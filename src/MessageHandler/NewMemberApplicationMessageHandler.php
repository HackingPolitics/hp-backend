<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\ProjectMembership;
use App\Message\NewMemberApplicationMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

class NewMemberApplicationMessageHandler implements MessageHandlerInterface, ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;

    /**
     * Send the project coordinator(s) a notification email.
     */
    public function __invoke(NewMemberApplicationMessage $message)
    {
        $entityManager = $this->entityManager();
        $membership = $entityManager->getRepository(ProjectMembership::class)
            ->findOneBy([
                'user'    => $message->userId,
                'project' => $message->projectId,
                'role'    => ProjectMembership::ROLE_APPLICANT,
            ]);

        if (!$membership) {
            $this->logger()->info(
                "Membership for User {$message->userId} / Project {$message->projectId} does not exist or is no application!"
            );

            return;
        }

        $sent = $this->sendNotificationMail($membership);
        if ($sent) {
            $this->logger()
                ->info('Sent new-application notification email!');
        } else {
            $this->logger()
                ->error('Failed to send the new-application notification email!');
        }
    }

    /**
     * Sends an email to the project coordinators.
     */
    private function sendNotificationMail(ProjectMembership $membership): bool
    {
        $project = $membership->getProject();
        $coordinators = $project->getMembersByRole(ProjectMembership::ROLE_COORDINATOR);
        if (!count($coordinators)) {
            $this->logger()
                ->error("No project coordinators found for {$project->getId()}!");

            return false;
        }

        $email = (new TemplatedEmail())
            // FROM is added via listener, subject is added via template
            ->htmlTemplate('project/mail.new-member-application.html.twig')
            ->context([
                'username'     => $membership->getUser()->getUsername(),
                'projectTitle' => $project->getTitle(),
                'projectId'    => $project->getId(), // if project is unnamed
            ]);

        foreach ($coordinators as $coordinator) {
            $email->addTo($coordinator->getEmail());
        }

        $sent = $this->mailer()->send($email);

        return $sent instanceof SentMessage && null !== $sent->getMessageId();
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->container->get(__METHOD__);
    }

    private function logger(): LoggerInterface
    {
        return $this->container->get(__METHOD__);
    }

    private function mailer(): TransportInterface
    {
        return $this->container->get(__METHOD__);
    }
}
