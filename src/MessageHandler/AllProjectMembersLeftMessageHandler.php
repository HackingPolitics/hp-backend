<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Project;
use App\Entity\User;
use App\Message\AllProjectMembersLeftMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

class AllProjectMembersLeftMessageHandler implements MessageHandlerInterface, ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;

    /**
     * Send the process manager(s) a notification email.
     */
    public function __invoke(AllProjectMembersLeftMessage $message)
    {
        $entityManager = $this->entityManager();
        $project = $entityManager->getRepository(Project::class)
            ->find($message->projectId);

        if (!$project || $project->isDeleted()
            || !$project->isLocked()
        ) {
            $this->logger()->info(
                "Project {$message->projectId} does not exist or is not locked!"
            );

            return;
        }

        $sent = $this->sendNotificationMail($project);
        if ($sent) {
            $this->logger()
                ->info('Sent project-locked notification email!');
        } else {
            $this->logger()
                ->error('Failed to send the project-locked notification email!');
        }
    }

    /**
     * Sends an email to the process managers to inform about the deactivated project.
     */
    private function sendNotificationMail(Project $project): bool
    {
        $admins = $this->entityManager()
            ->getRepository(User::class)
            ->loadProcessManagers();
        if (!count($admins)) {
            $this->logger()
                ->error('No process managers found!');

            return false;
        }

        $email = (new TemplatedEmail())
            // FROM is added via listener, subject is added via template
            ->htmlTemplate('project/mail.noMembers.pmNotification.html.twig')
            ->context([
                'projectTitle' => $project->getTitle(),
                'projectId'   => $project->getId(), // if project is unnamed
            ]);

        foreach ($admins as $admin) {
            $email->addTo($admin->getEmail());
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
