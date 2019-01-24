<?php

namespace Opensoft\StorageBundle\Entity;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * StorageFile - an entity representing a stored file in our storage system.
 *
 * The contents of a StorageFile are immutable.  If you need to alter the contents of a file, create a new entity and delete the old one.
 *
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 *
 * @ORM\Entity(repositoryClass="Opensoft\StorageBundle\Entity\Repository\StorageFileRepository")
 * @ORM\Table(name="storage_file", uniqueConstraints={@ORM\UniqueConstraint(name="unique_storage_file", columns={"storage_key"})})
 */
class StorageFile
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
     * @var Storage
     *
     * @ORM\ManyToOne(targetEntity="Opensoft\StorageBundle\Entity\Storage", fetch="EAGER", inversedBy="files")
     */
    private $storage;

    /**
     * @var string
     *
     * @ORM\Column(type="string", name="storage_key")
     */
    private $key;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="type")
     */
    private $type;

    /**
     * @var string
     *
     * @ORM\Column(type="string", name="mime_type")
     */
    private $mimeType;

    /**
     * @var DateTime
     *
     * @ORM\Column(type="datetime", name="created_at")
     */
    private $createdAt;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="size_in_bytes")
     */
    private $size;

    /**
     * @var string
     *
     * @ORM\Column(type="string", name="content_hash")
     */
    private $contentHash;

    /**
     * @var StorageMoveException[]
     *
     * @ORM\OneToMany(targetEntity="Opensoft\StorageBundle\Entity\StorageMoveException", mappedBy="storageFile")
     * @ORM\OrderBy({"createdAt" = "DESC"})
     */
    private $moveExceptions;

    /**
     * @param string $key
     * @param Storage $storage
     */
    public function __construct(string $key, Storage $storage)
    {
        $this->key = $key;
        $this->storage = $storage;
        $this->createdAt = new DateTime();
        $this->moveExceptions = new ArrayCollection();
    }

    /**
     * @param Storage $storage
     */
    public function setStorage(Storage $storage): void
    {
        $this->storage = $storage;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getContentHash(): string
    {
        return $this->contentHash;
    }

    /**
     * @param int $size
     */
    public function setSize(int $size): void
    {
        $this->size = $size;
    }

    /**
     * @return int
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @param string $hash
     */
    public function setContentHash(string $hash): void
    {
        $this->contentHash = $hash;
    }

    /**
     * @return \DateTime
     */
    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    /**
     * @return Storage
     */
    public function getStorage(): Storage
    {
        return $this->storage;
    }

    /**
     * @return string
     */
    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    /**
     * @param string $mimeType
     */
    public function setMimeType(string $mimeType): void
    {
        $this->mimeType = $mimeType;
    }

    /**
     * @return bool
     */
    public function isLocal(): bool
    {
        return $this->storage->isLocal();
    }

    /**
     * @return string
     */
    public function getLocalPath(): string
    {
        return $this->storage->getLocalPath() . DIRECTORY_SEPARATOR . $this->key;
    }

    /**
     * @return int
     */
    public function getType(): int
    {
        return $this->type;
    }

    /**
     * @param int $type
     */
    public function setType(int $type): void
    {
        $this->type = $type;
    }

    /**
     * @return ArrayCollection|StorageMoveException[]
     */
    public function getMoveExceptions()
    {
        return $this->moveExceptions;
    }

    /**
     * @param int $precision
     * @return string
     */
    public function generateHumanReadableSize(int $precision = 2): string
    {
        $base = log($this->getSize(), 1024);
        $suffixes = array('', 'k', 'M', 'G', 'T');

        return round(1024 ** ($base - floor($base)), $precision) . $suffixes[floor($base)];
    }
}
