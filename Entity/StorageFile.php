<?php
/**
 * This file is part of ONP.
 *
 * Copyright (c) 2014 Opensoft (http://opensoftdev.com)
 *
 * The unauthorized use of this code outside the boundaries of
 * Opensoft is prohibited.
 */

namespace Opensoft\StorageBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gaufrette\File as GaufretteFile;
use Gaufrette\Filesystem;
//use Opensoft\Onp\Bundle\WebBundle\Entity\FileHiresCandidate;

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
class StorageFile extends GaufretteFile
{
    /**
     * @var integer
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
    protected $key;

    /**
     * @var integer
     *
     * @ORM\Column(type="integer", name="type")
     */
    protected $type;

    /**
     * @var string
     *
     * @ORM\Column(type="string", name="mime_type")
     */
    protected $mimeType;

    /**
     * @var \DateTime
     *
     * @ORM\Column(type="datetime", name="created_at")
     */
    private $createdAt;

    /**
     * @var integer
     *
     * @ORM\Column(type="integer", name="size_in_bytes")
     */
    protected $size;

    /**
     * @var string
     *
     * @ORM\Column(type="string", name="content_hash")
     */
    private $contentHash;

//    /**
//     * Note: this association only exists to set up the proper cascade removal behavior for FileHiresCandidate objects
//     *
//     * @var FileHiresCandidate
//     * @noinspection PhpUnusedPrivateFieldInspection
//     *
//     * @ORM\OneToOne(targetEntity="Opensoft\Onp\Bundle\WebBundle\Entity\FileHiresCandidate", mappedBy="storageFile", cascade={"remove"})
//     */
//    private $fileHiresCandidateHiresStorageFile;

    /**
     * Constructor
     *
     * @param string $key
     * @param Filesystem $filesystem
     * @param Storage $storage
     */
    public function __construct($key, Filesystem $filesystem, Storage $storage)
    {
        parent::__construct($key, $filesystem);
        $this->storage = $storage;
        $this->createdAt = new \DateTime();
    }

    /**
     * Do not call this explicitly, it's called by the doctrine StorageListener class when this object
     * is refreshed from the database
     *
     * @param Filesystem $filesystem
     */
    public function setFilesystem(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    /**
     * @param Storage $storage
     */
    public function setStorage(Storage $storage)
    {
        $this->storage = $storage;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getContentHash()
    {
        return $this->contentHash;
    }

    /**
     * @param string $content
     * @param array $metadata optional metadata which should be send when write
     * @return integer The number of bytes that were written into the file, or
     *                 FALSE on failure
     */
    public function setContent($content, $metadata = array())
    {
        $this->contentHash = md5($content);

        return parent::setContent($content, $metadata);
    }

    /**
     * Set the metadata for this object
     *
     * @param array $metadata
     * @return bool
     */
    public function setFileMetadata(array $metadata)
    {
        return $this->setMetadata($metadata);
    }

    /**
     * @param string $hash
     */
    public function setContentHash($hash)
    {
        $this->contentHash = $hash;
    }

    /**
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @return Storage
     */
    public function getStorage()
    {
        return $this->storage;
    }

    /**
     * @return string
     */
    public function getMimeType()
    {
        return $this->mimeType;
    }

    /**
     * @param string $mimeType
     */
    public function setMimeType($mimeType)
    {
        $this->mimeType = $mimeType;
    }

    /**
     * @return bool
     */
    public function isLocal()
    {
        return $this->storage->isLocal();
    }

    /**
     * @return string
     */
    public function getLocalPath()
    {
        return $this->storage->getLocalPath() . DIRECTORY_SEPARATOR . $this->key;
    }

    /**
     * @return integer
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getTypeCode()
    {
        return isset(self::$types[$this->type]) ? self::$types[$this->type] : '';
    }

    /**
     * @param integer $type
     */
    public function setType($type)
    {
        if (!isset(self::$types[$type])) {
            throw new \InvalidArgumentException(sprintf("StorageFile type out of range.  Allowed values:  '%s'", array_keys(self::$types)));
        }

        $this->type = $type;
    }

    /**
     * @param int $precision
     * @return string
     */
    public function generateHumanReadableSize($precision = 2)
    {
        $base = log($this->getSize(), 1024);
        $suffixes = array('', 'k', 'M', 'G', 'T');

        return round(pow(1024, $base - floor($base)), $precision) . $suffixes[floor($base)];
    }
}
