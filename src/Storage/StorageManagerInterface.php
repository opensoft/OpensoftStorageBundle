<?php

namespace Opensoft\StorageBundle\Storage;

use League\Flysystem\FilesystemInterface;
use Opensoft\StorageBundle\Entity\Storage;
use Opensoft\StorageBundle\Entity\StorageFile;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Provides an interface for stored files in the application.
 *
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
interface StorageManagerInterface
{
    /**
     * Retrieve an underlying Filesystem associated with a Storage
     *
     * @param Storage $storage
     * @return FilesystemInterface
     */
    public function filesystem(Storage $storage): FilesystemInterface;

    /**
     * Ask the underlying filesystem if the file actually exists
     *
     * @param StorageFile $file
     * @return bool
     */
    public function exists(StorageFile $file): bool;

    /**
     * @param StorageFile $file
     * @return array
     */
    public function metadata(StorageFile $file): array;

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
     * @param int $type
     * @param UploadedFile|StreamInterface|string $content
     * @param array $options
     * @throws \RuntimeException If writing the file fails
     * @return StorageFile
     */
    public function store(int $type, $content, array $options = []): StorageFile;

    /**
     * WARNING - very memory intensive for large files
     *
     * @param StorageFile $file
     * @return string
     */
    public function content(StorageFile $file): string;

    /**
     * Moves a storage file from it's current storage location to a new one.  The storage manager will correctly update
     * the database with the storage reference change.
     *
     * @param StorageFile $file
     * @param Storage $toStorage
     * @return StorageFile
     */
    public function move(StorageFile $file, Storage $toStorage): StorageFile;

    /**
     * Copy a stored file's content into a scratch location on the server's system temp directory (usually /tmp) and return
     * the file location to the caller.
     *
     * After the caller is done with this file, it should delete it locally.
     *
     * @param StorageFile $file
     * @param string|null $destination
     * @return string
     */
    public function copy(StorageFile $file, ?string $destination = null): string;

    /**
     * Retrieve a URL to this resource
     *
     * @param StorageFile $file
     * @param string $referenceType
     * @return string
     */
    public function url(StorageFile $file, string $referenceType = StorageUrlResolverInterface::ABSOLUTE_URL): string;

    /**
     * Returns responses for download links for any StorageFile (remote and local)
     *
     * @param StorageFile $storageFile
     * @param array $additionalHeaders Additional headers to add to the response
     * @param bool $isInlineDisposition
     * @return StreamedResponse|BinaryFileResponse
     */
    public function downloadResponse(StorageFile $storageFile, array $additionalHeaders = [], bool $isInlineDisposition = false): Response;
}
