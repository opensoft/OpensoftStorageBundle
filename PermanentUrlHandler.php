<?php
/**
 * This file is part of ONP.
 *
 * Copyright (c) Opensoft (http://opensoftdev.com)
 *
 * The unauthorized use of this code outside the boundaries of
 * Opensoft is prohibited.
 */

namespace Opensoft\StorageBundle;

use Doctrine\DBAL\Connection;
use Opensoft\StorageBundle\Entity\StorageFile;
use Opensoft\StorageBundle\Storage\Adapter\AdapterConfigurationInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;


/**
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
class PermanentUrlHandler implements HttpKernelInterface
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Handles a Request to convert it to a Response.
     *
     * When $catch is true, the implementation must catch all exceptions
     * and do its best to convert them to a Response instance.
     *
     * @param Request $request A Request instance
     * @param int $type The type of the request
     *                         (one of HttpKernelInterface::MASTER_REQUEST or HttpKernelInterface::SUB_REQUEST)
     * @param bool $catch Whether to catch exceptions or not
     *
     * @return Response A Response instance
     *
     * @throws \Exception When an Exception occurs during processing
     */
    public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = true)
    {

        try {
            $pathInfo = $request->getPathInfo();
            $scheme = 'https';
        } catch (\Exception $e) {
            return new Response('', Response::HTTP_BAD_REQUEST);
        }


        $storageKey = substr($pathInfo, 1);

        $sql = '
            SELECT sf.mime_type,
                   sf.type,
                   sf.size_in_bytes,
                   s.adapter_options
            FROM storage_file sf
            INNER JOIN storage s ON sf.storage_id = s.id
            WHERE sf.storage_key = ?
        ';

        $storageInfo = $this->connection->fetchAssoc($sql, [$storageKey]);

        if (empty($storageInfo['adapter_options'])) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $adapter = unserialize($storageInfo['adapter_options']);
        /** @var AdapterConfigurationInterface|string $adapterClass */
        $adapterClass = $adapter['class'];

        if (!class_exists($adapterClass)) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }


        $response = $adapterClass::createPermanentUrlResponse($request, $scheme, $storageKey, $adapter, $storageInfo);

        if (!$response->isNotFound()) {
            $this->addHeadersByType($storageInfo['type'], $response);
        }

        return $response;

//        if ($adapter['class'] === LocalAdapterConfiguration::class) {
//
//        } elseif ($adapter['class'] === AwsS3AdapterConfiguration::class) {
//
//        }

//        return new Response('', Response::HTTP_NOT_FOUND);
    }

    /**
     * Add headers to the response object based on the storage file type
     *
     * @param integer $type
     * @param Response $response
     */
    private function addHeadersByType($type, Response $response)
    {
        switch ($type) {
            case StorageFile::TYPE_DVS_UPLOAD_IMAGE:
                $response->headers->set('Access-Control-Allow-Origin', '*');
                break;
            case StorageFile::TYPE_BANNER_IMAGE:
            case StorageFile::TYPE_MAIN_PAGE_BLOCK_TABLET_IMAGE:
                // Set a 1 year expires public cache on these items
                $response->headers->set('Cache-Control', 'max-age=31536000, public');
                $response->headers->set('Expires', (new \DateTime('1 year'))->format("D, d M Y H:i:s T"));
                break;
        }
    }
}
