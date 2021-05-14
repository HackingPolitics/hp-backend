<?php

declare(strict_types=1);

namespace App\Serializer;

use App\Entity\FactionDetails;
use App\Entity\ProjectMembership;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

class FactionDetailsNormalizer implements ContextAwareNormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'FACTION_DETAILS_NORMALIZER_ALREADY_CALLED';

    private TokenStorageInterface $tokenStorage;

    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * {@inheritdoc}
     *
     * @param FactionDetails $object
     */
    public function normalize($object, $format = null, array $context = [])
    {
        $isPM = false;

        $token = $this->tokenStorage->getToken();
        if ($token && $token->getUser() instanceof UserInterface) {
            /** @var User $currentUser */
            $currentUser = $token->getUser();

            if ($currentUser->hasRole(User::ROLE_PROCESS_MANAGER)) {
                $isPM = true;
            }

            $project = $object->getProject();
            if ($project) {
                if ($project->userCanRead($currentUser)) {
                    $context['groups'][] = 'factionDetails:member-read';
                }

                $role = $project->getUserRole($currentUser);
                if ($role === ProjectMembership::ROLE_COORDINATOR) {
                    $context['groups'][] = 'factionDetails:coordinator-read';
                }

                if ($role === ProjectMembership::ROLE_OBSERVER) {
                    $context['groups'][] = 'factionDetails:observer-read';
                }

                if ($role === ProjectMembership::ROLE_WRITER) {
                    $context['groups'][] = 'factionDetails:writer-read';
                }
            }
        }

        $context[self::ALREADY_CALLED] = true;

        /** @var array|\ArrayObject $result */
        $result = $this->normalizer->normalize($object, $format, $context);

        // @todo we now remove the properties that may be soft-deleted
        // how can we prevent APIPlatform/Doctrine from fetching them in the first place?

        if (!$isPM && $object->getUpdatedBy() && (
            $object->getUpdatedBy()->isDeleted()
            || !$object->getUpdatedBy()->isValidated()
            || !$object->getUpdatedBy()->isActive()
        )) {
            $result['updatedBy'] = null;
        }

        if (!$isPM && $object->getTeamContact() && (
                $object->getTeamContact()->isDeleted()
                || !$object->getTeamContact()->isValidated()
                || !$object->getTeamContact()->isActive()
            )) {
            $result['teamContact'] = null;
        }

        return $result;
    }

    public function supportsNormalization($data, $format = null, array $context = []): bool
    {
        // Make sure we're not called twice
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        return $data instanceof FactionDetails;
    }
}
