<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Lexik\Bundle\JWTAuthenticationBundle\Response\JWTAuthenticationFailureResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;
use Symfony\Contracts\Translation\TranslatorInterface;

class JWTEventSubscriber implements EventSubscriberInterface, ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            Events::JWT_CREATED            => 'onJWTCreated',
            Events::AUTHENTICATION_FAILURE => 'onAuthenticationFailure',
        ];
    }

    /**
     * Adds the users ID to the JWT.
     * We need it as single users can only be fetched via ID, "normal" users
     * cannot access the collection (to get their own user by username).
     */
    public function onJWTCreated(JWTCreatedEvent $event)
    {
        /** @var User $user */
        $user = $event->getUser();

        $payload       = $event->getData();
        $payload['id'] = $user->getId();

        // we need to encode the projects the user has write access to, so
        // the socket.io server can cross-check this with the requested
        // project ID before allowing a socket client into a room
        $payload['editableProjects'] = [];
        $payload['editableProposals'] = [];
        foreach ($user->getProjectMemberships() as $membership) {
            if ($membership->getProject()->userCanWrite($user)) {
                $payload['editableProjects'][] = $membership->getProject()->getId();

                foreach ($membership->getProject()->getProposals() as $proposal) {
                    $payload['editableProposals'][] = $proposal->getId();
                }
            }
        }

        $event->setData($payload);
    }

    /**
     * Translates the returned message. Allows us to use error constants
     * (key.with.separators) compatible with i18next in the client.
     */
    public function onAuthenticationFailure(AuthenticationFailureEvent $event)
    {
        $data = json_decode($event->getResponse()->getContent(), true);

        $response = new JWTAuthenticationFailureResponse(
            $this->translator()->trans($data['message'], [], 'security'),
            $data['code']
        );
        $event->setResponse($response);
    }

    private function translator(): TranslatorInterface
    {
        return $this->container->get(__METHOD__);
    }
}
