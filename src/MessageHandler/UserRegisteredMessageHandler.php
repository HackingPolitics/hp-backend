<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\User;
use App\Entity\Validation;
use App\Message\UserRegisteredMessage;
use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

class UserRegisteredMessageHandler implements MessageHandlerInterface, ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;

    /**
     * Create the validation token and send the user an email with a link to click.
     */
    public function __invoke(UserRegisteredMessage $message)
    {
        $this->logger()->debug(
            'Processing UserRegisteredMessage for user '.$message->userId
        );

        $entityManager = $this->entityManager();
        $user = $entityManager->getRepository(User::class)
            ->findOneBy([
                'id'        => $message->userId,
                'validated' => false,
                'deletedAt' => null,
            ]);

        if (!$user) {
            $this->logger()->info(
                "User {$message->userId} does not exist or is already validated!"
            );

            return;
        }

        $validation = $this->createValidation($user);

        $sent = $this->sendValidationMail($validation, $message->validationUrl);
        if ($sent) {
            $this->logger()
                ->info("Sent account validation email to user {$user->getEmail()}!");
        } else {
            $this->logger()
                ->error("Failed to send the account validation email to {$user->getEmail()}!");
        }
    }

    /**
     * @throws Exception when token generation fails
     */
    private function createValidation(User $user): Validation
    {
        $validation = new Validation();
        $validation->setUser($user);
        $validation->setType(Validation::TYPE_ACCOUNT);
        $validation->generateToken();

        // @todo make interval configurable
        $now = new DateTimeImmutable();
        $validUntil = $now->add(new DateInterval('P2D'));
        $validation->setExpiresAt($validUntil);

        $entityManager = $this->entityManager();
        $entityManager->persist($validation);
        $entityManager->flush();

        return $validation;
    }

    /**
     * Sends an email with the validation details (URL to click) to the new user.
     */
    private function sendValidationMail(Validation $validation, string $url): bool
    {
        // replace the placeholders, token & id are required and enforced via
        // constraint on the UserInput DTO, type is optional as information for
        // the client (which message to show on failed validation)
        $withToken = str_replace('{{token}}', $validation->getToken(), $url);
        $withId = str_replace('{{id}}', (string) $validation->getId(), $withToken);
        $withType = str_replace('{{type}}', $validation->getType(), $withId);

        $email = (new TemplatedEmail())
            // FROM is added via listener, subject is added via template
            ->to($validation->getUser()->getEmail())
            ->htmlTemplate('registration/mail.validation.html.twig')
            ->context([
                'id'            => $validation->getId(),
                'expiresAt'     => $validation->getExpiresAt(),
                'token'         => $validation->getToken(),
                'username'      => $validation->getUser()->getUsername(),
                'validationUrl' => $withType,

                // @todo use user specific TZ/locale if set
                'userTZ'        => $this->params()->get('default_timezone'),
                'userLocale'    => $this->params()->get('default_locale'),
            ]);

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

    private function params(): ParameterBagInterface
    {
        return $this->container->get(__METHOD__);
    }
}
