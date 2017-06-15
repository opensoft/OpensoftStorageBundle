<?php

namespace Opensoft\StorageBundle\Command;

use Opensoft\StorageBundle\Entity\Storage;
use Opensoft\StorageBundle\Entity\StorageFile;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
class MoveStorageFileCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this->setName('storage:move-file');
        $this->setDescription("Move a file between storages within the storage system.");
        $this->addArgument('storageFileId', InputArgument::REQUIRED, 'Storage File ID');
        $this->addArgument('destinationStorageId', InputArgument::REQUIRED, 'Move file to this storage');
    }

    /**
     * Executes the current command.
     *
     * @param InputInterface  $input  An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     *
     * @return null|int null or 0 if everything went fine, or an error code
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $storageFileId = $input->getArgument('storageFileId');
        $destinationStorageId = $input->getArgument('destinationStorageId');

        $doctrine = $this->getContainer()->get('doctrine');
        $storageFileRepository = $doctrine->getRepository(StorageFile::class);
        $storageRepository = $doctrine->getRepository(Storage::class);
        $storageFileTypes = $this->getContainer()->get('opensoft_storage.storage_type_provider')->getTypes();
        $logger = $this->getContainer()->get('logger');
        $storageManager = $this->getContainer()->get('storage_manager');

        /** @var StorageFile $storageFile */
        $storageFile = $storageFileRepository->find($storageFileId);
        if (!$storageFile) {
            $logger->critical(sprintf("Cannot find storageFile '%d'", $storageFileId));

            return -1;
        }
        /** @var Storage $destinationStorage */
        $destinationStorage = $storageRepository->find($destinationStorageId);
        if (!$destinationStorage) {
            $logger->critical(sprintf("Cannot find destination storage '%d'", $destinationStorageId));

            return -1;
        }

        if ($storageFile->getStorage() == $destinationStorage) {
            $logger->warning(sprintf("Storage file '%d' already in destination storage '%d'.  Skipping...", $storageFileId, $destinationStorageId));

            return -1;
        }

        $em = $doctrine->getManager();
        $em->getConnection()->beginTransaction();

        try {
            $output->writeln(sprintf(
                "Moving file '%d' of type '%s' to storage '%s'...",
                $storageFile->getId(),
                $storageFileTypes[$storageFile->getType()],
                $destinationStorage->getName()
            ));

            // TODO - Locking mechanism is exclusive to postgres...
            $haveLock = $em->getConnection()->fetchColumn(
                "SELECT pg_try_advisory_xact_lock((SELECT 'storage_file'::regclass::oid)::int, ?)",
                [$storageFileId]
            );
            if ($haveLock === false) {
                $em->clear();
                $em->getConnection()->rollBack();
                $logger->warn(sprintf("Storage File '%d' already being moved.  Skipping...", $storageFileId));

                return 0;
            }

            $storageFile = $storageManager->moveStorageFile($storageFile, $destinationStorage);

            $em->persist($storageFile);
            $em->flush();
            $em->clear();
            $em->getConnection()->commit();

            $output->writeln(sprintf("Done moving file %d", $storageFile->getId()));
        } catch (\Exception $e) {
            $em->clear();
            $em->getConnection()->rollBack();
            $logger->critical($e);

            return -1;
        }
    }
}
