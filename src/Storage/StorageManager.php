<?php

namespace Opensoft\StorageBundle\Storage;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManager;
use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\StreamWrapper;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemInterface;
use League\Flysystem\Util;
use Opensoft\StorageBundle\Entity\Storage;
use Opensoft\StorageBundle\Entity\StorageFile;
use Opensoft\StorageBundle\Entity\StorageMoveException;
use Opensoft\StorageBundle\Entity\StoragePolicy;
use Opensoft\StorageBundle\Storage\Stream\HashingStream;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeExtensionGuesser;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
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
     * @var AdapterResolver
     */
    private $adapterResolver;

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
     * @param AdapterResolver $adapterResolver
     * @param StorageUrlResolverInterface $storageUrlResolver
     * @param StorageKeyGeneratorInterface $storageKeyGenerator
     * @param StorageFileTypeProviderInterface $storageTypeProvider
     */
    public function __construct(
        ManagerRegistry $doctrine,
        AdapterResolver $adapterResolver,
        StorageUrlResolverInterface $storageUrlResolver,
        StorageKeyGeneratorInterface $storageKeyGenerator,
        StorageFileTypeProviderInterface $storageTypeProvider
    ) {
        $this->doctrine = $doctrine;
        $this->adapterResolver = $adapterResolver;
        $this->storageUrlResolver = $storageUrlResolver;
        $this->storageKeyGenerator = $storageKeyGenerator;
        $this->storageTypeProvider = $storageTypeProvider;
    }

    /**
     * @param int $type
     * @param resource|string|null|int|float|bool|StreamInterface|callable|\Iterator|UploadedFile $content
     * @param array $options
     * @return StorageFile
     * @throws \League\Flysystem\FileExistsException
     */
    public function store(int $type, $content, array $options = []): StorageFile
    {
        $options = $this->getOptionsResolver()->resolve($options);
        $this->validateFileStorageType($type);

        if (isset($options['string_content']) && $options['string_content']) {
            // do nothing
        } elseif ($content instanceof File) {
            $filepath = $content->getRealPath();
            if (!is_file($filepath)) {
                throw new \InvalidArgumentException(sprintf('Could not find file "%s"', $filepath));
            }
            if (filesize($filepath) === 0) {
                throw new \InvalidArgumentException(sprintf('Can not create storage file from zero byte file "%s"', $filepath));
            }

            if (!isset($options['mimetype'])) {
                $options['mimetype'] = $content instanceof UploadedFile ? $content->getClientMimeType() : $content->getMimeType();
            }

            $content = Psr7\try_fopen($filepath, 'rb');
        } elseif (is_string($content)) {
            if (!is_file($content)) {
                throw new \InvalidArgumentException(sprintf('Could not find file "%s"', $content));
            }
            if (filesize($content) === 0) {
                throw new \InvalidArgumentException(sprintf('Can not create storage file from zero byte file "%s"', $content));
            }

            $filepath = $content;

            $content = Psr7\try_fopen($filepath, 'rb');
            if (!isset($options['mimetype']) || $options['mimetype'] === null) {
                if (class_exists('finfo')) {
                    $finfo = new \finfo(FILEINFO_MIME_TYPE);

                    $mimeType = $finfo->file($filepath) ?: null;
                    if ($mimeType !== null) {
                        $options['mimetype'] = $mimeType;
                    }
                }
                if (!isset($options['mimetype'])) {
                    $options['mimetype'] = Util::guessMimeType($filepath, $content);
                }
            }
        }

        $stream = Psr7\stream_for($content);
        if ($stream->getSize() === 0) {
            throw new \InvalidArgumentException('Stream size is zero');
        }

        if (!isset($options['mimetype']) && $uri = $stream->getMetadata('uri') !== null) {
            $options['mimetype'] = Psr7\mimetype_from_filename($uri);
        }
        if (!isset($options['mimetype'])) {
            $options['mimetype'] = Util::guessMimeType('', $content) ?? 'application/octet-stream';
        }

        if (!isset($options['newFilename'])) {
            $generatedFilename = $this->storageTypeProvider->generateBaseFilename($type);
            if ($generatedFilename === null) {
                $generatedFilename = uniqid('gen_', true);
            }
            $options['newFilename'] = $generatedFilename;
            $guessedExtension = (new MimeTypeExtensionGuesser())->guess($options['mimetype']);
            if ($guessedExtension) {
                $options['newFilename'] = $options['newFilename'] . '.' . $guessedExtension;
            }
        }

        $key = $this->storageKeyGenerator->generate($options['newFilename']);

        $storage = $this->getStorageFromWritePolicy($type);
        $file = new StorageFile($key, $storage);

        $hashingStream = new HashingStream($stream, function ($hash) use ($file) {
            $file->setContentHash($hash);
        });
        $file->setMimeType($options['mimetype']);
        $file->setSize($hashingStream->getSize());
        $file->setType($type);

        $successfulWrite = $this->filesystem($storage)->writeStream($key, StreamWrapper::getResource($hashingStream), $options);

        if (!$successfulWrite) {
            throw new \RuntimeException(sprintf('Failed to stream to key "%s"', $key));
        }

        $this->saveToDatabase($file);

        return $file;
    }

    /**
     * @param StorageFile $file
     * @return bool
     */
    public function exists(StorageFile $file): bool
    {
        return $this->filesystem($file->getStorage())->has($file->getKey());
    }

    /**
     * @param StorageFile $file
     * @return array|false
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function metadata(StorageFile $file): array
    {
        return $this->filesystem($file->getStorage())->getMetadata($file->getKey());
    }

    /**
     * WARNING - very memory intensive for large files
     *
     * @param StorageFile $file
     * @return string
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function content(StorageFile $file): string
    {
        return $this->filesystem($file->getStorage())->read($file->getKey());
    }

    /**
     * @param StorageFile $file
     * @param bool|int|string $referenceType
     * @return string
     */
    public function url(StorageFile $file, ?string $referenceType = StorageUrlResolverInterface::ABSOLUTE_URL): string
    {
        return $this->storageUrlResolver->getUrl($file, $referenceType);
    }

    /**
     * Moves a storage file from it's current storage location to a new one.
     *
     * @param StorageFile $file
     * @param Storage $toStorage
     * @throws \Exception If the move fails
     * @return StorageFile
     */
    public function move(StorageFile $file, Storage $toStorage): StorageFile
    {
        $key = $file->getKey();
        $fromStorage = $file->getStorage();
        $fromFileStorageSystem = $this->filesystem($file->getStorage());
        $toStorageFilesystem = $this->filesystem($toStorage);

        try {
            $success = $toStorageFilesystem->writeStream($key, $fromFileStorageSystem->readStream($key));
            if (!$success) {
                throw new \RuntimeException(
                    sprintf(
                        "Unable to write stream from storage '%s' with key '%s' to storage '%s' with key '%s'.",
                        $fromStorage->getName(),
                        $key,
                        $toStorage->getName(),
                        $key
                    )
                );
            }
            $bytes = $toStorageFilesystem->getSize($key);

            if ($bytes === 0) {
                throw new \RuntimeException(
                    sprintf(
                        "Unable to copy file from storage '%s' with key '%s' to storage '%s' with key '%s'.  Zero bytes copied.",
                        $fromStorage->getName(),
                        $key,
                        $toStorage->getName(),
                        $key
                    )
                );
            }

            if ($file->getSize() !== $bytes) {
                $toStorageFilesystem->delete($key);

                throw new \RuntimeException(
                    sprintf(
                        "Unexpectedly copied '%d' bytes instead of the expected '%d' for storage key '%s'.",
                        $bytes,
                        $file->getSize(),
                        $key
                    )
                );
            }

            if (!$toStorageFilesystem->has($key)) {
                throw new \RuntimeException(
                    sprintf(
                        "Could not stream copy file from storage '%s' with key '%s' to storage '%s' with key '%s'.",
                        $fromStorage->getName(),
                        $key,
                        $toStorage->getName(),
                        $key
                    )
                );
            }

            $file->setStorage($toStorage);
            $this->saveToDatabase($file);

        } catch (\Exception $e) {
            $moveException = new StorageMoveException($file, $fromStorage, $toStorage, $e);

            /** @var EntityManager $em */
            $em = $this->doctrine->getManager();
            $em->persist($moveException);
            $em->flush($moveException);

            throw $e;
        }


        if (!$fromFileStorageSystem->delete($key)) {
            throw new \RuntimeException(
                sprintf(
                    "Could not delete original file from storage '%s' and key '%s' after copy.",
                    $fromStorage->getName(),
                    $file->getKey()
                )
            );
        }

        return $file;
    }

    /**
     * Copy a stored file into any location on the server and return the file location to the caller.
     *
     * @param StorageFile $file
     * @param string|null $destination
     * @return string
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function copy(StorageFile $file, ?string $destination = null): string
    {
        if ($destination === null) {
            $destination = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $file->getKey();
        }

        if (!is_dir(dirname($destination))) {
            mkdir(dirname($destination), 0777, true);
        }

        $dstStream = fopen($destination, 'wb');

//        Psr7\copy_to_stream($this->filesystem($file->getStorage())->readStream($file->getKey()), Psr7\stream_for($dstStream));
        $bytes = stream_copy_to_stream($this->filesystem($file->getStorage())->readStream($file->getKey()), $dstStream);

        if ($bytes === 0) {
            throw new \RuntimeException(
                sprintf(
                    "Unable to copy file from storage '%s' with key '%s' to local path '%s'.  Zero bytes copied.",
                    $file->getStorage()->getName(),
                    $file->getKey(),
                    $destination
                )
            );
        }
        if ($file->getSize() !== $bytes) {
            @unlink($destination);

            throw new \RuntimeException(
                sprintf(
                    "Unexpectedly copied '%d' bytes instead of the expected '%d' for storage key '%s'.",
                    $bytes,
                    $file->getSize(),
                    $file->getKey()
                )
            );
        }

        if (!file_exists($destination)) {
            throw new \RuntimeException(
                sprintf(
                    "Could not stream copy file from storage '%s' with key '%s' to destination '%s'.",
                    $file->getStorage()->getName(),
                    $file->getKey(),
                    $destination
                )
            );
        }

        return $destination;
    }

    /**
     * Retrieve the filesystem object for an associated storage
     *
     * @param Storage $storage
     * @return FilesystemInterface
     */
    public function filesystem(Storage $storage): FilesystemInterface
    {
        static $cachedFilesystems = [];

        if (!isset($cachedFilesystems[$storage->getId()])) {
            $adapter = $this->adapterResolver->getAdapter($storage->getAdapterOptions());
            $filesystem = new Filesystem($adapter);

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
    public function downloadResponse(StorageFile $storageFile, array $additionalHeaders = [], ?bool $isInlineDisposition = false): Response
    {
        if ($storageFile->isLocal()) {
            return new BinaryFileResponse($storageFile->getLocalPath(), 200, [], true, $isInlineDisposition ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT);
        }

        // stream the response through readfile to deal with remote storage
        $fileUrl = $this->url($storageFile);

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
    private function getStorageFromWritePolicy(int $type): Storage
    {
        $this->validateFileStorageType($type);

        $storagePolicy = $this->doctrine->getRepository(StoragePolicy::class)->findOneByType($type);
        if ($storagePolicy && $storagePolicy->getCreateInStorage()) {
            return $storagePolicy->getCreateInStorage();
        }

        $storage = $this->doctrine->getRepository(Storage::class)->findOneByActive();
        if ($storage === null) {
            throw new \UnexpectedValueException('No default write storage locations present');
        }

        return $storage;
    }

    /**
     * @param StorageFile $file
     */
    private function saveToDatabase(StorageFile $file): void
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
    private function validateFileStorageType(int $type): void
    {
        $availableTypes = $this->storageTypeProvider->getTypes();
        if (!isset($availableTypes[$type])) {
            throw new \InvalidArgumentException(sprintf("StorageFile type out of range.  Allowed values:  '%s'", implode(', ', array_keys($availableTypes))));
        }
    }

    /**
     * @return OptionsResolver
     */
    private function getOptionsResolver(): OptionsResolver
    {
        $resolver = new OptionsResolver();

        $resolver->setDefined([
            'newFilename',
            'originalFilename',
            'metadata',
            'unlinkAfterStore',
            'mimetype',

            //
            'string_content',
        ]);

        $resolver->setAllowedTypes('newFilename', 'string');
        $resolver->setAllowedTypes('originalFilename', 'string');
        $resolver->setAllowedTypes('metadata', 'array');
        $resolver->setAllowedTypes('unlinkAfterStore', 'bool');
        $resolver->setAllowedTypes('mimetype', 'string');
        $resolver->setAllowedTypes('string_content', 'bool');

        $resolver->setDefault('unlinkAfterStore', true);
        $resolver->setDefault('metadata', []);
        $resolver->setDefault('string_content', false);

        return $resolver;
    }
}
