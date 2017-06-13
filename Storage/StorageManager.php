<?php
/**
 * This file is part of ONP.
 *
 * Copyright (c) 2014 Opensoft (http://opensoftdev.com)
 *
 * The unauthorized use of this code outside the boundaries of
 * Opensoft is prohibited.
 */

namespace Opensoft\StorageBundle\Storage;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManager;
use Gaufrette\Filesystem;
use Gaufrette\StreamWrapper;
use Opensoft\StorageBundle\Entity\Storage;
use Opensoft\StorageBundle\Entity\StorageFile;
use Opensoft\StorageBundle\Entity\StoragePolicy;
use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesser;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * A service for dealing with storages.
 *
 * Stored files are considered immutable.  Their contents cannot be changed once written.  If you need to alter a file
 * in the stored file system, you must write a new one and delete the old one.
 *
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
class StorageManager implements StorageManagerInterface
{
    /**
     * @var GaufretteAdapterResolver
     */
    private $gaufretteAdapterResolver;

    /**
     * @var StorageUrlResolverInterface
     */
    private $storageUrlResolver;

    /**
     * @var ManagerRegistry
     */
    private $doctrine;

    /**
     * @var StorageKeyGeneratorInterface
     */
    private $storageKeyGenerator;

    /**
     * @param ManagerRegistry $doctrine
     * @param GaufretteAdapterResolver $gaufretteAdapterResolver
     * @param StorageUrlResolverInterface $storageUrlResolver
     * @param StorageKeyGeneratorInterface $storageKeyGenerator
     */
    public function __construct(
        ManagerRegistry $doctrine,
        GaufretteAdapterResolver $gaufretteAdapterResolver,
        StorageUrlResolverInterface $storageUrlResolver,
        StorageKeyGeneratorInterface $storageKeyGenerator
    ) {
        $this->doctrine = $doctrine;
        $this->gaufretteAdapterResolver = $gaufretteAdapterResolver;
        $this->storageUrlResolver = $storageUrlResolver;
        $this->storageKeyGenerator = $storageKeyGenerator;
    }

    /**
     * Take an uploaded file given to Symfony and store it in permanent storage
     *
     * It is the callers responsibility to associate the returned entity with a relation and save it to the database.  The
     * storage file itself will be independently stored by the storage manager.
     *
     * @param integer $type
     * @param UploadedFile $uploadedFile
     * @param string|null $newFilename
     * @param bool $unlinkAfterStore
     * @throws \RuntimeException If writing the file fails
     * @return StorageFile
     */
    public function storeUploadedFile($type, UploadedFile $uploadedFile, $newFilename = null, $unlinkAfterStore = true)
    {
        $path = $uploadedFile->getRealPath();

        $storage = $this->getStorageFromWritePolicy($type);

        if (null === $newFilename) {
            $newFilename = uniqid('gen' . substr(hash('sha256', $path), 0, 4)) . '.' . $uploadedFile->getClientOriginalExtension();
        }

        $mimeType = $uploadedFile->getClientMimeType();

        $file = new StorageFile(
            $this->storageKeyGenerator->generate($newFilename),
            $this->getFilesystemForStorage($storage),
            $storage
        );

        $bytes = $this->streamCopy($path, $this->getIOStream($storage->getSlug(), $file->getKey()));

        if ($bytes == 0) {
            throw new \RuntimeException(
                sprintf(
                    "Unable to copy file to storage '%s' with key '%s' from local path '%s'.  Zero bytes copied.",
                    $storage->getName(),
                    $file->getKey(),
                    $path
                )
            );
        }

        if (!$file->exists()) {
            throw new \RuntimeException(
                sprintf(
                    "Could not stream copy to storage '%s' with key '%s'.  Resultant file does not exist.",
                    $storage->getName(),
                    $file->getKey()
                )
            );
        }

        // Ensure other fields on the file are set properly
        $file->setMimeType($mimeType);
        $file->setContentHash(md5_file($path));
        $file->setSize($bytes);
        $file->setType($type);
        // ContentType is used by the AmazonAWSS3 Adapter
        $file->setFileMetadata(['contentType' => $mimeType]);

        if ($unlinkAfterStore) {
            unlink($path);
        }

        $this->saveToDatabase($file);

        return $file;
    }

