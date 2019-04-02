<?php

namespace Opensoft\StorageBundle\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Proxy\Proxy;
use League\Flysystem\FileNotFoundException;
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
     * Ensure removed entities have been properly proxy loaded so that deleteEntityFromStorage works
     *
     * @param LifecycleEventArgs $eventArgs
     */
    public function preRemove(LifecycleEventArgs $eventArgs): void
    {
        $entity = $eventArgs->getEntity();
        if ($entity instanceof StorageFile) {
            // doctrine <= 3 (doctrine switches to ocramius/proxy-manager in version 3)
            if ($entity instanceof Proxy) {
                // manually trigger a proxy load by asking for one of the class members
                $entity->getStorage();
            }
        }
    }

    /**
     * Remove is called after each DELETE statement inside the transaction is sent to the DB
     *
     * @param LifecycleEventArgs $eventArgs
     */
    public function postRemove(LifecycleEventArgs $eventArgs): void
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
    public function postFlush(PostFlushEventArgs $eventArgs): void
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
    private function deleteEntityFromStorage(StorageFile $file): void
    {
        try {
            $fs = $this->storageManager->filesystem($file->getStorage());
            $success = $fs->delete($file->getKey());

            if (!$success) {
                throw new \RuntimeException('Delete returned false');
            }
        } catch (FileNotFoundException $e) {
            // file doesn't exist
            $this->logger->warning(sprintf(
                "Could not delete storage file with key '%s' from storage layer '%s':  The file does not exist",
                $file->getKey(),
                $file->getStorage()->getName()
            ));
        } catch (\Exception $e) {
            $this->logger->critical(sprintf("Failed to delete storage file: %s", $e->getMessage()));
            $this->logger->critical($e);
        } catch (\Throwable $e) {
            $this->logger->critical(sprintf("Failed to delete storage file: %s", $e->getMessage()));
            $this->logger->critical($e);
        }
    }

    /**
     * Returns an array of events this subscriber wants to listen to.
     *
     * @return string[]
     */
    public function getSubscribedEvents(): array
    {
        return [
            'preRemove',
            'postRemove',
            'postFlush'
        ];
    }
}
