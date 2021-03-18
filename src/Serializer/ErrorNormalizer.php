<?php

declare(strict_types=1);

namespace App\Serializer;

use ApiPlatform\Core\Hydra\Serializer\ErrorNormalizer as HydraErrorNormalizer;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Allows to translate the hydra:description for error results, to transform
 * natural language to translation strings (only letters and dots as separator),
 * so the message can be again translated in the client.
 */
class ErrorNormalizer implements NormalizerInterface, CacheableSupportsMethodInterface
{
    protected HydraErrorNormalizer $decorated;

    private TranslatorInterface $translator;

    public function __construct(HydraErrorNormalizer $decorated, TranslatorInterface $translator)
    {
        $this->decorated = $decorated;
        $this->translator = $translator;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($object, $format = null, array $context = [])
    {
        $result = $this->decorated->normalize($object, $format, $context);
        $result['hydra:description'] = $this->translator
            ->trans($result['hydra:description'], [], 'validators');

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        return $this->decorated->supportsNormalization($data, $format);
    }

    /**
     * {@inheritdoc}
     */
    public function hasCacheableSupportsMethod(): bool
    {
        return $this->decorated->hasCacheableSupportsMethod();
    }
}
