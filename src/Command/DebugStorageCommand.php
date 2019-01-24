<?php

namespace Opensoft\StorageBundle\Command;

use Doctrine\Common\Persistence\ManagerRegistry;
use Opensoft\StorageBundle\Entity\Storage;
use Opensoft\StorageBundle\Entity\StorageFile;
use Opensoft\StorageBundle\Storage\StorageFileTypeProviderInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
class DebugStorageCommand extends Command
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
     * @param ManagerRegistry $doctrine
     * @param StorageFileTypeProviderInterface $storageFileTypeProvider
     */
    public function __construct(ManagerRegistry $doctrine, StorageFileTypeProviderInterface $storageFileTypeProvider)
    {
        parent::__construct();
        $this->doctrine = $doctrine;
        $this->storageFileTypeProvider = $storageFileTypeProvider;
    }

    public function configure()
    {
        $this->setName('debug:storage');
        $this->setAliases(['storage:debug']);
        $this->setDescription('Display storage system and information');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return null|int     null or 0 if everything went fine, or an error code
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $storageRepository = $this->doctrine->getRepository(Storage::class);
        $storageFileRepository = $this->doctrine->getRepository(StorageFile::class);
        /** @var Storage[] $storages */
        $storages = $storageRepository->findAll();

        $storageFileTypes = $this->storageFileTypeProvider->getTypes();
        $output->writeln(sprintf('Showing <comment>%d</comment> known file types:', count($storageFileTypes)));
        foreach ($storageFileTypes as $key => $type) {
            $output->writeln(sprintf('  <comment>[%d]</comment> %s', $key, $type));
        }

        $output->writeln(sprintf("\nShowing <comment>%d</comment> configured storage locations:", count($storages)));
        foreach ($storages as $storage) {
            $stats = $storageFileRepository->statsByStorage($storage);

            $output->writeln(sprintf('  <comment>[%d]</comment> %s', $storage->getId(), $storage->getName()));
            $output->writeln(sprintf('     internal identifier - %s', $storage->getSlug()));
            $output->writeln(sprintf('     default write policy? - %s', $storage->isActive() ? '<info>Yes</info>' : '<error>No</error>'));
            $output->writeln(sprintf('     created - %s', $storage->getCreatedAt()->format(DATE_ATOM)));
            $output->writeln(sprintf('     files - %d', $stats['file_count']));
            $output->writeln(sprintf('     stored bytes - %d', $stats['file_size']));
            $output->writeln(sprintf('     %s', $storage->getAdapterType()));

            foreach ($storage->getAdapterOptions() as $key => $value) {
                $output->writeln(sprintf('       %s = %s', $key, $value));
            }
        }
    }
}
