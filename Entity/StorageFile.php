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
    const TYPE_ORIGINAL = 1;
    const TYPE_HI_RES = 2;
    const TYPE_LOW_RES = 3;
    const TYPE_THUMBNAIL = 4;
    const TYPE_RECEIPT = 5;
    const TYPE_501C3 = 6;
    const TYPE_TAX_EXEMPT = 7;
    const TYPE_SELLING = 8;
    const TYPE_MAIN_PAGE_BLOCK_TABLET_IMAGE = 10;
    const TYPE_DVS_UPLOAD_IMAGE = 11;
    const TYPE_BANNER_IMAGE = 12;
    const TYPE_CUSTOMER_PICKUP_SIGNATURE = 13;
    const TYPE_HI_RES_PREVIEW = 14;
    const TYPE_HI_RES_CANDIDATE = 15;
    const TYPE_HI_RES_JPG_FULL_SIZE = 16;
    const TYPE_SPOT_UV = 17;
    const TYPE_SPOT_UV_HI_RES = 18;
    const TYPE_SPOT_UV_LOW_RES = 19;
    const TYPE_SPOT_UV_THUMBNAIL = 20;
    const TYPE_SMOCK_ROUNDED = 21;
    const TYPE_SPOT_UV_HI_RES_PREVIEW = 22;
    const TYPE_TEXTURE_ORIGINAL = 23;
    const TYPE_TEXTURE_HI_RES = 24;
    const TYPE_TEXTURE_LOW_RES = 25;
    const TYPE_TEXTURE_THUMBNAIL = 26;
    const TYPE_TEXTURE_HI_RES_PREVIEW = 27;
    const TYPE_PRODUCT_SAMPLE = 28;
    const TYPE_CALENDAR_TEMPLATE_FILE_ORIGINAL = 31;
    const TYPE_CALENDAR_TEMPLATE_FILE_HI_RES = 32;
    const TYPE_CALENDAR_TEMPLATE_FILE_LOW_RES = 33;
    const TYPE_CALENDAR_TEMPLATE_FILE_THUMBNAIL = 34;

    public static $types = [
        self::TYPE_ORIGINAL => 'Original Customer Upload',
        self::TYPE_HI_RES => 'High Resolution for Print',
        self::TYPE_LOW_RES => 'Low Resolution Preview',
        self::TYPE_THUMBNAIL => 'Low Resolution Thumbnail Preview',
        self::TYPE_RECEIPT => 'Receipt PDF',
        self::TYPE_501C3 => 'Section 501c3 Non Profit Form File',
        self::TYPE_TAX_EXEMPT => 'Tax exempt form file',
        self::TYPE_SELLING => 'Upsell Images',
        self::TYPE_MAIN_PAGE_BLOCK_TABLET_IMAGE => 'Mobile/Tablet Image for Main Page Block',
        self::TYPE_DVS_UPLOAD_IMAGE => 'DVS File Upload - Rendered',
        self::TYPE_CUSTOMER_PICKUP_SIGNATURE => 'Customer Pickup Signature',
        self::TYPE_BANNER_IMAGE => 'Image for Banner',
        self::TYPE_HI_RES_PREVIEW => 'Hires Preview Image',
        self::TYPE_HI_RES_CANDIDATE => 'Hires Replacement Candidate Image',
        self::TYPE_HI_RES_JPG_FULL_SIZE => 'Full size hires preview for utils',
        self::TYPE_SPOT_UV => 'SpotUv Image',
        self::TYPE_SPOT_UV_HI_RES => 'Spot UV High Resolution File',
        self::TYPE_SPOT_UV_LOW_RES => 'Spot UV Low Resolution Preview',
        self::TYPE_SPOT_UV_THUMBNAIL => 'Spot UV Thumbnail',
        self::TYPE_SMOCK_ROUNDED => 'A RoundCorners Version of a Smock Template Preview',
        self::TYPE_SPOT_UV_HI_RES_PREVIEW => 'Spot UV High Resolution Preview Image',
        self::TYPE_HI_RES_CANDIDATE => 'Hires Replacement Candidate Image',
        self::TYPE_TEXTURE_ORIGINAL => 'Original Texture File',
        self::TYPE_TEXTURE_HI_RES => 'Texture High Resolution File',
        self::TYPE_TEXTURE_LOW_RES => 'Texture Low Resolution Preview',
        self::TYPE_TEXTURE_THUMBNAIL => 'Texture Thumbnail',
        self::TYPE_TEXTURE_HI_RES_PREVIEW => 'Texture High Resolution Preview Image',
        self::TYPE_PRODUCT_SAMPLE => 'Sample for product',
        self::TYPE_CALENDAR_TEMPLATE_FILE_ORIGINAL => 'Original Upload for calendar template file',
        self::TYPE_CALENDAR_TEMPLATE_FILE_HI_RES => 'High Resolution for Print for calendar template file',
        self::TYPE_CALENDAR_TEMPLATE_FILE_LOW_RES => 'Low Resolution Preview for calendar template file',
        self::TYPE_CALENDAR_TEMPLATE_FILE_THUMBNAIL => 'Low Resolution Thumbnail Preview for calendar template file',
    ];

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
