<?php
/**
 * This file is part of ONP.
 *
 * Copyright (c) 2014 Opensoft (http://opensoftdev.com)
 *
 * The unauthorized use of this code outside the boundaries of
 * Opensoft is prohibited.
 */

namespace Opensoft\StorageBundle\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Gaufrette\Exception\FileNotFound;
use Opensoft\StorageBundle\Entity\StorageFile;
use Opensoft\StorageBundle\Storage\StorageManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Deletes files for the underlying storage if their database object is deleted
 *
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
class StorageListener implements EventSubscriber
{
    /**
     * @var StorageManagerInterface
     */
    private $storageManager;

    /**
     * @var StorageFile[]
     */
    private $storageFilesToDelete = [];

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param StorageManagerInterface $storageManager
     * @param LoggerInterface $logger
     */
    public function __construct(StorageManagerInterface $storageManager, LoggerInterface $logger)
    {
        $this->storageManager = $storageManager;
        $this->logger = $logger;
    }

    /**
     * Set up the filesystem for the gaufrette file.
     *
     * @param LifecycleEventArgs $eventArgs
     */
    public function postLoad(LifecycleEventArgs $eventArgs)
    {
        $entity = $eventArgs->getEntity();
        if ($entity instanceof StorageFile) {
            $entity->setFilesystem($this->storageManager->getFilesystemForStorage($entity->getStorage()));
        }
    }

    /**
     * When a storage file is marked for removal, we need to make sure that the filesystem is properly loaded on the
     * object.  Usually, this is already done by the "postLoad" event, but in cases of cascaded removals, we need to
     * ensure it's set again.  This will trigger a proxy load, which is fine.
     *
     * @param LifecycleEventArgs $eventArgs
     */
    public function preRemove(LifecycleEventArgs $eventArgs)
    {
        $entity = $eventArgs->getEntity();
        if ($entity instanceof StorageFile) {
            $this->logger->debug('Storage Entity found as a candidate for deletion, setting its filesystem');
            $entity->setFilesystem($this->storageManager->getFilesystemForStorage($entity->getStorage()));
        }
    }

    /**
     * Remove is called after each DELETE statement inside the transaction is sent to the DB
     *
     * @param LifecycleEventArgs $eventArgs
     */
    public function postRemove(LifecycleEventArgs $eventArgs)
    {
        $entity = $eventArgs->getEntity();
        if ($entity instanceof StorageFile) {
            $this->logger->debug('Queueing storage file for deletion');
            $this->storageFilesToDelete[] = $entity;
        }
    }

    /**
     * Post flush is called after the transaction is successfully committed to the database.
     *
     * Delete any removed storage files
     *
     * @param PostFlushEventArgs $eventArgs
     */
    public function postFlush(PostFlushEventArgs $eventArgs)
    {
        if (!empty($this->storageFilesToDelete)) {
            foreach ($this->storageFilesToDelete as $storageFileToDelete) {
                $this->deleteEntityFromStorage($storageFileToDelete);
            }

            // Reset the list of storage files
            $this->storageFilesToDelete = [];
        }
    }

    /**
     * @param StorageFile $file
     */
    private function deleteEntityFromStorage(StorageFile $file)
    {
        try {
            $file->delete();
        } catch (FileNotFound $e) {
            // file doesn't exist
            $this->logger->warning(sprintf(
                "Could not delete storage file '%s' with key '%s' from storage layer '%s':  The file does not exist",
                $file->getId(),
                $file->getKey(),
                $file->getStorage()->getName()
            ));
        } catch (\Exception $e) {
            $this->logger->critical(sprintf(
                "Failed to delete storage file '%s' with key '%s' from the storage layer '%s': %s",
                $file->getId(),
                $file->getKey(),
                $file->getStorage()->getName(),
                $e->getMessage()
            ));
            $this->logger->critical($e);
        }
    }

    /**
     * Returns an array of events this subscriber wants to listen to.
     *
     * @return array
     */
    public function getSubscribedEvents()
    {
        return [
            'postLoad',
            'postFlush',
            'preRemove',
            'postRemove'
        ];
    }
}