    /**
     * Creates and stores a file from a local path already on the system.  The file will be copied to a new location
     * in permanent storage.
     *
     * It is the callers responsibility to associate the returned entity with a relation and save it to the database
     *
     * @param integer $type A type for this file.  See StorageFile::$types for a list
     * @param string $path The local path to the file which is being stored
     * @param string|null $newFilename A new filename for the stored file
     * @param bool $unlinkAfterStore
     * @throws \InvalidArgumentException If the file at $path cannot be found
     * @throws \UnexpectedValueException If no active storages are found
     * @throws \RuntimeException If fail to write to storage
     * @return StorageFile
     */
    public function storeFileFromLocalPath($type, $path, $newFilename = null, $unlinkAfterStore = false)
    {
        if (!file_exists($path)) {
            throw new \InvalidArgumentException(sprintf("Cannot store file from non-existent path at '%s'", $path));
        }

        $storage = $this->getStorageFromWritePolicy($type);

        if (null === $newFilename) {
            $newFilename = uniqid('gen' . substr(hash('sha256', $path), 0, 4));

            $pathParts = pathinfo($path);
            if (isset($pathParts['extension'])) {
                $newFilename .= '.' . $pathParts['extension'];
            }
        }

        $mimeType = MimeTypeGuesser::getInstance()->guess($path);

        $file = new StorageFile(
            $this->storageKeyGenerator->generate($newFilename),
            $this->getFilesystemForStorage($storage),
            $storage
        );

        $bytes = $this->streamCopy($path, $this->getIOStream($storage->getSlug(), $file->getKey()));

        if ($bytes == 0) {
            throw new \RuntimeException(
                sprintf(
                    "Unable to copy file to storage '%s' with key '%s' from local path '%s'.  Zero bytes copied.",
                    $storage->getName(),
                    $file->getKey(),
                    $path
                )
            );
        }

        if (!$file->exists()) {
            throw new \RuntimeException(
                sprintf(
                    "Could not stream copy to storage '%s' with key '%s'.  Resultant file does not exist.",
                    $storage->getName(),
                    $file->getKey()
                )
            );
        }

        // Ensure other fields on the file are set properly
        $file->setMimeType($mimeType);
        $file->setContentHash(md5_file($path));
        $file->setSize($bytes);
        $file->setType($type);
        // ContentType is used by the AmazonAWSS3 Adapter
        $file->setFileMetadata(['contentType' => $mimeType]);

        if ($unlinkAfterStore) {
            unlink($path);
        }

        $this->saveToDatabase($file);

        return $file;
    }

    /**
     *
     *
     * @param StorageFile $file
     * @param bool|int|string $referenceType
     * @return string
     */
    public function retrieveUrl(StorageFile $file, $referenceType = StorageUrlResolverInterface::ABSOLUTE_URL)
    {
        return $this->storageUrlResolver->getUrl($file, $referenceType);
    }

    /**
     * @param StorageFile $file
     * @return resource
     */
    public function retrieveContext(StorageFile $file)
    {
        return $this->storageUrlResolver->getContext($file);
    }

    /**
     * Moves a storage file from it's current storage location to a new one.
     *
     * @param StorageFile $file
     * @param Storage $toStorage
     * @throws \RuntimeException If the move fails
     * @return StorageFile
     */
    public function moveStorageFile(StorageFile $file, Storage $toStorage)
    {
        $fromStorage = $file->getStorage();

        $file->setFilesystem($this->getFilesystemForStorage($toStorage));
        $file->setStorage($toStorage);

        $bytes = $this->streamCopy(
            $this->getIOStream($fromStorage->getSlug(), $file->getKey()),
            $this->getIOStream($toStorage->getSlug(), $file->getKey())
        );

        if ($bytes == 0) {
            throw new \RuntimeException(
                sprintf(
                    "Unable to copy file from storage '%s' with key '%s' to storage '%s' with key '%s'.  Zero bytes copied.",
                    $fromStorage->getName(),
                    $file->getKey(),
                    $toStorage->getName(),
                    $file->getKey()
                )
            );
        }

        if (!$file->exists()) {
            throw new \RuntimeException(
                sprintf(
                    "Could not stream copy file from storage '%s' with key '%s' to storage '%s' with key '%s'.",
                    $fromStorage->getName(),
                    $file->getKey(),
                    $toStorage->getName(),
                    $file->getKey()
                )
            );
        }


        if (!unlink($this->getIOStream($fromStorage->getSlug(), $file->getKey()))) {
            throw new \RuntimeException(
                sprintf(
                    "Could not delete original file from storage '%s' and key '%s' after copy.",
                    $fromStorage->getName(),
                    $file->getKey()
                )
            );
        }

        $this->saveToDatabase($file);

        return $file;
    }

