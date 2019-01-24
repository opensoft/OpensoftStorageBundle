<?php

namespace Opensoft\StorageBundle\Command;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManager;
use Opensoft\StorageBundle\Entity\Storage;
use Opensoft\StorageBundle\Entity\StorageFile;
use Opensoft\StorageBundle\Entity\StorageMoveException;
use Opensoft\StorageBundle\Storage\StorageFileTypeProviderInterface;
use Opensoft\StorageBundle\Storage\StorageManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
class MoveStorageFileCommand extends Command
{
    /**
     * @var ManagerRegistry
     */
    private $doctrine;

    /**
     * @var StorageFileTypeProviderInterface
     */
    private $storageFileTypeProvider;

    /**
     * @var StorageManagerInterface
     */
    private $storageManager;

    /**
     * @param ManagerRegistry $doctrine
     * @param StorageFileTypeProviderInterface $storageFileTypeProvider
     */
    public function __construct(ManagerRegistry $doctrine, StorageFileTypeProviderInterface $storageFileTypeProvider, StorageManagerInterface $storageManager)
    {
        parent::__construct();
        $this->doctrine = $doctrine;
        $this->storageFileTypeProvider = $storageFileTypeProvider;
        $this->storageManager = $storageManager;
    }

    public function configure()
    {
        $this->setName('storage:move-file');
        $this->setDescription('Move a file between storages within the storage system.');
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
        $io = new SymfonyStyle($input, $output);
        $storageFileId = $input->getArgument('storageFileId');
        $destinationStorageId = $input->getArgument('destinationStorageId');

        $storageFileRepository = $this->doctrine->getRepository(StorageFile::class);
        $storageRepository = $this->doctrine->getRepository(Storage::class);
        $storageFileTypes = $this->storageFileTypeProvider->getTypes();

        /** @var StorageFile $storageFile */
        $storageFile = $storageFileRepository->find($storageFileId);
        if (!$storageFile) {
            $io->error(sprintf("Cannot find storageFile '%d'", $storageFileId));

            return -1;
        }
        /** @var Storage $destinationStorage */
        $destinationStorage = $storageRepository->find($destinationStorageId);
        if (!$destinationStorage) {
            $io->error(sprintf("Cannot find destination storage '%d'", $destinationStorageId));

            return -1;
        }

        if ($storageFile->getStorage() === $destinationStorage) {
            $io->warning(sprintf("Storage file '%d' already in destination storage '%d'.  Skipping...", $storageFileId, $destinationStorageId));

            return -1;
        }

        $fromStorage = $storageFile->getStorage();

        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();
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
                $io->warning(sprintf("Storage File '%d' already being moved.  Skipping...", $storageFileId));

                return 0;
            }

            $storageFile = $this->storageManager->move($storageFile, $destinationStorage);

            $em->persist($storageFile);
            $em->flush();
            $em->getConnection()->commit();

            $output->writeln(sprintf("Done moving file %d", $storageFile->getId()));
        } catch (\Exception $e) {
            $em->getConnection()->rollBack();
            $io->error($e);

            // This usually happens in the $storageManager->moveStorageFile function, but we've suspended transactions, execute it here.
            $moveException = new StorageMoveException($storageFile, $fromStorage, $destinationStorage, $e);
            $em->persist($moveException);
            $em->flush();

            return -1;
        }
    }
}
