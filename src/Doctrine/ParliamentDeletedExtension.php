<?php

declare(strict_types=1);

namespace App\Doctrine;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\ContextAwareQueryCollectionExtensionInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use App\Entity\Parliament;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Security\Core\Security;

class ParliamentDeletedExtension implements ContextAwareQueryCollectionExtensionInterface, QueryItemExtensionInterface
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
        if (Parliament::class !== $resourceClass) {
            // we are not responsible...
            return;
        }

        // if an PM filtered explicitly do nothing, else enforce only
        // non-deleted projects
        if (isset($context['filters']['exists']['deletedAt'])
            && $this->security->isGranted(User::ROLE_PROCESS_MANAGER)
        ) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $queryBuilder->andWhere(sprintf('%s.deletedAt IS NULL', $rootAlias));
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
        if (Parliament::class !== $resourceClass) {
            return;
        }

        // PMs can see deleted projects -> do nothing
        if ($this->security->isGranted(User::ROLE_PROCESS_MANAGER)) {
            return;
        }

        // enforce restriction for all other users
        $rootAlias = $queryBuilder->getRootAliases()[0];
        $queryBuilder->andWhere(sprintf('%s.deletedAt IS NULL', $rootAlias));
    }
}
