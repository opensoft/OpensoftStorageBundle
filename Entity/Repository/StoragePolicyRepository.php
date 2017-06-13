<?php
/**
 * This file is part of ONP.
 *
 * Copyright (c) 2014 Opensoft (http://opensoftdev.com)
 *
 * The unauthorized use of this code outside the boundaries of
 * Opensoft is prohibited.
 */

namespace Opensoft\StorageBundle\Entity\Repository;

use Doctrine\ORM\EntityRepository;
use Opensoft\StorageBundle\Entity\StoragePolicy;

/**
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
class StoragePolicyRepository extends EntityRepository
{
    /**
     * @param integer $type
     * @return StoragePolicy|null
     */
    public function findOneByType($type)
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
