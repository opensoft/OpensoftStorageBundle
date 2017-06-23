<?php

namespace Opensoft\StorageBundle\Command;

use Opensoft\StorageBundle\Entity\Repository\StorageFileRepository;
use Opensoft\StorageBundle\Entity\StorageFile;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
class DeleteStorageFileCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this->setName('storage:delete-file');
        $this->setDescription("Remove a specific file from the storage system.");
        $this->setHelp('Warning: If the storage file is used by the system and deletion cascade behavior is not defined for this storage file, you will not be able to delete it.');
        $this->addArgument('storageFileId', InputArgument::REQUIRED, 'Storage File ID');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return integer null|int     null or 0 if everything went fine, or an error code
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $storageFileId = $input->getArgument('storageFileId');
        $doctrine = $this->getContainer()->get('doctrine');

        $storageFile = $doctrine->getRepository(StorageFile::class)->find($storageFileId);

        if (!$storageFile) {
            $output->writeln(sprintf("<error>Can not find file with storage id '%d' to delete it</error>", $storageFileId));

            return -1;
        }

        $em = $doctrine->getManager();
        $em->remove($storageFile);
        $em->flush();

        $output->writeln(sprintf("<info>Storage file '%d' deleted</info>", $storageFileId));
    }
}