    /**
     * Copy a stored file's content into a scratch location on the server's system temp directory (usually /tmp) and return
     * the file location to the caller.
     *
     * After the caller is done with this file, it should delete it locally.
     *
     * @param StorageFile $file
     * @return string
     */
    public function copyStorageFileToScratch(StorageFile $file)
    {
        $destinationPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $file->getKey();

        return $this->copyStorageFileToLocalPath($file, $destinationPath);
    }

    /**
     * Copy a stored file into any location on the server and return the file location to the caller.
     *
     * @param StorageFile $file
     * @param string $destinationPath
     * @throws \RuntimeException If the copy fails
     * @return string
     */
    public function copyStorageFileToLocalPath(StorageFile $file, $destinationPath)
    {
        if (!is_dir(dirname($destinationPath))) {
            mkdir(dirname($destinationPath), 0777, true);
        }

        $bytes = $this->streamCopy(
            $this->getIOStream($file->getStorage()->getSlug(), $file->getKey()),
            $destinationPath
        );

        if ($bytes == 0) {
            throw new \RuntimeException(
                sprintf(
                    "Unable to copy file from storage '%s' with key '%s' to local path '%s'.  Zero bytes copied.",
                    $file->getStorage()->getName(),
                    $file->getKey(),
                    $destinationPath
                )
            );
        }

        return $destinationPath;
    }

    /**
     * Retrieve the filesystem object for an associated storage
     *
     * @param Storage $storage
     * @return Filesystem
     */
    public function getFilesystemForStorage(Storage $storage)
    {
        static $cachedFilesystems = [];

        if (!isset($cachedFilesystems[$storage->getId()])) {
            $adapter = $this->gaufretteAdapterResolver->getAdapter($storage->getAdapterOptions());
            $filesystem = new Filesystem($adapter);

            $map = StreamWrapper::getFilesystemMap();
            $map->set($storage->getSlug(), $filesystem);

            StreamWrapper::register();

            $cachedFilesystems[$storage->getId()] = $filesystem;
        }

        return $cachedFilesystems[$storage->getId()];
    }

    /**
     * Retrieve a storage for writing a file by type
     *
     * @param integer $type
     * @throws \UnexpectedValueException
     * @return Storage
     */
    private function getStorageFromWritePolicy($type)
    {
        $storagePolicy = $this->doctrine->getRepository(StoragePolicy::class)->findOneByType($type);
        if ($storagePolicy) {
            $storage = $storagePolicy->getCreateInStorage();
            if ($storage) {
                return $storage;
            }
        }

        $storage = $this->doctrine->getRepository(Storage::class)->findOneByActive();
        if ($storage === null) {
            throw new \UnexpectedValueException("No default write storage locations present");
        }

        return $storage;
    }


    /**
     * Retrieve a PHP internal I/O Stream identifier for use with php stream related functions
     *
     * @param string $storageSlug
     * @param string $fileKey
     * @return string
     */
    private function getIOStream($storageSlug, $fileKey)
    {
        return sprintf('gaufrette://%s/%s', $storageSlug, $fileKey);
    }

    /**
     * Performs an efficient stream copy from IO streams
     *
     * @param string $sourceStream
     * @param string $destinationStream
     * @return integer The number of bytes copied
     */
    private function streamCopy($sourceStream, $destinationStream)
    {
        $src = fopen($sourceStream, 'rb');
        $dest = fopen($destinationStream, 'wb');

        // stream copy to avoid a memory hit
        $bytes = stream_copy_to_stream($src, $dest);

        fclose($src);
        fclose($dest);
        unset($src, $dest);

        return $bytes;
    }

    /**
     * @param StorageFile $file
     */
    private function saveToDatabase(StorageFile $file)
    {
        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();
        $em->persist($file);
        $em->flush($file);
    }
}
