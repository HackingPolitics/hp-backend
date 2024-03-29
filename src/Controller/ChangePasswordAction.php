<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Core\Validator\ValidatorInterface;
use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ChangePasswordAction
{
    public function __invoke(
        Request $request,
        User $data,
        ManagerRegistry $registry,
        UserPasswordHasherInterface $passwordHasher,
        ValidatorInterface $validator
    ) {
        // DTO was validated by the DataTransformer,
        // confirmationPassword & password should be there
        $params = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        // the DataTransformer already set the new password on the entity
        $savedPwd = $data->getPassword();

        // restore old pw for checking
        $em = $registry->getManagerForClass(User::class);
        $oldObject = $em->getUnitOfWork()
            ->getOriginalEntityData($data);
        $data->setPassword($oldObject['password']);

        $check = $passwordHasher->isPasswordValid($data, $params['confirmationPassword']);
        if (!$check) {
            throw new BadRequestHttpException('validate.user.passwordMismatch');
        }

        // do not allow the user to set the same password again
        $sameCheck = $passwordHasher->isPasswordValid($data, $params['password']);
        if ($sameCheck) {
            throw new BadRequestHttpException('validate.user.password.notChanged');
        }

        // now set & save the new, encoded password
        $data->setPassword($savedPwd);
        $em->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Password changed',
        ]);
    }
}
