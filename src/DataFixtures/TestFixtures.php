<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\ActionLog;
use App\Entity\User;
use App\Entity\Validation;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class TestFixtures extends Fixture implements FixtureGroupInterface
{
    public const ADMIN = [
        'id'        => 1, // because we persist him first
        'username'  => 'admin',
        'email'     => 'admin@zukunftsstadt.de',
        'roles'     => [User::ROLE_ADMIN],
        'password'  => 'no_secret',
        'createdAt' => '2018-01-01',
        'deletedAt' => null,
    ];

    public const DELETED_USER = [
        'id'        => 2,
        'username'  => 'deleted_2',
        'email'     => 'deleted_2@zukunftsstadt.de',
        'roles'     => [],
        'password'  => 'empty',
        'createdAt' => '2018-02-01',
        'deletedAt' => '2019-12-01',
    ];

    public const USER = [
        'id'        => 3,
        'username'  => 'user',
        'email'     => 'user@zukunftsstadt.de',
        'roles'     => [],
        'password'  => '*?*user7777',
        'createdAt' => '2019-02-01',
        'deletedAt' => null,
        'firstName' => 'Peter',
    ];

    private UserPasswordEncoderInterface $encoder;

    public function __construct(UserPasswordEncoderInterface $encoder)
    {
        $this->encoder = $encoder;
    }

    public static function getGroups(): array
    {
        return ['test'];
    }

    public function load(ObjectManager $manager): void
    {
        $loggerBackup = $manager->getConnection()->getConfiguration()
            ->getSQLLogger();
        $manager->getConnection()->getConfiguration()->setSQLLogger(null);

        $admin = $this->createUser(self::ADMIN);
        $manager->persist($admin);

        $deletedUser = $this->createUser(self::DELETED_USER);
        $manager->persist($deletedUser);

        $user = $this->createUser(self::USER);
        $manager->persist($user);

        $accountValidation = new Validation();
        $accountValidation->setUser($user);
        $accountValidation->generateToken();
        $accountValidation->setType(Validation::TYPE_ACCOUNT);
        $accountValidation->setExpiresAt(new DateTimeImmutable('tomorrow'));
        $manager->persist($accountValidation);

        $emailValidation = new Validation();
        $emailValidation->setUser($user);
        $emailValidation->generateToken();
        $emailValidation->setType(Validation::TYPE_CHANGE_EMAIL);
        $emailValidation->setContent(['email' => 'new@zukunftsstadt.de']);
        $emailValidation->setExpiresAt(new DateTimeImmutable('tomorrow'));
        $manager->persist($emailValidation);

        $pwValidation = new Validation();
        $pwValidation->setUser($user);
        $pwValidation->generateToken();
        $pwValidation->setType(Validation::TYPE_RESET_PASSWORD);
        $pwValidation->setExpiresAt(new DateTimeImmutable('tomorrow'));
        $manager->persist($pwValidation);

        // flush here, we need the IDs for the following entities
        $manager->flush();

        $this->populateActionLog($manager);

        $manager->flush();
        $manager->getConnection()->getConfiguration()->setSQLLogger($loggerBackup);
    }

    protected function createUser(array $data): User
    {
        $user = new User();
        $user->setUsername($data['username']);
        $user->setEmail($data['email']);
        $user->setRoles($data['roles']);

        if (isset($data['validated'])) {
            $user->setValidated($data['validated']);
        } else {
            $user->setValidated(true);
        }

        if (isset($data['firstName'])) {
            $user->setFirstName($data['firstName']);
        }

        if (isset($data['lastName'])) {
            $user->setLastName($data['lastName']);
        }

        if (isset($data['createdAt'])) {
            $user->setCreatedAt(new DateTimeImmutable($data['createdAt'], new DateTimeZone('UTC')));
        }

        if (isset($data['deletedAt'])) {
            $user->setDeletedAt(new DateTimeImmutable($data['deletedAt'], new DateTimeZone('UTC')));
            $user->setPassword('');
        } else {
            $user->setPassword(
                $this->encoder->encodePassword(
                    $user,
                    $data['password'],
                )
            );
        }

        return $user;
    }

    protected function populateActionLog(ObjectManager $manager): void
    {
        $data = [
            [
                'ipAddress' => '127.0.0.1',
                'action'    => 'create-idea',
            ],
            [
                'ipAddress' => '127.0.0.1',
                'username'  => 'tester',
                'action'    => 'create-project',
            ],
            [
                'ipAddress' => '127.0.0.1',
                'username'  => 'tester',
                'action'    => 'create-comment',
            ],
            [
                'ipAddress' => '127.0.0.1',
                'username'  => 'tester',
                'action'    => 'create-comment',
                'interval'  => 'PT10H',
            ],
            [
                'action'   => 'create-comment',
                'interval' => 'P1D',
            ],
        ];

        foreach ($data as $entry) {
            $manager->persist($this->createActionLog($entry));
        }
    }

    protected function createActionLog(array $data): ActionLog
    {
        $log = new ActionLog();
        $log->ipAddress = $data['ipAddress'] ?? null;
        $log->username = $data['username'] ?? null;
        $log->action = $data['action'];

        if (isset($data['interval'])) {
            $date = (new DateTimeImmutable())
                ->sub(new DateInterval($data['interval']));
            $log->timestamp = $date;
        }

        return $log;
    }
}
