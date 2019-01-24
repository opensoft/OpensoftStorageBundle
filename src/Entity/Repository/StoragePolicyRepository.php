<?php

namespace Opensoft\StorageBundle\Entity\Repository;

use Doctrine\ORM\EntityRepository;
use Opensoft\StorageBundle\Entity\StoragePolicy;

/**
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
class StoragePolicyRepository extends EntityRepository
{
    /**
     * @param int $type
     * @return StoragePolicy|null
     */
    public function findOneByType(int $type): ?StoragePolicy
    {
        return $this->findOneBy(['type' => $type]);
    }

    /**
     * @return StoragePolicy[]
     */
    public function findAllIndexedByType()
    {
        /** @var StoragePolicy[] $results */
        $results = $this->findAll();

        $policies = [];
        foreach ($results as $policy) {
            $policies[$policy->getType()] = $policy;
        }

        return $policies;
    }
}
