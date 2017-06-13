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

use Gaufrette\Filesystem;
use Opensoft\StorageBundle\Entity\Storage;
use Opensoft\StorageBundle\Entity\StorageFile;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Provides an interface for stored files in the application.
 *
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
interface StorageManagerInterface
{
    /**
     * @param Storage $storage
     * @return Filesystem
     */
    public function getFilesystemForStorage(Storage $storage);

    /**
     * Store a file from an upload into the storage engine.
     *
     * It is the callers responsibility to associate the returned entity with a relation and save the relation to the database.  The
     * storage file itself will be independently stored by the storage manager.
     *
     * @param integer $type
     * @param UploadedFile $uploadedFile
     * @param string|null $newFilename
     * @param bool $unlinkAfterStore
     * @return StorageFile
     */
    public function storeUploadedFile($type, UploadedFile $uploadedFile, $newFilename = null, $unlinkAfterStore = false);

    /**
     * Store a file from a local path into the storage engine.
     *
     * It is the callers responsibility to associate the returned entity with a relation and save the relation to the database.  The
     * storage file itself will be independently stored by the storage manager.
     *
     * @param integer $type
     * @param string $path
     * @param string|null $newFilename
     * @param bool $unlinkAfterStore
     * @return StorageFile
     */
    public function storeFileFromLocalPath($type, $path, $newFilename = null, $unlinkAfterStore = false);

    /**
     * Moves a storage file from it's current storage location to a new one.  The storage manager will correctly update
     * the database with the storage reference change.
     *
     * @param StorageFile $file
     * @param Storage $toStorage
     * @return StorageFile
     */
    public function moveStorageFile(StorageFile $file, Storage $toStorage);

    /**
     * Copy a stored file's content into a scratch location on the server's system temp directory (usually /tmp) and return
     * the file location to the caller.
     *
     * After the caller is done with this file, it should delete it locally.
     *
     * @param StorageFile $file
     * @return string
     */
    public function copyStorageFileToScratch(StorageFile $file);

    /**
     * Retrieve a URL to this resource
     *
     * @param StorageFile $file
     * @param string $referenceType
     * @return string
     */
    public function retrieveUrl(StorageFile $file, $referenceType = StorageUrlResolverInterface::ABSOLUTE_URL);

    /**
     * @param StorageFile $file
     * @return resource
     */
    public function retrieveContext(StorageFile $file);
}
