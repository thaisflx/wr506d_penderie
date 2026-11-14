<?php

namespace App\Serializer;

use App\Entity\Item;
use App\Entity\Ootd;
use App\Service\ItemExposure;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Un objet devenu privé ou perso après la publication du look disparaît de « wornItems »
 * pour tout le monde sauf le propriétaire (docs/decisions.md, lot 2).
 */
final class OotdNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'ootd_normalizer_already_called';

    public function __construct(
        private readonly Security $security,
        private readonly ItemExposure $exposure,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<mixed>|string|int|float|bool|\ArrayObject<int|string, mixed>|null
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $context[self::ALREADY_CALLED] = true;
        $normalized = $this->normalizer->normalize($data, $format, $context);

        if (!$data instanceof Ootd || !\is_array($normalized) || !isset($normalized['wornItems']) || $data->getOwner() === $this->security->getUser()) {
            return $normalized;
        }

        $hiddenIds = $data->getItems()
            ->filter(fn (Item $item) => !$this->exposure->isShareable($item))
            ->map(fn (Item $item) => $item->getId())
            ->getValues();
        $normalized['wornItems'] = array_values(array_filter(
            (array) $normalized['wornItems'],
            static fn (mixed $item) => !\is_array($item) || !\in_array($item['id'] ?? null, $hiddenIds, true),
        ));

        return $normalized;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Ootd && !isset($context[self::ALREADY_CALLED]);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [Ootd::class => false];
    }
}
