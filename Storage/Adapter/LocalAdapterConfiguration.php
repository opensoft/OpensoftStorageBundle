<?php

namespace Opensoft\StorageBundle\Storage\Adapter;

use Opensoft\StorageBundle\Entity\Storage;
use Opensoft\StorageBundle\Entity\StorageFile;
use Opensoft\StorageBundle\Storage\Gaufrette\Adapter\Local;
use Opensoft\StorageBundle\Storage\StorageUrlResolverInterface;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\RouterInterface;

/**
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
class LocalAdapterConfiguration extends AbstractAdapterConfiguration implements UsageAwareInterface
{
    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var Packages
     */
    private $assetPackages;

    /**
     * @var string
     */
    private $permanentBaseUrl;

    /**
     * @param RouterInterface $router
     * @param Packages $assetPackages
     * @param string $permanentBaseUrl
     */
    public function __construct(RouterInterface $router, Packages $assetPackages, $permanentBaseUrl)
    {
        $this->router = $router;
        $this->assetPackages = $assetPackages;
        $this->permanentBaseUrl = $permanentBaseUrl;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormInterface $form, array $options = null)
    {
        $form
            ->add('directory', TextType::class, [
                'required' => true,
                'help_block' => 'Directory where the filesystem is located local to the webservers. This should be a relative path of the application\'s <code>web/</code> directory.
                            <br /><br /> <strong>Note:</strong>  This directory must be present on all web servers.'
            ])
            ->add('http_host', TextType::class, [
                'required' => true,
                'help_block' => 'The local HTTP host which will serve the storage files.'
            ])
            ->add('create', CheckboxType::class, [
                'required' => false,
                'help_block' => 'Whether to create the directory if it does not exist.'
            ])
            ->add('mode', TextType::class, [
                'required' => true,
                'data' => isset($options['mode']) ? $options['mode'] : '0777',
                'help_block' => 'Mode for mkdir.'
            ])
        ;
    }

    /**
     * {@inheritdoc}
     */
    public static function getName()
    {
        return 'Local Filesystem Storage';
    }

    /**
     * {@inheritdoc}
     */
    protected function doCreateAdapter(array $options)
    {
        return new Local($options['directory'], $options['create'], intval($options['mode'], 8));
    }

    /**
     * {@inheritdoc}
     */
    protected function getOptionsResolver()
    {
        $resolver = new OptionsResolver();

        $resolver->setRequired([
            'directory'
        ]);
        $resolver->setDefined([
            'create',
            'mode',
            'http_host'
        ]);
        $resolver->setDefaults([
            'create' => false,
            'mode' => '0777'
        ]);

        return $resolver;
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
        $path = $adapterOptions['directory'] . '/' . $file->getKey();

        switch ($referenceType) {
            case StorageUrlResolverInterface::PERMANENT_URL:
                $url = sprintf('%s/%s', $this->permanentBaseUrl, $file->getKey());
                break;
            case StorageUrlResolverInterface::ABSOLUTE_URL:
                $url = $this->router->getContext()->getScheme() . ':' . $this->assetPackages->getUrl($path);
                break;
            default:
                throw new \LogicException('Undefined url $referenceType');
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
     * @param Storage $storage
     * @return array
     */
    public function usage(Storage $storage)
    {
        $adapterOptions = $storage->getAdapterOptions();
        $path = $adapterOptions['directory'];

        $usage = array();
        //Find disk usage - the 'awk' takes out the extra spaces so split() will work correctly
        exec("df -Pk " . $path . "|awk '{print $2,$3,$1,$4,$5}'", $usage);
        $usageline = explode(' ', $usage[1]);

        $data = [
            'usagepct' => ($usageline[0]) ? $usageline[4] : 0.00,
            'usagesize' => $usageline[1] / 1024 / 1024,
            'device' => $usageline[2],
        ];

        return $data;
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
        // Assumption here that directories are relative to web/
        $fileName = sprintf('%s/%s', $adapter['directory'], $storageKey);
        if (!file_exists($fileName)) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        if ($storageInfo['size_in_bytes'] > 1000000) {
            $response = new RedirectResponse(sprintf(
                '%s://%s/%s/%s',
                $scheme,
//                $adapter['http_host'],
                isset($adapter['http_host']) ? $adapter['http_host'] : $request->getHttpHost(), // TODO - remove after all storages have http_host values
                rtrim($adapter['directory'], '/'),
                $storageKey
            ));

            return $response;
        }

        $fileModified = filemtime($fileName);
        $since = $request->server->get('HTTP_IF_MODIFIED_SINCE');
        $requestedTime = !empty($since) ? strtotime($since) : null;

        $response = new BinaryFileResponse($fileName, Response::HTTP_OK, ['Content-Type' => $storageInfo['mime_type']]);
        $response->prepare($request);

        if (!empty($requestedTime) && $fileModified <= $requestedTime) {
            $response->setStatusCode(Response::HTTP_NOT_MODIFIED);
        }

        return $response;
    }
}
