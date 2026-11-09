<?php

namespace App\State;

use ApiPlatform\Doctrine\Common\State\PersistProcessor;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\ValidatorInterface;
use App\Entity\Block;
use App\Entity\User;
use App\Repository\RelationshipRepository;
use App\Service\RelationshipChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Bloquer quelqu'un (docs/decisions.md) :
 * - le bloqueur est l'utilisateur connecté ;
 * - un compte introuvable, suspendu ou déjà lié à moi par un blocage répond 404 (rien n'est révélé) ;
 * - un parent ne peut pas bloquer son compte enfant, ni un enfant son parent (422) ;
 * - toutes les relations entre les deux personnes sont supprimées, dans la même transaction.
 *
 * @implements ProcessorInterface<Block, Block>
 */
final class BlockProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<Block, Block> $persistProcessor
     */
    public function __construct(
        #[Autowire(service: PersistProcessor::class)]
        private readonly ProcessorInterface $persistProcessor,
        private readonly Security $security,
        private readonly ValidatorInterface $validator,
        private readonly RelationshipChecker $checker,
        private readonly RelationshipRepository $relationships,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Block
    {
        $blocked = $data->getBlocked();
        if (null === $blocked) {
            // Compte non trouvé par PublicUserExtension (inexistant, suspendu, déjà bloqué dans un sens ou dans l'autre)
            $request = $context['request'] ?? null;
            if ($request instanceof Request && !\array_key_exists('blocked', $request->toArray())) {
                throw new BadRequestHttpException('Indique le compte à bloquer (blocked).');
            }
            throw new NotFoundHttpException('Ce compte est introuvable.');
        }

        $blocker = $this->security->getUser();
        \assert($blocker instanceof User);
        if ($this->checker->isChildOf($blocked, $blocker) || $this->checker->isChildOf($blocker, $blocked)) {
            throw new UnprocessableEntityHttpException('Tu ne peux pas bloquer ton compte parent ou ton compte enfant.');
        }

        $data->setBlocker($blocker);
        // Validé ici, une fois le bloqueur connu : se bloquer soi-même
        $this->validator->validate($data);

        return $this->entityManager->wrapInTransaction(function () use ($data, $blocker, $blocked, $operation, $uriVariables, $context): Block {
            $this->relationships->deleteBetween($blocker, $blocked);

            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        });
    }
}
