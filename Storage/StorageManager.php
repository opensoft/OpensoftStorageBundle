<?php

namespace Opensoft\StorageBundle\Storage;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManager;
use Gaufrette\Filesystem;
use Gaufrette\StreamWrapper;
use Opensoft\StorageBundle\Entity\Storage;
use Opensoft\StorageBundle\Entity\StorageFile;
use Opensoft\StorageBundle\Entity\StoragePolicy;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesser;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\OptionsResolver\OptionsResolver;

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
     * @var StorageFileTypeProviderInterface
     */
    private $storageTypeProvider;

    /**
     * @param ManagerRegistry $doctrine
     * @param GaufretteAdapterResolver $gaufretteAdapterResolver
     * @param StorageUrlResolverInterface $storageUrlResolver
     * @param StorageKeyGeneratorInterface $storageKeyGenerator
     * @param StorageFileTypeProviderInterface $storageTypeProvider
     */
    public function __construct(
        ManagerRegistry $doctrine,
        GaufretteAdapterResolver $gaufretteAdapterResolver,
        StorageUrlResolverInterface $storageUrlResolver,
        StorageKeyGeneratorInterface $storageKeyGenerator,
        StorageFileTypeProviderInterface $storageTypeProvider
    ) {
        $this->doctrine = $doctrine;
        $this->gaufretteAdapterResolver = $gaufretteAdapterResolver;
        $this->storageUrlResolver = $storageUrlResolver;
        $this->storageKeyGenerator = $storageKeyGenerator;
        $this->storageTypeProvider = $storageTypeProvider;
    }

    /**
     * {@inheritdoc}
     */
    public function storeUploadedFile($type, UploadedFile $uploadedFile, $newFilename = null, $unlinkAfterStore = true)
    {
        $options = [];
        if ($newFilename !== null) {
            $options['newFilename'] = $newFilename;
        }
        if ($unlinkAfterStore !== false) {
            $options['unlinkAfterStore'] = $unlinkAfterStore;
        }
        return $this->store($type, $uploadedFile, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function storeFileFromLocalPath($type, $path, $newFilename = null, $unlinkAfterStore = false)
    {
        $options = [];
        if ($newFilename !== null) {
            $options['newFilename'] = $newFilename;
        }
        if ($unlinkAfterStore !== false) {
            $options['unlinkAfterStore'] = $unlinkAfterStore;
        }
        return $this->store($type, $path, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function store($type, $content, array $options = [])
    {
        $options = $this->getOptionsResolver()->resolve($options);

        $this->validateFileStorageType($type);

        $options = $this->getContentOptions($content, $options);
        $newFilename = $this->getFilename($options);

        $storage = $this->getStorageFromWritePolicy($type);

        $file = new StorageFile(
            $this->storageKeyGenerator->generate($newFilename),
            $this->getFilesystemForStorage($storage),
            $storage
        );

        $this->storeFileContent($file, $content, $options);

        $file->setType($type);

        $this->saveToDatabase($file);

        return $file;
    }

    /**
     * @param StorageFile $file
     * @param UploadedFile|StreamInterface|string $content
     * @param array $options
     */
    private function storeFileContent(StorageFile $file, $content, $options = [])
    {
        $storage = $file->getStorage();

        $metadata = $options['metadata'];

        if (!empty($options['realPath'])) {
            $path = $options['realPath'];
            $bytes = $this->streamCopy($path, $this->getIOStream($storage->getSlug(), $file->getKey()));

            $file->setContentHash(md5_file($path));
            $file->setSize($bytes);

            if (empty($metadata['ContentType'])) {
                $metadata['ContentType'] = MimeTypeGuesser::getInstance()->guess($path);
            }

            $file->setFileMetadata($metadata);
        } else {
            if (empty($metadata['ContentType'])) {
                $metadata['ContentType'] = 'binary/octet-stream';
            }

            $bytes = $file->setContent($content, $metadata);
        }

        $file->setMimeType($metadata['ContentType']);

        if ($bytes == 0) {
            throw new \RuntimeException(
                sprintf(
                    "Unable to stream file to storage '%s' with key '%s'.  Zero bytes streamed.",
                    $storage->getName(),
                    $file->getKey()
                )
            );
        }

        if (!$file->exists()) {
            throw new \RuntimeException(
                sprintf(
                    "Could not stream to storage '%s' with key '%s'.  Resultant file does not exist.",
                    $storage->getName(),
                    $file->getKey()
                )
            );
        }

        $this->unlinkIfNeeded($options);
    }

    /**
     * Return generated filename
     *
     * @param array $options
     * @return string
     */
    private function getFilename($options)
    {
        if (!empty($options['newFilename'])) {
            return $options['newFilename'];
        }

        if (!empty($options['originalFilename'])) {
            $path = $options['originalFilename'];
        } else if (!empty($options['realPath'])) {
            $path = $options['realPath'];
        } else {
            $path = uniqid();
        }

        $newFilename = uniqid('gen' . substr(hash('sha256', $path), 0, 4));

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        if ($extension != null) {
            $newFilename .= '.' . $extension;
        }

        return $newFilename;
    }

    /**
     * Enrich options with available content metadata
     *
     * @param UploadedFile|StreamInterface|string $content
     * @param array $options
     * @return array
     */
    private function getContentOptions($content, $options = [])
    {
        if ($content instanceof UploadedFile) {
            $options['realPath'] = $content->getRealPath();
            $options['originalFilename'] = $content->getClientOriginalName();
            $options['metadata']['ContentType'] = $content->getClientMimeType();
        } else if (is_string($content)) {
            $options['realPath'] = $content;
        }

        return $options;
    }

    /**
     * Unlink original file if real path is known and unlink param is present
     *
     * @param array $options
     */
    private function unlinkIfNeeded($options)
    {
        if (empty($options['realPath'])) {
            return;
        }

        if ($options['unlinkAfterStore'] === false) {
            return;
        }

        unlink($options['realPath']);
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
     * Returns responses for download links for any StorageFile (remote and local)
     *
     * @param StorageFile $storageFile
     * @param array $additionalHeaders Additional headers to add to the response
     * @param bool $isInlineDisposition
     * @return StreamedResponse|BinaryFileResponse
     */
    public function returnStorageFileDownloadResponse(StorageFile $storageFile, array $additionalHeaders = [], $isInlineDisposition = false)
    {
        if ($storageFile->isLocal()) {
            return new BinaryFileResponse($storageFile->getLocalPath(), 200, [], true, $isInlineDisposition ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT);
        }

        // stream the response through readfile to deal with remote storage
        $fileUrl = $this->retrieveUrl($storageFile);

        // just in case we need a stream context (ie. when using CNAME's with s3)
        $context = $this->retrieveContext($storageFile);

        $response = new StreamedResponse(function() use ($fileUrl, $context) {
            readfile($fileUrl, false, $context);
        });

        $key = $storageFile->getKey();
        $fn = strpos($key, '/') !== false ? substr($key, strrpos($key, '/') + 1) : $key;
        $contentDisposition = $isInlineDisposition ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT;
        $d = $response->headers->makeDisposition($contentDisposition, $fn);
        $response->headers->set('Content-Disposition', $d);
        $response->headers->set('Content-Length', $storageFile->getSize());
        $response->headers->set('Content-Transfer-Encoding', 'binary');
        $response->headers->set('Content-Type', $storageFile->getMimeType());

        foreach ($additionalHeaders as $key => $value) {
            $response->headers->set($key, $value);
        }

        return $response;
    }

    /**
     * @param StorageFile $file
     * @return resource
     */
    private function retrieveContext(StorageFile $file)
    {
        return $this->storageUrlResolver->getContext($file);
    }

    /**
     * Retrieve a storage for writing a file by type
     *
     * @param int $type
     * @throws \UnexpectedValueException
     * @return Storage
     */
    private function getStorageFromWritePolicy($type)
    {
        $this->validateFileStorageType($type);

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

    /**
     * @param int $type
     * @throws \InvalidArgumentException
     */
    private function validateFileStorageType($type)
    {
        $availableTypes = $this->storageTypeProvider->getTypes();
        if (!isset($availableTypes[$type])) {
            throw new \InvalidArgumentException(sprintf("StorageFile type out of range.  Allowed values:  '%s'", implode(', ', array_keys($availableTypes))));
        }
    }

    private function getOptionsResolver()
    {
        $resolver = new OptionsResolver();

        $resolver->setDefined([
            'newFilename',
            'originalFilename',
            'metadata',
            'unlinkAfterStore',
        ]);

        $resolver->setAllowedTypes('newFilename', 'string');
        $resolver->setAllowedTypes('originalFilename', 'string');
        $resolver->setAllowedTypes('metadata', 'array');
        $resolver->setAllowedTypes('unlinkAfterStore', 'bool');

        $resolver->setDefault('unlinkAfterStore', false);
        $resolver->setDefault('metadata', []);

        return $resolver;
    }
}
