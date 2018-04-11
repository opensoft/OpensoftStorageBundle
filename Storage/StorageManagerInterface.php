<?php

namespace Opensoft\StorageBundle\Storage;

use Gaufrette\Filesystem;
use Opensoft\StorageBundle\Entity\Storage;
use Opensoft\StorageBundle\Entity\StorageFile;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Provides an interface for stored files in the application.
 *
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
interface StorageManagerInterface
{
    /**
     * Retrieve an underlying gaufrette Filesystem assocaited with a Storage
     *
     * @param Storage $storage
     * @return Filesystem
     */
    public function getFilesystemForStorage(Storage $storage);

    /**
     * Takes a content and store it in permanent storage
     * Content might be:
     * UploadedFile
     * string - path to local file
     * StreamInterface - stream containing file contents
     *
     * It is the callers responsibility to associate the returned entity with a relation and save it to the database
     *
     * options:
     *
     * - newFilename: (string) Filename used to generate storage file key
     * - originalFilename: (string) Filename used to determine extension. And the used to generate storage file key
     * - metadata: (array) An array of key/value pairs to pass into storage adapter. Note:
     *   ContentType would be used as StorageFile mime-type
     *   ContentLength is required in case content is stream
     * - unlinkAfterStore: (bool) Set to true to unlink temporary stored file after storing to storage.
     *
     * @param integer $type
     * @param UploadedFile|StreamInterface|string $content
     * @param array $options
     * @throws \RuntimeException If writing the file fails
     * @return StorageFile
     */
    public function store($type, $content, array $options = []);

    /**
     * Store a file from an upload into the storage engine.
     *
     * It is the callers responsibility to associate the returned entity with a relation and save that relation to the database.  The
     * storage file itself will be independently stored by the storage manager.
     *
     * @deprecated Use StorageManagerInterface::store instead
     *
     * @param int $type
     * @param UploadedFile $uploadedFile
     * @param string|null $newFilename
     * @param bool $unlinkAfterStore
     * @return StorageFile
     */
    public function storeUploadedFile($type, UploadedFile $uploadedFile, $newFilename = null, $unlinkAfterStore = false);

    /**
     * Store a file from a local path into the storage engine.
     *
     * It is the callers responsibility to associate the returned entity with a relation and save that relation to the database.  The
     * storage file itself will be independently stored by the storage manager.
     *
     * @deprecated Use StorageManagerInterface::store instead
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
     * Copy a stored file into any location on the server and return the file location to the caller.
     *
     * @param StorageFile $file
     * @param string $destinationPath
     * @throws \RuntimeException If the copy fails
     * @return string
     */
    public function copyStorageFileToLocalPath(StorageFile $file, $destinationPath);

    /**
     * Retrieve a URL to this resource
     *
     * @param StorageFile $file
     * @param string $referenceType
     * @return string
     */
    public function retrieveUrl(StorageFile $file, $referenceType = StorageUrlResolverInterface::ABSOLUTE_URL);

    /**
     * Returns responses for download links for any StorageFile (remote and local)
     *
     * @param StorageFile $storageFile
     * @param array $additionalHeaders Additional headers to add to the response
     * @param bool $isInlineDisposition
     * @return StreamedResponse|BinaryFileResponse
     */
    public function returnStorageFileDownloadResponse(StorageFile $storageFile, array $additionalHeaders = [], $isInlineDisposition = false);
}
