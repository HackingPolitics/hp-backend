<?php

declare(strict_types=1);

namespace App\Serializer;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

class UserNormalizer implements ContextAwareNormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'USER_NORMALIZER_ALREADY_CALLED';

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
     *
     * @param User $object
     */
    public function normalize($object, $format = null, array $context = [])
    {
        $isPM = false;

        $token = $this->tokenStorage->getToken();
        if ($token && $token->getUser() instanceof UserInterface) {
            $currentUser = $token->getUser();

            if ($currentUser->hasRole(User::ROLE_PROCESS_MANAGER)) {
                $isPM = true;
            }

            // only when a user item or the user collection is accessed, we
            // don't want to add user:self on requests for other entities but
            // this normalizer is still executed with resourceClass == User
            if (0 === stripos($context['request_uri'], '/users/')
                && $object->getId() == $currentUser->getId()
            ) {
                $context['groups'][] = 'user:self';
            }
        }

        $context[self::ALREADY_CALLED] = true;

        $result = $this->normalizer->normalize($object, $format, $context);

        // @todo we now remove the properties that may be soft-deleted
        // how can we prevent APIPlatform/Doctrine from fetching them in the first place?

        if (!$isPM && isset($result['createdProjects'])) {
            foreach ($object->getCreatedProjects() as $createdProject) {
                if ($createdProject->isDeleted() || $createdProject->isLocked()) {
                    $result['createdProjects'] = array_filter(
                        $result['createdProjects'],
                        static function ($ele) use ($createdProject) {
                            return $ele['id'] != $createdProject->getId();
                        }
                    );
                }
            }
        }

        return $result;
    }

    public function supportsNormalization($data, $format = null, array $context = [])
    {
        // Make sure we're not called twice
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        return $data instanceof User;
    }
}
