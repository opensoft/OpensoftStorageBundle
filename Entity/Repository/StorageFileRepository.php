<?php

namespace Opensoft\StorageBundle\Entity\Repository;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Opensoft\StorageBundle\Entity\Storage;

/**
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
class StorageFileRepository extends EntityRepository
{
    /**
     * @param Storage $storage
     * @return array
     */
    public function statsByStorage(Storage $storage)
    {
        $result = $this->_em->createQuery('SELECT COUNT(s.id) as file_count, SUM(s.size) as file_size FROM Opensoft\StorageBundle\Entity\StorageFile s WHERE s.storage = :storageId')
            ->setParameter('storageId', $storage->getId())
            ->getResult(AbstractQuery::HYDRATE_SCALAR)
        ;

        return $result[0];
    }

    /**
     * @param Storage $storage
     * @return array
     */
    public function statsByStorageGroupedByType(Storage $storage)
    {
        return $this->_em->createQuery('SELECT COUNT(s.id) as file_count, SUM(s.size) as file_size, s.type FROM Opensoft\StorageBundle\Entity\StorageFile s WHERE s.storage = :storageId GROUP BY s.type')
            ->setParameter('storageId', $storage->getId())
            ->getResult(AbstractQuery::HYDRATE_ARRAY);
    }

    /**
     * @return QueryBuilder
     */
    public function getQueryBuilderForStorage($storageId)
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.storage = :storage')
            ->setParameter('storage', $storageId)
            ->orderBy('s.id', 'DESC');
    }
}
