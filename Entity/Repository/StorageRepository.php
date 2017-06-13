<?php
/**
 * This file is part of ONP.
 *
 * Copyright (c) Opensoft (http://opensoftdev.com)
 *
 * The unauthorized use of this code outside the boundaries of
 * Opensoft is prohibited.
 */

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
