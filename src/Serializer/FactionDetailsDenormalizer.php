<?php

declare(strict_types=1);

namespace App\Serializer;

use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use App\Entity\FactionDetails;
use App\Entity\ProjectMembership;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Normalizer\ContextAwareDenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;

class FactionDetailsDenormalizer implements ContextAwareDenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    private const ALREADY_CALLED = 'FACTION_DETAILS_DENORMALIZER_ALREADY_CALLED';

    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null, array $context = [])
    {
        // Make sure we're not called twice
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        return FactionDetails::class === $type
            && isset($context[AbstractItemNormalizer::OBJECT_TO_POPULATE]);
    }

    /**
     * {@inheritdoc}
     *
     * @param FactionDetails $data
     */
    public function denormalize($data, $type, $format = null, array $context = [])
    {
        $object = $context[AbstractItemNormalizer::OBJECT_TO_POPULATE];
        $project = $object->getProject();
        $token = $this->tokenStorage->getToken();
        if ($project && $token && $token->getUser() instanceof UserInterface) {
            $currentUser = $token->getUser();

            if ($project->getUserRole($currentUser) === ProjectMembership::ROLE_COORDINATOR) {
                $context['groups'][] = 'factionDetails:coordinator-write';

                // this denormalizer is never called for the creation of
                // factionDetails so we can simply add this here without checking
                // the context for operation_type=item & item_operation_name=put
                $context['groups'][] = 'factionDetails:coordinator-update';
            }

            // writers & coordinators
            if ($project->userCanWrite($currentUser)) {
                $context['groups'][] = 'factionDetails:member-write';

                // same as before, no additional checks needed
                $context['groups'][] = 'factionDetails:member-update';
            }
        }

        $context[self::ALREADY_CALLED] = true;

        return $this->denormalizer->denormalize($data, $type, $format, $context);
    }
}
