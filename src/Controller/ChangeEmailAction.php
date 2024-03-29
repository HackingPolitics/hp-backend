<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Core\Validator\ValidatorInterface;
use App\Entity\User;
use App\Message\UserEmailChangeMessage;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ChangeEmailAction
{
    public function __invoke(
        Request $request,
        User $data,
        ManagerRegistry $registry,
        MessageBusInterface $bus,
        UserPasswordHasherInterface $passwordHasher,
        ValidatorInterface $validator
    ) {
        // DTO was validated by the DataTransformer,
        // email, confirmationPassword & validationUrl should be there
        $params = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $check = $passwordHasher->isPasswordValid($data, $params['confirmationPassword']);
        if (!$check) {
            throw new BadRequestHttpException('validate.user.passwordMismatch');
        }

        // the DataTransformer already set the new email on the entity (which is
        // good as the UniqueObject constraint cannot be used on the DTO) ->
        // get the old email here
        $em = $registry->getManagerForClass(User::class);
        $oldObject = $em->getUnitOfWork()
            ->getOriginalEntityData($data);

        if ($oldObject['email'] === $data->getEmail()) {
            throw new BadRequestHttpException('validate.user.email.notChanged');
        }

        // validate it to check if it's a valid email and not existing in the db
        $validator->validate($data, ['groups' => ['Default', 'user:changeEmail']]);

        // reset to prevent accidental saving by $em->flush
        $em->refresh($data);

        $bus->dispatch(
            new UserEmailChangeMessage($data->getId(), $params['email'],
                $params['validationUrl'])
        );

        // return 202: the action has not yet been enacted
        return new JsonResponse([
            'success' => true,
            'message' => 'Request received',
        ], Response::HTTP_ACCEPTED);
    }
}
