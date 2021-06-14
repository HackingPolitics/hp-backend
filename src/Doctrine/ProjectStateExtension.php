<?php

declare(strict_types=1);

namespace App\Doctrine;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\ContextAwareQueryCollectionExtensionInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use App\Entity\Project;
use App\Entity\ProjectMembership;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Security\Core\Security;

class ProjectStateExtension implements ContextAwareQueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    /**
     * @var Security
     */
    private $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    /**
     * For ContextAwareQueryCollectionExtensionInterface.
     *
     * {@inheritdoc}
     */
    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        string $operationName = null,
        array $context = []
    ) {
        if (Project::class !== $resourceClass) {
            // we are not responsible...
            return;
        }

        // if an admin|PM filtered explicitly do nothing, else enforce only
        // public projects
        if (isset($context['filters']['state'])
            && ($this->security->isGranted(User::ROLE_ADMIN)
            || $this->security->isGranted(User::ROLE_PROCESS_MANAGER))
        ) {
            return;
        }

        // logged in users can filter by ID and retrieve private projects,
        // e.g. members filter by the IDs of their projects
        if (isset($context['filters']['id']) &&
            $this->security->isGranted(User::ROLE_USER)
        ) {
            return;
        }

        // in all other cases we only return public projects
        $rootAlias = $queryBuilder->getRootAliases()[0];
        $queryBuilder
            ->andWhere(sprintf('%s.state = :state', $rootAlias))
            ->setParameter('state', Project::STATE_PUBLIC);
    }

    /**
     * For QueryItemExtensionInterface.
     */
    public function applyToItem(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        array $identifiers,
        string $operationName = null,
        array $context = []
    ) {
        if (Project::class !== $resourceClass) {
            return;
        }

        // admins|POs can see private projects -> do nothing
        if ($this->security->isGranted(User::ROLE_ADMIN)
            || $this->security->isGranted(User::ROLE_PROCESS_MANAGER)
        ) {
            return;
        }

        $this->addQueryRestriction($queryBuilder);
    }

    protected function addQueryRestriction(QueryBuilder $queryBuilder)
    {
        $rootAlias = $queryBuilder->getRootAliases()[0];

        // members can see private projects
        if ($this->security->getUser()) {
            if (!in_array('memberships', $queryBuilder->getAllAliases())) {
                $queryBuilder
                    ->leftJoin("$rootAlias.memberships", 'memberships');
            }
            $queryBuilder
                ->andWhere("($rootAlias.state = :publicState OR (".
                    'memberships.role IN (:memberRoles) '.
                    'AND memberships.user = :currentUser'.
                    '))')
                ->setParameter('publicState', Project::STATE_PUBLIC)
                ->setParameter('memberRoles', [
                    ProjectMembership::ROLE_COORDINATOR,
                    ProjectMembership::ROLE_WRITER,
                    ProjectMembership::ROLE_OBSERVER,
                ])
                ->setParameter('currentUser', $this->security->getUser());
        }
        // enforce restriction for all other users
        else {
            $queryBuilder
                ->andWhere(sprintf('%s.state = :state', $rootAlias))
                ->setParameter('state', Project::STATE_PUBLIC);
        }
    }
}
