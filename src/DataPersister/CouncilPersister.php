<?php

declare(strict_types=1);

namespace App\DataPersister;

use ApiPlatform\Core\Bridge\Doctrine\Common\DataPersister;
use ApiPlatform\Core\DataPersister\ContextAwareDataPersisterInterface;
use ApiPlatform\Core\Exception\InvalidResourceException;
use App\Entity\Council;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Handles persisting soft-deletes.
 */
class CouncilPersister implements ContextAwareDataPersisterInterface
{
    protected DataPersister $wrapped;

    public function __construct(ManagerRegistry $managerRegistry)
    {
        $this->wrapped = new DataPersister($managerRegistry);
    }

    /**
     * {@inheritdoc}
     */
    public function supports($data, array $context = []): bool
    {
        return $data instanceof Council;
    }

    /**
     * {@inheritdoc}
     *
     * @param Council $data
     */
    public function persist($data, array $context = [])
    {
        return $this->wrapped->persist($data, $context);
    }

    /**
     * {@inheritdoc}
     *
     * @param Council $data
     *
     * @throws InvalidResourceException when the project is already marked as deleted
     */
    public function remove($data, array $context = [])
    {
        if ($data->isDeleted()) {
            throw new InvalidResourceException('Council already deleted');
        }

        $data->markDeleted();

        $this->wrapped->persist($data, $context);
    }
}
