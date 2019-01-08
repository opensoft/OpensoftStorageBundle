<?php

namespace Opensoft\StorageBundle\Storage\Adapter;

use League\Flysystem\AdapterInterface;
use Opensoft\StorageBundle\Entity\StorageFile;
use Opensoft\StorageBundle\Storage\StorageUrlResolverInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
interface AdapterConfigurationInterface
{

    /**
     * Validation options for and create the desired adapter
     *
     * @param array $options
     * @return AdapterInterface
     */
    public function createAdapter(array $options): AdapterInterface;

    /**
     * Build a subform for the options in this adapter
     *
     * @param FormInterface $form
     * @param array $options
     */
    public function buildForm(FormInterface $form, array $options = null): void;

    /**
     * Retrieve a user friendly name for this adapter
     *
     * @return string
     */
    public static function getName(): string;

    /**
     * Retrieve a list of protected options (not to be shown without extra permissions)
     *
     * @return array
     */
    public function getProtectedOptions(): array;

    /**
     * Retrieve a URL for a specific file that can be given to the browser
     *
     * @param StorageFile $file
     * @param string $referenceType
     * @return string
     */
    public function getUrl(StorageFile $file, string $referenceType = StorageUrlResolverInterface::ABSOLUTE_URL): string;

    /**
     * @param StorageFile $file
     * @return resource
     */
    public function getContext(StorageFile $file);

    /**
     * @param Request $request
     * @param string $scheme
     * @param string $storageKey
     * @param array $adapter
     * @param array $storageInfo
     * @return Response
     */
    public static function createPermanentUrlResponse(Request $request, string $scheme, string $storageKey, array $adapter, array $storageInfo): Response;
}
