<?php

namespace Opensoft\StorageBundle\Command;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query\Expr\Join;
use Opensoft\StorageBundle\Entity\Storage;
use Opensoft\StorageBundle\Entity\StorageFile;
use Opensoft\Onp\Bundle\CoreBundle\Task\Type\CommandTask;
use Opensoft\StorageBundle\Entity\StorageMoveException;
use Opensoft\StorageBundle\Entity\StoragePolicy;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputOption;

/**
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
class PolicyExecuteCommand extends ContainerAwareCommand
{
    public function configure()
    {
        $this->setName('storage:policy-execute');
        $this->setDescription('Execute file storage policy rules for file moves and deletions');
        $this->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Limit the number of messages of each type');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return null|int null or 0 if everything went fine, or an error code
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Storage Policy Executor');

        /** @var StoragePolicy[] $policies */
        $policies = $this->getContainer()->get('doctrine')->getRepository(StoragePolicy::class)->findAllIndexedByType();

        if (empty($policies)) {
            $io->warning('No policies found');

            return 0;
        }

        $storageFileTypeProvider = $this->getContainer()->get('opensoft_storage.storage_type_provider');
        $storageFileTypes = $storageFileTypeProvider->getTypes();

        $limit = $input->getOption('limit');

        $filesProcessed = 0;
        foreach ($policies as $policy) {
            $io->section(sprintf("Queuing move and delete policies for '%s'", $storageFileTypes[$policy->getType()]));

            $moveInterval = $policy->getMoveAfterInterval();
            if ($moveInterval) {
                $filesQueuedForMove = $this->queueMoves($policy->getType(), $moveInterval, $policy->getMoveFromStorage(), $policy->getMoveToStorage(), $limit, $output);

                $io->comment(sprintf(
                    "%d file(s) queued for move from storage '%s' to storage '%s'",
                    $filesQueuedForMove,
                    $policy->getMoveFromStorage()->getName(),
                    $policy->getMoveToStorage()->getName()
                ));
                $filesProcessed += $filesQueuedForMove;
            }

            $deleteInterval = $policy->getDeleteAfterInterval();
            if ($deleteInterval) {
                $filesQueuedForDelete = $this->queueDeletes($policy->getType(), $deleteInterval, $limit, $output);

                $io->comment(sprintf(
                    "%d file(s) queued for deletion",
                    $filesQueuedForDelete
                ));

                $filesProcessed += $filesQueuedForDelete;
            }
        }

        $io->success(sprintf(
            "Queued %d file(s) for storage policy execution",
            $filesProcessed
        ));
    }

    /**
     * @param integer $type
     * @param string $moveInterval
     * @param Storage $fromStorage
     * @param Storage $toStorage
     * @param int|null $limit
     * @param OutputInterface $output
     * @return integer
     */
    private function queueMoves($type, $moveInterval, Storage $fromStorage, Storage $toStorage, $limit, OutputInterface $output)
    {
        /** @var EntityManager $em */
        $em = $this->getContainer()->get('doctrine')->getManager();

        $qb = $em->createQueryBuilder()
            ->select('s')
            ->distinct()
            ->from(StorageFile::class, 's')
            ->leftJoin('s.moveExceptions', 'm', Join::WITH, 'm.fromStorage = :fromStorageId AND m.toStorage = :toStorageId')
            ->andWhere('s.storage = :fromStorageId')
            ->andWhere('s.type = :fileType')
            ->andWhere('s.createdAt < :olderThan')
            ->andWhere('m.id IS NULL')
            ->setParameters([
                'fromStorageId' => $fromStorage->getId(),
                'toStorageId' => $toStorage->getId(),
                'fileType' => $type,
                'olderThan' => new \DateTime($moveInterval . ' ago')
            ])
        ;

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        $query = $qb->select('s.id')->getQuery();

        $queued = 0;
        foreach ($query->iterate(null, AbstractQuery::HYDRATE_SCALAR) as $row) {
            $storageFile = $row[0];

            $queued += $this->executeMove($storageFile['id'], $toStorage, $output);
        }

        return $queued;
    }

    /**
     * @param int $type
     * @param string $deleteInterval
     * @param int|null $limit
     * @param OutputInterface $output
     * @return int
     */
    private function queueDeletes($type, $deleteInterval, $limit, OutputInterface $output)
    {
        /** @var EntityManager $em */
        $em = $this->getContainer()->get('doctrine')->getManager();

        $qb = $em->createQueryBuilder()
            ->select('s')
            ->from(StorageFile::class, 's')
            ->andWhere('s.createdAt < :olderThan')
            ->andWhere('s.type = :fileType')
            ->setParameters([
                'olderThan' => new \DateTime($deleteInterval . ' ago'),
                'fileType' => $type
            ])
        ;

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        $query = $qb->select('s.id')->getQuery();

        $queued = 0;
        foreach ($query->iterate(null, AbstractQuery::HYDRATE_SCALAR) as $row) {
            $storageFile = $row[0];

            $queued += $this->executeDelete($storageFile['id'], $output);
        }

        return $queued;
    }

    /**
     * @param int $storageFileId
     * @param OutputInterface $output
     * @return int The number of storage files deleted
     */
    private function executeDelete($storageFileId, OutputInterface $output)
    {
        if ($this->getContainer()->has('task_manager')) {
            // Queue the delete command if a task manager exists
            $taskManager = $this->getContainer()->get('task_manager');

            $task = new CommandTask();
            $task->command('storage:delete-file');
            $task->arguments(sprintf('%d', $storageFileId));
            $taskManager->queueTask($task);

            if ($taskManager->hasQueuedTask($task)) {
                $output->writeln(sprintf('Delete already queued for storage file %s... skipping', $storageFileId));

                return 0;
            }

            return 1;
        }

        // Execute the delete command directly
        $command = $this->getApplication()->find('storage:delete-file');

        $arguments = [
            'command' => 'storage:delete-file',
            'storageFileId' => $storageFileId
        ];

        $command->run(new ArrayInput($arguments), $output);

        return 1;
    }

    /**
     * @param int $storageFileId
     * @param Storage $toStorage
     * @param OutputInterface $output
     * @return int The number of storage files that were moved
     */
    private function executeMove($storageFileId, Storage $toStorage, OutputInterface $output)
    {
        if ($this->getContainer()->has('task_manager')) {
            // Queue the move command if a task manager exists
            $taskManager = $this->getContainer()->get('task_manager');

            $task = new CommandTask();
            $task->command('storage:move-file');
            $task->arguments(sprintf('%d %d', $storageFileId, $toStorage->getId()));
            $task->timeout(300)->idleTimeout(300);

            if ($taskManager->hasQueuedTask($task)) {
                $output->writeln(sprintf('Move already queued for storage file %s... skipping', $storageFileId));

                return 0;
            }
            $taskManager->queueTask($task);

            return 1;
        }

        // Execute the move command directly
        $command = $this->getApplication()->find('storage:move-file');

        $arguments = [
            'command' => 'storage:move-file',
            'storageFileId' => $storageFileId,
            'destinationStorageId' => $toStorage->getId()
        ];

        $command->run(new ArrayInput($arguments), $output);

        return 1;
    }
}
