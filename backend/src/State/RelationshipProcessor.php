<?php

namespace App\State;

use ApiPlatform\Doctrine\Common\State\PersistProcessor;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\ValidatorInterface;
use App\Entity\Relationship;
use App\Entity\User;
use App\Enum\RelationshipStatus;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Demandes d'abonnement et d'ami proche (docs/decisions.md).
 * - Envoi : le demandeur est l'utilisateur connecté, quoi que dise le client ; la demande reste en attente.
 *   Un compte introuvable, suspendu, bloqué (dans un sens ou dans l'autre) ou -18 répond 404.
 * - Acceptation : seule transition permise, pending -> accepted.
 *
 * @implements ProcessorInterface<Relationship, Relationship>
 */
final class RelationshipProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<Relationship, Relationship> $persistProcessor
     */
    public function __construct(
        #[Autowire(service: PersistProcessor::class)]
        private readonly ProcessorInterface $persistProcessor,
        private readonly Security $security,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Relationship
    {
        if ($operation instanceof Post) {
            $this->prepareRequest($data, $context);
        } else {
            $this->checkAcceptance($data, $context);
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function prepareRequest(Relationship $relationship, array $context): void
    {
        $addressee = $relationship->getAddressee();
        if (null === $addressee) {
            // Le compte n'a pas été trouvé par PublicUserExtension (inexistant, suspendu ou bloqué) : 404,
            // sauf si le champ manque dans la requête
            $request = $context['request'] ?? null;
            if ($request instanceof Request && !\array_key_exists('addressee', $request->toArray())) {
                throw new BadRequestHttpException('Indique le compte à qui envoyer la demande (addressee).');
            }
            throw new NotFoundHttpException('Ce compte est introuvable.');
        }
        // Les comptes -18 ne reçoivent pas de demande, même de leur parent
        if ($addressee->isMinor()) {
            throw new NotFoundHttpException('Ce compte est introuvable.');
        }

        $requester = $this->security->getUser();
        \assert($requester instanceof User);
        // Le statut n'est pas modifiable à la création (absent du groupe relationship:create) : il reste pending
        $relationship->setRequester($requester);

        // Validé ici, une fois le demandeur connu : doublon et demande à soi-même
        $this->validator->validate($relationship);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function checkAcceptance(Relationship $relationship, array $context): void
    {
        $previous = $context['previous_data'] ?? null;
        $wasPending = $previous instanceof Relationship && RelationshipStatus::Pending === $previous->getStatus();
        if (!$wasPending || !$relationship->isAccepted()) {
            throw new UnprocessableEntityHttpException('Seule une demande en attente peut être acceptée.');
        }
    }
}
