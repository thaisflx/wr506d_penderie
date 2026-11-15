<?php

namespace App\State;

use ApiPlatform\Doctrine\Common\State\PersistProcessor;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Ootd;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Création d'un look du jour : le propriétaire est toujours l'utilisateur connecté, jamais le client.
 *
 * @implements ProcessorInterface<Ootd, Ootd>
 */
final class OotdProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<Ootd, Ootd> $persistProcessor
     */
    public function __construct(
        #[Autowire(service: PersistProcessor::class)]
        private readonly ProcessorInterface $persistProcessor,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $user = $this->security->getUser();
        if (null === $data->getOwner() && $user instanceof User) {
            $data->setOwner($user);
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
