<?php
/**
 * This file is part of ONP.
 *
 * Copyright (c) Opensoft (http://opensoftdev.com)
 *
 * The unauthorized use of this code outside the boundaries of
 * Opensoft is prohibited.
 */

namespace Opensoft\StorageBundle\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 *
 * @ORM\Entity(repositoryClass="Opensoft\StorageBundle\Entity\Repository\StorageMoveExceptionRepository")
 * @ORM\Table(name="storage_move_exception")
 */
class StorageMoveException
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var StorageFile
     *
     * @ORM\ManyToOne(targetEntity="Opensoft\StorageBundle\Entity\StorageFile", inversedBy="moveExceptions")
     * @ORM\JoinColumn(name="storage_file_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     */
    private $storageFile;

    /**
     * @var Storage
     *
     * @ORM\ManyToOne(targetEntity="Opensoft\StorageBundle\Entity\Storage")
     * @ORM\JoinColumn(name="from_storage_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     */
    private $fromStorage;

    /**
     * @var Storage
     *
     * @ORM\ManyToOne(targetEntity="Opensoft\StorageBundle\Entity\Storage")
     * @ORM\JoinColumn(name="to_storage_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     */
    private $toStorage;

    /**
     * @var \DateTime
     *
     * @ORM\Column(type="datetime", name="created_at")
     */
    private $createdAt;

    /**
     * @var string
     *
     * @ORM\Column(type="string", name="exception_string")
     */
    private $exceptionString;

    /**
     * @var string
     *
     * @ORM\Column(type="text", name="exception_backtrace")
     */
    private $exceptionBacktrace;

    /**
     * @param StorageFile $file
     * @param Storage $fromStorage
     * @param Storage $toStorage
     * @param \Exception $e
     */
    public function __construct(StorageFile $file, Storage $fromStorage, Storage $toStorage, \Exception $e)
    {
        $this->storageFile = $file;
        $this->fromStorage = $fromStorage;
        $this->toStorage = $toStorage;
        $this->createdAt = new DateTime();
        $this->exceptionString = $e->getMessage();
        $this->exceptionBacktrace = $e->getTraceAsString();
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return StorageFile
     */
    public function getStorageFile(): StorageFile
    {
        return $this->storageFile;
    }

    /**
     * @return Storage
     */
    public function getFromStorage(): Storage
    {
        return $this->fromStorage;
    }

    /**
     * @return Storage
     */
    public function getToStorage(): Storage
    {
        return $this->toStorage;
    }

    /**
     * @return \DateTime
     */
    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    /**
     * @return string
     */
    public function getExceptionString(): string
    {
        return $this->exceptionString;
    }

    /**
     * @return string
     */
    public function getExceptionBacktrace(): string
    {
        return $this->exceptionBacktrace;
    }
}
