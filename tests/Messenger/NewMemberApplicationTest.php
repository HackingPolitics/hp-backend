<?php

declare(strict_types=1);

namespace App\Tests\Messenger;

use App\DataFixtures\TestFixtures;
use App\Entity\Project;
use App\Entity\ProjectMembership;
use App\Entity\User;
use App\Message\NewMemberApplicationMessage;
use App\MessageHandler\NewMemberApplicationMessageHandler;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

class NewMemberApplicationTest extends KernelTestCase
{
    use RefreshDatabaseTrait;

    private ?EntityManager $entityManager;

    public static function setUpBeforeClass(): void
    {
        static::$fixtureGroups = ['initial', 'test'];
    }

    protected function setUp(): void
    {
        $kernel = self::bootKernel();

        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager = null;
    }

    public static function tearDownAfterClass(): void
    {
        self::fixtureCleanup();
    }

    public function testHandlerSendsMessage()
    {
        $em = static::getContainer()->get('doctrine')->getManager();

        /** @var User $user */
        $user = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_OBSERVER['id']);
        foreach ($user->getProjectMemberships() as $membership) {
            $em->remove($membership);
        }
        $em->flush();

        /** @var Project $oldProject */
        $oldProject = $em->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);
        $application = new ProjectMembership();
        $application->setMotivation('user motivation');
        $application->setSkills('user skills');
        $application->setRole(ProjectMembership::ROLE_APPLICANT);
        $user->addProjectMembership($application);
        $oldProject->addMembership($application);
        $em->persist($application);
        $em->flush();

        $msg = new NewMemberApplicationMessage(TestFixtures::PROJECT_OBSERVER['id'], TestFixtures::PROJECT['id']);

        /** @var NewMemberApplicationMessageHandler $handler */
        $handler = static::getContainer()->get(NewMemberApplicationMessageHandler::class);
        $handler($msg);

        self::assertEmailCount(1);
    }
}
