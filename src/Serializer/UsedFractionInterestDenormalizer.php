<?php

declare(strict_types=1);

namespace App\Serializer;

use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use App\Entity\ProjectMembership;
use App\Entity\UsedFractionInterest;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Normalizer\ContextAwareDenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;

class UsedFractionInterestDenormalizer implements ContextAwareDenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    private const ALREADY_CALLED = 'USED_FRACTION_INTEREST_DENORMALIZER_ALREADY_CALLED';

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

        return UsedFractionInterest::class === $type
            && isset($context[AbstractItemNormalizer::OBJECT_TO_POPULATE]);
    }

    /**
     * {@inheritdoc}
     *
     * @param UsedFractionInterest $data
     */
    public function denormalize($data, $type, $format = null, array $context = [])
    {
        $object = $context[AbstractItemNormalizer::OBJECT_TO_POPULATE];
        $project = $object->getFractionInterest()
            ? $object->getFractionInterest()->getProject()
            : null;

        $token = $this->tokenStorage->getToken();
        if ($project && $token && $token->getUser() instanceof UserInterface) {
            $currentUser = $token->getUser();

            if (ProjectMembership::ROLE_COORDINATOR === $project->getUserRole($currentUser)) {
                $context['groups'][] = 'usedFractionInterest:coordinator-write';

                // this denormalizer is never called for the creation of
                // usedFractionInterests so we can simply add this here without checking
                // the context for operation_type=item & item_operation_name=put
                $context['groups'][] = 'usedFractionInterest:coordinator-update';
            }

            // writers & coordinators
            if ($project->userCanWrite($currentUser)) {
                $context['groups'][] = 'usedFractionInterest:member-write';

                // same as before, no additional checks needed
                $context['groups'][] = 'usedFractionInterest:member-update';
            }
        }

        $context[self::ALREADY_CALLED] = true;

        return $this->denormalizer->denormalize($data, $type, $format, $context);
    }
}
