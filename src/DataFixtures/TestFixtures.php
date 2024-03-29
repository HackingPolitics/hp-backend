<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\ActionLog;
use App\Entity\ActionMandate;
use App\Entity\Argument;
use App\Entity\Category;
use App\Entity\Council;
use App\Entity\CounterArgument;
use App\Entity\FederalState;
use App\Entity\Fraction;
use App\Entity\FractionDetails;
use App\Entity\FractionInterest;
use App\Entity\Negation;
use App\Entity\Partner;
use App\Entity\Problem;
use App\Entity\Project;
use App\Entity\ProjectMembership;
use App\Entity\Proposal;
use App\Entity\UploadedFileTypes\ProposalDocument;
use App\Entity\UsedActionMandate;
use App\Entity\UsedArgument;
use App\Entity\UsedCounterArgument;
use App\Entity\UsedFractionInterest;
use App\Entity\UsedNegation;
use App\Entity\UsedProblem;
use App\Entity\User;
use App\Entity\Validation;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

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
        'goal'        => 'goal with 20 characters',
        'title'       => 'Car-free Dresden',
        'impact'      => 'impact with 10 characters',
        'topic'       => 'topic with 10 characters',
        'state'       => Project::STATE_PUBLIC,
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
        'state'     => Project::STATE_PUBLIC,
    ];

    public const PROPOSAL_1 = [
        'id'            => 1,
        'title'         => 'proposal title',
        'introduction'  => 'proposal <b>introduction</b>',
        'comment'       => 'proposal comment',
        'actionMandate' => '<h3>heading</h3><p>paragraph1</p><ul><li>proposal</li><li>action-mandate</li></ul><p>paragraph2 <b>bold</b></p>',
        'reasoning'     => '<em>proposal reasoning</em><ol><li>reason1</li><li>reason2</li></ol>',
        'sponsor'       => 'interfraktionell',
    ];

    public const PROPOSAL_2 = [
        'id'            => 2,
        'title'         => 'proposal2 title',
        'introduction'  => 'proposal2 introduction',
        'comment'       => 'proposal2 comment',
        'actionMandate' => 'proposal2 action-mandate',
        'reasoning'     => 'proposal2 reasoning',
        'sponsor'       => 'Green',
    ];

    public const COUNCIL = [
        'id'           => 1,
        'title'        => 'Stadtrat Stuttgart',
        'location'     => 'LH Stuttgart',
        'zipArea'      => '123',
        'federalState' => 'Baden-Württemberg',
        'validatedAt'  => '2021-05-01',
        'active'       => true,

        // political/organizational details
        'headOfAdministration'      => 'Max Muster',
        'headOfAdministrationTitle' => 'Oberbürgermeister',
    ];

    public const FRACTION_GREEN = [
        'id'          => 1,
        'name'        => 'Grün',
        'memberCount' => 1,
        'color'       => '00FF00',
    ];
    public const FRACTION_RED = [
        'id'          => 2,
        'name'        => 'Rot',
        'memberCount' => 2,
        'color'       => 'FF0000',
    ];
    public const FRACTION_BLACK = [
        'id'          => 3,
        'name'        => 'Schwarz',
        'memberCount' => 3,
    ];
    public const FRACTION_YELLOW = [
        'id'          => 4,
        'name'        => 'Gelb',
        'memberCount' => 4,
        'active'      => false,
    ];

    public const PARTNER_ONE = [
        'id'          => 1,
        'name'        => 'Partner1',
        'contactName' => 'P1',
        'role'        => 'Umsetzungspartner',
    ];
    public const PARTNER_TWO = [
        'id'          => 2,
        'name'        => 'Partner2',
        'contactName' => 'P2',
        'role'        => 'Wissenschaftliche Unterstützung',
    ];

    private UserPasswordHasherInterface $passwordHasher;

    public function getDependencies(): array
    {
        return [InitialFixtures::class];
    }

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
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

        //region Council + Fractions
        $council = $this->createCouncil(self::COUNCIL, $manager, $admin);
        $council->setUpdatedBy($admin);
        $manager->persist($council);

        $greenFraction = $this->createFraction(self::FRACTION_GREEN, $admin);
        $council->addFraction($greenFraction);
        $redFraction = $this->createFraction(self::FRACTION_RED, $admin);
        $council->addFraction($redFraction);
        $blackFraction = $this->createFraction(self::FRACTION_BLACK, $admin);
        $council->addFraction($blackFraction);
        $yellowFraction = $this->createFraction(self::FRACTION_YELLOW, $admin);
        $council->addFraction($yellowFraction);
        //endregion

        //region Normal project
        $project = $this->createProject(self::PROJECT, $projectCoordinator,
            $projectCoordinator, $projectWriter, $projectObserver);
        $council->addProject($project);
        $manager->persist($project);

        $catRepo = $manager->getRepository(Category::class);
        $cat1 = $catRepo->find(1);
        $project->addCategory($cat1);
        $cat2 = $catRepo->find(2);
        $project->addCategory($cat2);
        $cat3 = $catRepo->find(3);
        $project->addCategory($cat3);

        $detailsGreen = new FractionDetails();
        $detailsGreen->setContactEmail('green@zukunftsstadt.de');
        $detailsGreen->setContactName('Green');
        $detailsGreen->setContactPhone('123');
        $detailsGreen->setPossiblePartner(true);
        $detailsGreen->setPossibleSponsor(true);
        $detailsGreen->setTeamContact($projectObserver);
        $detailsGreen->setUpdatedBy($projectWriter);
        $greenFraction->addDetails($detailsGreen);
        $project->addFractionDetails($detailsGreen);

        $detailsBlack = new FractionDetails();
        $detailsBlack->setContactEmail('black@zukunftsstadt.de');
        $detailsBlack->setContactName('Black');
        $detailsBlack->setContactPhone('321');
        $detailsBlack->setPossiblePartner(true);
        $blackFraction->addDetails($detailsBlack);
        $project->addFractionDetails($detailsBlack);

        $interest1 = new FractionInterest();
        $interest1->setDescription('interest1');
        $interest1->setUpdatedBy($admin);
        $detailsGreen->addInterest($interest1);

        $interest2 = new FractionInterest();
        $interest2->setDescription('interest2');
        $interest2->setUpdatedBy($admin);
        $detailsGreen->addInterest($interest2);

        $partner1 = $this->createPartner(self::PARTNER_ONE);
        $partner1->setUpdatedBy($admin);
        $partner1->setTeamContact($projectObserver);
        $project->addPartner($partner1);

        $partner2 = $this->createPartner(self::PARTNER_TWO);
        $partner2->setUpdatedBy($projectCoordinator);
        $project->addPartner($partner2);

        $actionMandate1 = new ActionMandate();
        $actionMandate1->setDescription('action-mandate 1');
        $actionMandate1->setUpdatedBy($processManager);
        $actionMandate1->setPriority(77);
        $project->addActionMandate($actionMandate1);

        $argument1 = new Argument();
        $argument1->setDescription('argument 1');
        $argument1->setUpdatedBy($processManager);
        $argument1->setPriority(77);
        $project->addArgument($argument1);

        $counterArgument1 = new CounterArgument();
        $counterArgument1->setDescription('counter-argument 1');
        $counterArgument1->setUpdatedBy($processManager);
        $counterArgument1->setPriority(77);
        $project->addCounterArgument($counterArgument1);

        $negation1 = new Negation();
        $negation1->setDescription('negation 1');
        $negation1->setPriority(77);
        $negation1->setUpdatedBy($processManager);
        $counterArgument1->addNegation($negation1);

        $problem1 = new Problem();
        $problem1->setDescription('problem 1');
        $problem1->setUpdatedBy($processManager);
        $problem1->setPriority(77);
        $project->addProblem($problem1);

        $proposal1 = $this->createProposal(self::PROPOSAL_1);
        $proposal1->setUpdatedBy($processManager);
        $project->addProposal($proposal1);

        $usedActionMandate = new UsedActionMandate();
        $usedActionMandate->setCreatedBy($admin);
        $actionMandate1->addUsage($usedActionMandate);
        $proposal1->addUsedActionMandate($usedActionMandate);

        $usedArgument = new UsedArgument();
        $usedArgument->setCreatedBy($admin);
        $argument1->addUsage($usedArgument);
        $proposal1->addUsedArgument($usedArgument);

        $usedCounterArgument = new UsedCounterArgument();
        $usedCounterArgument->setCreatedBy($admin);
        $counterArgument1->addUsage($usedCounterArgument);
        $proposal1->addUsedCounterArgument($usedCounterArgument);

        $usedFractionInterest = new UsedFractionInterest();
        $usedFractionInterest->setCreatedBy($admin);
        $interest1->addUsage($usedFractionInterest);
        $proposal1->addUsedFractionInterest($usedFractionInterest);

        $usedNegation = new UsedNegation();
        $usedNegation->setCreatedBy($admin);
        $negation1->addUsage($usedNegation);
        $proposal1->addUsedNegation($usedNegation);

        $usedProblem = new UsedProblem();
        $usedProblem->setCreatedBy($admin);
        $problem1->addUsage($usedProblem);
        $proposal1->addUsedProblem($usedProblem);

        $document = new ProposalDocument();
        $document->setName('fake-abcd.odt');
        $document->setOriginalName('fake.odt');
        $document->setMimeType('application/vnd.oasis.opendocument.text');
        $document->setSize(999);
        $proposal1->setDocumentFile($document);

        $proposal2 = $this->createProposal(self::PROPOSAL_2);
        $proposal2->setUpdatedBy($admin);
        $project->addProposal($proposal2);
        //endregion

        /**
         * Create locked project.
         */
        $lockedProject = $this->createProject(self::LOCKED_PROJECT,
            $projectCoordinator, $projectCoordinator, $projectWriter);
        $council->addProject($lockedProject);
        $manager->persist($lockedProject);
        /**
         * /Create locked project.
         */

        /**
         * Create deleted project.
         */
        $deletedProject = $this->createProject(self::DELETED_PROJECT,
            $projectWriter);
        $council->addProject($deletedProject);
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
                $this->passwordHasher->hashPassword(
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
        if (isset($data['goal'])) {
            $project->setGoal($data['goal']);
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

    protected function createCouncil(
        array $data,
        ObjectManager $manager,
        ?User $creator = null
    ): Council {
        $council = new Council();

        if (isset($data['title'])) {
            $council->setTitle($data['title']);
        }
        if (isset($data['location'])) {
            $council->setLocation($data['location']);
        }
        if (isset($data['zipArea'])) {
            $council->setZipArea($data['zipArea']);
        }
        if (isset($data['headOfAdministration'])) {
            $council->setHeadOfAdministration($data['headOfAdministration']);
        }
        if (isset($data['headOfAdministrationTitle'])) {
            $council->setHeadOfAdministrationTitle($data['headOfAdministrationTitle']);
        }
        if (isset($data['url'])) {
            $council->setUrl($data['url']);
        }
        if (isset($data['wikipediaUrl'])) {
            $council->setWikipediaUrl($data['wikipediaUrl']);
        }
        if (isset($data['validatedAt'])) {
            $council->setValidatedAt(new DateTimeImmutable($data['validatedAt'], new DateTimeZone('UTC')));
        }

        if (isset($data['federalState'])) {
            $fs = $manager->getRepository(FederalState::class)->findOneBy([
                'name' => $data['federalState'],
            ]);
            $council->setFederalState($fs);
        }

        if ($creator) {
            $council->setUpdatedBy($creator);
        }

        $council->setActive($data['active'] ?? true);

        return $council;
    }

    protected function createFraction(array $data, ?User $creator = null): Fraction
    {
        $fraction = new Fraction();

        if (isset($data['name'])) {
            $fraction->setName($data['name']);
        }
        if (isset($data['memberCount'])) {
            $fraction->setMemberCount($data['memberCount']);
        }
        if (isset($data['url'])) {
            $fraction->setUrl($data['url']);
        }
        if (isset($data['color'])) {
            $fraction->setColor($data['color']);
        }
        if (isset($data['active'])) {
            $fraction->setActive($data['active']);
        }

        if ($creator) {
            $fraction->setUpdatedBy($creator);
        }

        return $fraction;
    }

    protected function createPartner(array $data): Partner
    {
        $partner = new Partner();

        if (isset($data['name'])) {
            $partner->setName($data['name']);
        }
        if (isset($data['contactName'])) {
            $partner->setContactName($data['contactName']);
        }
        if (isset($data['url'])) {
            $partner->setUrl($data['url']);
        }
        if (isset($data['role'])) {
            $partner->setRole($data['role']);
        }

        return $partner;
    }

    protected function createProposal(array $data): Proposal
    {
        $proposal = new Proposal();

        if (isset($data['title'])) {
            $proposal->setTitle($data['title']);
        }
        if (isset($data['introduction'])) {
            $proposal->setIntroduction($data['introduction']);
        }
        if (isset($data['actionMandate'])) {
            $proposal->setActionMandate($data['actionMandate']);
        }
        if (isset($data['reasoning'])) {
            $proposal->setReasoning($data['reasoning']);
        }
        if (isset($data['comment'])) {
            $proposal->setComment($data['comment']);
        }
        if (isset($data['url'])) {
            $proposal->setUrl($data['url']);
        }
        if (isset($data['sponsor'])) {
            $proposal->setSponsor($data['sponsor']);
        }

        return $proposal;
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
