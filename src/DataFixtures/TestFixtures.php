<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\ActionLog;
use App\Entity\Faction;
use App\Entity\FactionDetails;
use App\Entity\FactionInterest;
use App\Entity\FederalState;
use App\Entity\Parliament;
use App\Entity\Project;
use App\Entity\ProjectMembership;
use App\Entity\User;
use App\Entity\Validation;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class TestFixtures extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
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

    public const PROCESS_MANAGER = [
        'id'        => 2,
        'username'  => 'po',
        'email'     => 'process@zukunftsstadt.de',
        'roles'     => [User::ROLE_PROCESS_MANAGER],
        'password'  => 'my_precious',
        'createdAt' => '2018-02-01',
        'deletedAt' => null,
    ];

    public const DELETED_USER = [
        'id'        => 3,
        'username'  => 'deleted_3',
        'email'     => 'deleted_3@zukunftsstadt.de',
        'roles'     => [],
        'password'  => 'empty',
        'createdAt' => '2018-02-01',
        'deletedAt' => '2019-12-01',
    ];

    public const GUEST = [
        'id'        => 4,
        'username'  => 'guest',
        'email'     => 'guest@zukunftsstadt.de',
        'roles'     => [],
        'password'  => '-*?*guest',
        'createdAt' => '2019-01-01',
        'deletedAt' => null,
        'validated' => false,
    ];

    public const PROJECT_COORDINATOR = [
        'id'        => 5,
        'username'  => 'coordinator',
        'email'     => 'project@zukunftsstadt.de',
        'roles'     => [],
        'password'  => '*?*coordinator',
        'createdAt' => '2019-02-01',
        'deletedAt' => null,
        'firstname' => 'po',
    ];

    public const PROJECT_WRITER = [
        'id'        => 6,
        'username'  => 'writer',
        'email'     => 'writer@zukunftsstadt.de',
        'firstName' => 'Peter',
        'lastName'  => 'Pan',
        'roles'     => [],
        'password'  => '*?*writer',
        'createdAt' => '2019-02-02',
        'validated' => true,
        'deletedAt' => null,
    ];

    public const PROJECT_OBSERVER = [
        'id'        => 7,
        'username'  => 'observer',
        'email'     => 'observer@zukunftsstadt.de',
        'firstName' => 'Paul',
        'lastName'  => 'Pflaume',
        'roles'     => [],
        'password'  => '*?*observer',
        'createdAt' => '2020-02-02',
        'validated' => true,
        'deletedAt' => null,
    ];

    public const PROJECT = [
        'id'          => 1,
        'description' => 'description with 20 characters',
        'title'       => 'Car-free Dresden',
        'impact'      => 'impact with 10 characters',
        'topic'       => 'topic with 10 characters',
        'state'       => Project::STATE_PRIVATE,
    ];

    public const LOCKED_PROJECT = [
        'id'     => 2,
        'title'  => 'Locked Project',
        'locked' => true,
        'impact' => 'locked impact',
        'topic'  => 'locked topic',
        'state'  => Project::STATE_PUBLIC,
    ];

    public const DELETED_PROJECT = [
        'id'        => 3,
        'title'     => 'Deleted Project',
        'deletedAt' => '2019-12-12 12:12:12',
    ];

    public const PARLIAMENT = [
        'id'           => 1,
        'title'        => 'Stadtrat Stuttgart',
        'location'     => 'LH Stuttgart',
        'zipArea'      => '123',
        'federalState' => 'Baden-Württemberg',
        'validatedAt'  => '2021-05-01',

        // political/organizational details
        'headOfAdministration'      => 'Max Muster',
        'headOfAdministrationTitle' => 'Oberbürgermeister',
    ];

    public const FACTION_GREEN = [
        'id'          => 1,
        'name'        => 'Grün',
        'memberCount' => 1,
    ];
    public const FACTION_RED = [
        'id'          => 2,
        'name'        => 'Rot',
        'memberCount' => 2,
    ];
    public const FACTION_BLACK = [
        'id'          => 3,
        'name'        => 'Schwarz',
        'memberCount' => 3,
    ];
    public const FACTION_YELLOW = [
        'id'          => 4,
        'name'        => 'Gelb',
        'memberCount' => 4,
        'active'      => false,
    ];

    private UserPasswordEncoderInterface $encoder;

    public function getDependencies(): array
    {
        return [InitialFixtures::class];
    }

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

        //region Users + Validations
        $admin = $this->createUser(self::ADMIN);
        $manager->persist($admin);

        $processManager = $this->createUser(self::PROCESS_MANAGER);
        $manager->persist($processManager);

        $deletedUser = $this->createUser(self::DELETED_USER);
        $manager->persist($deletedUser);

        $guest = $this->createUser(self::GUEST);
        $manager->persist($guest);

        $accountValidation = new Validation();
        $accountValidation->setUser($guest);
        $accountValidation->generateToken();
        $accountValidation->setType(Validation::TYPE_ACCOUNT);
        $accountValidation->setExpiresAt(new DateTimeImmutable('tomorrow'));
        $manager->persist($accountValidation);

        $projectCoordinator = $this->createUser(self::PROJECT_COORDINATOR);
        $manager->persist($projectCoordinator);

        $emailValidation = new Validation();
        $emailValidation->setUser($projectCoordinator);
        $emailValidation->generateToken();
        $emailValidation->setType(Validation::TYPE_CHANGE_EMAIL);
        $emailValidation->setContent(['email' => 'new@zukunftsstadt.de']);
        $emailValidation->setExpiresAt(new DateTimeImmutable('tomorrow'));
        $manager->persist($emailValidation);

        $projectWriter = $this->createUser(self::PROJECT_WRITER);
        $manager->persist($projectWriter);

        $projectObserver = $this->createUser(self::PROJECT_OBSERVER);
        $manager->persist($projectObserver);

        $pwValidation = new Validation();
        $pwValidation->setUser($projectObserver);
        $pwValidation->generateToken();
        $pwValidation->setType(Validation::TYPE_RESET_PASSWORD);
        $pwValidation->setExpiresAt(new DateTimeImmutable('tomorrow'));
        $manager->persist($pwValidation);
        //endregion

        //region Parliament + Factions
        $parliament = $this->createParliament(self::PARLIAMENT, $manager, $admin);
        $parliament->setUpdatedBy($admin);
        $manager->persist($parliament);

        $greenFaction = $this->createFaction(self::FACTION_GREEN, $admin);
        $parliament->addFaction($greenFaction);
        $redFaction = $this->createFaction(self::FACTION_RED, $admin);
        $parliament->addFaction($redFaction);
        $blackFaction = $this->createFaction(self::FACTION_BLACK, $admin);
        $parliament->addFaction($blackFaction);
        $yellowFaction = $this->createFaction(self::FACTION_YELLOW, $admin);
        $parliament->addFaction($yellowFaction);
        //endregion

        /**
         * Create normal project.
         */
        $project = $this->createProject(self::PROJECT, $projectCoordinator,
            $projectCoordinator, $projectWriter, $projectObserver);
        $parliament->addProject($project);
        $manager->persist($project);

        $detailsGreen = new FactionDetails();
        $detailsGreen->setContactEmail('green@zukunftsstadt.de');
        $detailsGreen->setContactName('Green');
        $detailsGreen->setContactPhone('123');
        $detailsGreen->setPossiblePartner(true);
        $detailsGreen->setPossibleProponent(true);
        $detailsGreen->setTeamContact($projectObserver);
        $detailsGreen->setUpdatedBy($projectWriter);
        $greenFaction->addDetails($detailsGreen);
        $project->addFactionDetails($detailsGreen);

        $detailsBlack = new FactionDetails();
        $detailsBlack->setContactEmail('black@zukunftsstadt.de');
        $detailsBlack->setContactName('Black');
        $detailsBlack->setContactPhone('321');
        $detailsBlack->setPossiblePartner(true);
        $blackFaction->addDetails($detailsBlack);
        $project->addFactionDetails($detailsBlack);

        $interest1 = new FactionInterest();
        $interest1->setDescription('interest1');
        $interest1->setUpdatedBy($admin);
        $detailsGreen->addInterest($interest1);

        $interest2 = new FactionInterest();
        $interest2->setDescription('interest2');
        $interest2->setUpdatedBy($admin);
        $detailsGreen->addInterest($interest2);
        /**
         * /Create normal project.
         */

        /**
         * Create locked project.
         */
        $lockedProject = $this->createProject(self::LOCKED_PROJECT,
            $projectCoordinator, $projectCoordinator, $projectWriter);
        $parliament->addProject($lockedProject);
        $manager->persist($lockedProject);
        /**
         * /Create locked project.
         */

        /**
         * Create deleted project.
         */
        $deletedProject = $this->createProject(self::DELETED_PROJECT,
            $projectWriter);
        $parliament->addProject($deletedProject);
        $manager->persist($deletedProject);
        /*
         * /Create deleted project
         */

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

    protected function createProject(
        array $data,
        User $creator,
        ?User $coordinator = null,
        ?User $member = null,
        ?User $observer = null
    ): Project {
        $project = new Project();

        if (isset($data['deletedAt'])) {
            $project->setDeletedAt(new DateTimeImmutable($data['deletedAt'], new DateTimeZone('UTC')));
        }
        if (isset($data['description'])) {
            $project->setDescription($data['description']);
        }
        if (isset($data['locked'])) {
            $project->setLocked($data['locked']);
        }
        if (isset($data['topic'])) {
            $project->setTopic($data['topic']);
        }
        if (isset($data['title'])) {
            $project->setTitle($data['title']);
        }
        if (isset($data['state'])) {
            $project->setState($data['state']);
        }
        if (isset($data['impact'])) {
            $project->setImpact($data['impact']);
        }

        $creator->addCreatedProject($project);

        if ($coordinator) {
            $coordinatorShip = new ProjectMembership();
            $coordinatorShip->setRole(ProjectMembership::ROLE_COORDINATOR);
            $coordinatorShip->setSkills('coordinator skills');
            $coordinatorShip->setMotivation('coordinator motivation');
            $coordinator->addProjectMembership($coordinatorShip);
            $project->addMembership($coordinatorShip);
        }

        if ($member) {
            $membership = new ProjectMembership();
            $membership->setRole(ProjectMembership::ROLE_WRITER);
            $membership->setSkills('writer skills');
            $membership->setMotivation('writer motivation');
            $member->addProjectMembership($membership);
            $project->addMembership($membership);
        }

        if ($observer) {
            $observerRole = new ProjectMembership();
            $observerRole->setRole(ProjectMembership::ROLE_OBSERVER);
            $observerRole->setSkills('observer skills');
            $observerRole->setMotivation('observer motivation');
            $observer->addProjectMembership($observerRole);
            $project->addMembership($observerRole);
        }

        return $project;
    }

    protected function createParliament(
        array $data,
        ObjectManager $manager,
        ?User $creator = null
    ): Parliament
    {
        $parliament = new Parliament();

        if (isset($data['title'])) {
            $parliament->setTitle($data['title']);
        }
        if (isset($data['location'])) {
            $parliament->setLocation($data['location']);
        }
        if (isset($data['zipArea'])) {
            $parliament->setZipArea($data['zipArea']);
        }
        if (isset($data['headOfAdministration'])) {
            $parliament->setHeadOfAdministration($data['headOfAdministration']);
        }
        if (isset($data['headOfAdministrationTitle'])) {
            $parliament->setHeadOfAdministrationTitle($data['headOfAdministrationTitle']);
        }
        if (isset($data['url'])) {
            $parliament->setUrl($data['url']);
        }
        if (isset($data['wikipediaUrl'])) {
            $parliament->setWikipediaUrl($data['wikipediaUrl']);
        }
        if (isset($data['validatedAt'])) {
            $parliament->setValidatedAt(new DateTimeImmutable($data['validatedAt'], new DateTimeZone('UTC')));
        }

        if (isset($data['federalState'])) {
            $fs = $manager->getRepository(FederalState::class)->findOneBy([
                'name' => $data['federalState']
            ]);
            $parliament->setFederalState($fs);
        }

        if ($creator) {
            $parliament->setUpdatedBy($creator);
        }

        return $parliament;
    }

    protected function createFaction(array $data, ?User $creator = null): Faction
    {
        $faction = new Faction();

        if (isset($data['name'])) {
            $faction->setName($data['name']);
        }
        if (isset($data['memberCount'])) {
            $faction->setMemberCount($data['memberCount']);
        }
        if (isset($data['url'])) {
            $faction->setUrl($data['url']);
        }
        if (isset($data['active'])) {
            $faction->setActive($data['active']);
        }

        if ($creator) {
            $faction->setUpdatedBy($creator);
        }

        return $faction;
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
