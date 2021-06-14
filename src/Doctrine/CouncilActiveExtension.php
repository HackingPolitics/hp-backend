<?php

declare(strict_types=1);

namespace App\Doctrine;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\ContextAwareQueryCollectionExtensionInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use App\Entity\Council;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Security\Core\Security;

class CouncilActiveExtension implements ContextAwareQueryCollectionExtensionInterface, QueryItemExtensionInterface
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
        if (Council::class !== $resourceClass) {
            // we are not responsible...
            return;
        }

        // if an Admin filtered explicitly do nothing, else enforce only
        // active councils
        if (isset($context['filters']['active'])
            && $this->security->isGranted(User::ROLE_ADMIN)
        ) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $queryBuilder->andWhere(sprintf('%s.active = :isActive', $rootAlias))
            ->setParameter('isActive', true);
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
        if (Council::class !== $resourceClass) {
            return;
        }

        // PMs can see inactive councils -> do nothing
        if ($this->security->isGranted(User::ROLE_ADMIN)
            || $this->security->isGranted(User::ROLE_PROCESS_MANAGER)
        ) {
            return;
        }

        // enforce restriction for all other users
        $rootAlias = $queryBuilder->getRootAliases()[0];
        $queryBuilder->andWhere(sprintf('%s.active = :isActive', $rootAlias))
            ->setParameter('isActive', true);
    }
}
