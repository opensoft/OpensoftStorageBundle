<?php

namespace Opensoft\StorageBundle\Check;

use Doctrine\Common\Persistence\ManagerRegistry;
use Opensoft\StorageBundle\Entity\Storage;
use Opensoft\StorageBundle\Storage\Adapter\UsageAwareInterface;
use Opensoft\StorageBundle\Storage\AdapterResolver;
use Opensoft\StorageBundle\Storage\StorageManagerInterface;
use ZendDiagnostics\Check\CheckInterface;
use ZendDiagnostics\Result\Failure;
use ZendDiagnostics\Result\ResultInterface;
use ZendDiagnostics\Result\Success;
use ZendDiagnostics\Result\Warning;

/**
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
class LocalStorageCheck implements CheckInterface
{
    /**
     * @var ManagerRegistry
     */
    private $doctrine;

    /**
     * @var StorageManagerInterface
     */
    private $storageManager;

    /**
     * @var AdapterResolver
     */
    private $adapterResolver;

    /**
     * @param ManagerRegistry $doctrine
     * @param StorageManagerInterface $storageManager
     * @param AdapterResolver $adapterResolver
     */
    public function __construct(ManagerRegistry $doctrine, StorageManagerInterface $storageManager, AdapterResolver $adapterResolver)
    {
        $this->doctrine = $doctrine;
        $this->storageManager = $storageManager;
        $this->adapterResolver = $adapterResolver;
    }

    /**
     * Perform the actual check and return a ResultInterface
     *
     * @return ResultInterface
     */
    public function check()
    {
        $storages = $this->doctrine->getRepository(Storage::class)->findBy([], ['createdAt' => 'asc']);

        if (empty($storages)) {
            return new Failure("No storage locations found in the storage engine.");
        }

        foreach ($storages as $storage) {
            $adapter = $this->adapterResolver->getConfigurationByClass($storage->getAdapterOptions()['class']);

            // Test adapters that know their usage to make sure there is enough space for new storage items
            if ($adapter instanceof UsageAwareInterface) {
                $usage = $adapter->usage($storage);

                $percentage = sprintf("%2.2f", $usage['usagepct']);
                $usageSize = sprintf("%4.2fGB", $usage['usagesize']);

                if ($percentage >= 89) {
                    return new Warning(sprintf(
                        "Storage '%s' (%s total size) is more than 89%% full.",
                        $storage->getName(),
                        $usageSize
                    ));
                } elseif ($percentage >= 94) {
                    return new Failure(sprintf(
                        "Storage '%s' (%s total size) is more than 94%% full.  Please adjust storage policies immediately.",
                        $storage->getName(),
                        $usageSize
                    ));
                }
            }

            // Ensure that each adapter is writeable.
            $fs = $this->storageManager->filesystem($storage);
            $filename = sprintf('%s.bak', uniqid('test_tmp_', false));

            try {
                $written = $fs->write($filename, 'content');
                if ($written === false) {
                    return new Failure(sprintf(
                        "Unable to write a temporary test file to storage '%s'.  Zero bytes written.  Please confirm storage configuration.",
                        $storage->getName()
                    ));
                }
                $deleted = $fs->delete($filename);
                if (!$deleted) {
                    return new Failure(sprintf(
                        "Unable to delete temporary test file from storage '%s'.  File name '%s'.  Please confirm storage configuration.",
                        $storage->getName(),
                        $filename
                    ));
                }
            } catch (\Exception $e) {
                return new Failure(sprintf(
                    "Unable to write and delete a temporary test file to storage '%s'. Exception: '%s'.  Please confirm storage configuration.",
                    $storage->getName(),
                    $e->getMessage()
                ));
            }
        }

        return new Success('Storage engine configured storages have enough space and are writeable.');
    }

    /**
     * Return a label describing this test instance.
     *
     * @return string
     */
    public function getLabel()
    {
        return 'Storage Engine - Storages Check';
    }
}
