<?php
/**
 * This file is part of ONP.
 *
 * Copyright (c) Opensoft (http://opensoftdev.com)
 *
 * The unauthorized use of this code outside the boundaries of
 * Opensoft is prohibited.
 */

namespace Opensoft\StorageBundle\Storage\Adapter;

use Gaufrette\Adapter;
use Gaufrette\Adapter\AzureBlobStorage\BlobProxyFactory;
use Gaufrette\Adapter\AzureBlobStorage;
use Opensoft\StorageBundle\Entity\StorageFile;
use Opensoft\StorageBundle\Storage\StorageUrlResolverInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
class AzureAdapterConfiguration extends AbstractAdapterConfiguration
{

    /**
     * @var string
     */
    private $permanentBaseUrl;

    /**
     * @param string $permanentBaseUrl
     */
    public function __construct($permanentBaseUrl)
    {
        $this->permanentBaseUrl = $permanentBaseUrl;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormInterface $form, array $options = null)
    {
        $form
            ->add('BlobEndpoint', TextType::class, [
                'required' => true,
                'help_block' => 'An Azure Blob Endpoint.'
            ])
            ->add('AccountName', TextType::class, [
                'required' => true,
                'help_block' => 'An Azure Account Name.'
            ])
            ->add('AccountKey', TextType::class, [
                'required' => true,
                'help_block' => 'The Azure Account Key.'
            ])
            ->add('ContainerName', TextType::class, [
                'required' => false,
                'help_block' => 'If you specify a container name, adapter will use only that container for all blobs.  If you omit specifying a container, it will use a so-called multi-container mode in which container name is determined directly from key. This allows for more flexibility if you\'re using dedicated storage accounts per asset type (ie. one for images, one for videos) as you get to group assets logically, use container-level privileges, etc.'
            ])
            ->add('create', CheckboxType::class, [
                'required' => false,
                'help_block' => 'Whether to create the container name if it does not exist.'
            ])
        ;
    }

    /**
     * Options are pre-validated, create and return the adapter object
     *
     * @param array $options
     * @return Adapter
     */
    protected function doCreateAdapter(array $options)
    {
        $connectionString = sprintf('BlobEndpoint=%s;AccountName=%s;AccountKey=%s',
            $options['BlobEndpoint'],
            $options['AccountName'],
            $options['AccountKey']
        );
        $factory = new BlobProxyFactory($connectionString);

        $adapter = new AzureBlobStorage(
            $factory,
            isset($options['ContainerName']) ? $options['ContainerName'] : null,
            $options['create']
        );

        return $adapter;
    }

    /**
     * Retrieve option configuration for this adapter
     *
     * @return OptionsResolver
     */
    protected function getOptionsResolver()
    {
        $resolver = new OptionsResolver();

        $resolver->setRequired([
            'BlobEndpoint',
            'AccountName',
            'AccountKey'
        ]);

        $resolver->setDefined([
            'ContainerName',
            'create',
        ]);

        return $resolver;
    }


    /**
     * {@inheritdoc}
     */
    public static function getName()
    {
        return 'Azure Blob Storage';
    }

    /**
     * @return array
     */
    public function getProtectedOptions()
    {
        return [
            'AccountKey'
        ];
    }

    /**
     * Retrieve a URL for a specific file that can be given to the browser
     *
     * @param StorageFile $file
     * @param string $referenceType
     * @return string
     */
    public function getUrl(StorageFile $file, $referenceType = StorageUrlResolverInterface::ABSOLUTE_URL)
    {
        $adapterOptions = $file->getStorage()->getAdapterOptions();

        switch ($referenceType) {
            case StorageUrlResolverInterface::PERMANENT_URL:
                $url = sprintf('%s/%s', $this->permanentBaseUrl, $file->getKey());
                break;
            case StorageUrlResolverInterface::ABSOLUTE_URL:
                $url = sprintf("%s/%s", $adapterOptions['BlobEndpoint'], $file->getKey());
                break;
            default:
                throw new \LogicException("Undefined url referenceType");
        }

        return $url;
    }

    /**
     * @param StorageFile $file
     * @return resource
     */
    public function getContext(StorageFile $file)
    {
        return stream_context_create([]);
    }

    /**
     * @param Request $request
     * @param string $scheme
     * @param string $storageKey
     * @param array $adapter
     * @param array $storageInfo
     * @return Response
     */
    public static function createPermanentUrlResponse(Request $request, $scheme, $storageKey, $adapter, $storageInfo)
    {
        $url = sprintf('%s/%s', $adapter['BlobEndpoint'], $storageKey);

        return new RedirectResponse($url);
    }
}
