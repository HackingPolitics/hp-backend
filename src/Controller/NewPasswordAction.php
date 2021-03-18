<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Message\NewUserPasswordMessage;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class NewPasswordAction
{
    public function __invoke(
        Request $request,
        User $data,
        ManagerRegistry $registry,
        MessageBusInterface $bus,
        UserPasswordEncoderInterface $passwordEncoder
    ): JsonResponse {
        // DTO was validated by the DataTransformer, validationUrl
        // should be there
        $params = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        // set the password to a random, unknown value to devalidate
        // the users current password, e.g. when there is reason to
        // believe it was hacked
        $data->setPassword(
            $passwordEncoder->encodePassword(
                $data,
                random_bytes(25)
            )
        );
        $entityManager = $registry->getManagerForClass(User::class);
        $entityManager->flush();

        $bus->dispatch(
            new NewUserPasswordMessage($data->getId(), $params['validationUrl'])
        );

        // return 202: the action has not yet been enacted
        return new JsonResponse([
            'success' => true,
            'message' => 'Request received',
        ], Response::HTTP_ACCEPTED);
    }
}
