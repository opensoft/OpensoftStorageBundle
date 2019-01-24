<?php

namespace Opensoft\StorageBundle\Entity\Repository;

use Doctrine\ORM\EntityRepository;
use Opensoft\StorageBundle\Entity\Storage;

/**
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
class StorageRepository extends EntityRepository
{
    /**
     * @return Storage|null
     */
    public function findOneByActive()
    {
        return $this->findOneBy(['active' => true]);
    }
}
