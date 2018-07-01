<?php

namespace Opensoft\StorageBundle\Storage\Adapter;

use Aws\S3\S3Client;
use Gaufrette\Adapter\AwsS3;
use Opensoft\StorageBundle\Entity\StorageFile;
use Opensoft\StorageBundle\Storage\StorageUrlResolverInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\RouterInterface;

/**
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
class AwsS3AdapterConfiguration extends AbstractAdapterConfiguration
{
    public static $availableSignatureVersions = [
        'v4' => 'v4',
        'v4-unsigned-body' => 'v4-unsigned-body'
    ];

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var string
     */
    private $permanentBaseUrl;

    /**
     * @param RouterInterface $router
     * @param string $permanentBaseUrl
     */
    public function __construct(RouterInterface $router, $permanentBaseUrl)
    {
        $this->router = $router;
        $this->permanentBaseUrl = $permanentBaseUrl;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormInterface $form, array $options = null)
    {
        $form
            ->add('key', TextType::class, [
                'required' => true,
                'help_block' => 'An AWS access key ID.'
            ])
            ->add('secret', TextType::class, [
                'required' => true,
                'help_block' => 'An AWS secret access key.'
            ])
            ->add('bucket_name', TextType::class, [
                'required' => true,
                'help_block' => 'The AWS S3 bucket name.'
            ])
            ->add('region', TextType::class, [
                'required' => false,
                'help_block' => 'The main <a target="_blank" href="http://docs.aws.amazon.com/general/latest/gr/rande.html#s3_region">AWS Region</a>.  Defaults to <code>us-east-1</code>.'
            ])
            ->add('cname', TextType::class, [
                'required' => false,
                'help_block' => 'Optional CNAME vs default AWS URL',
                'label'=>'CNAME',
            ])
            ->add('signature_version', ChoiceType::class, [
                'required' => false,
                'choices' => self::$availableSignatureVersions,
                'help_block' => 'Signature version used to sign requests (<a target="_blank" href="https://docs.aws.amazon.com/general/latest/gr/signing_aws_api_requests.html">Signing AWS API Requests</a>).  Defaults to <a target="_blank" href="https://docs.aws.amazon.com/general/latest/gr/signature-version-4.html">version 4</a>. Should be set to <code>v4-unsigned-body</code> to support upload streaming',
                'placeholder' => false,
            ])
        ;
    }

    /**
     * {@inheritdoc}
     */
    public static function getName()
    {
        return 'Amazon S3 Remote Storage';
    }

    /**
     * @return array
     */
    public function getProtectedOptions()
    {
        return [
            'secret'
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function doCreateAdapter(array $options)
    {
        $service = new S3Client([
            'version' => '2006-03-01', // see http://docs.aws.amazon.com/AmazonS3/latest/API/Welcome.html
            'region' => !empty($options['region']) ? $options['region'] : 'us-east-1',
            'credentials' => [
                'key' => $options['key'],
                'secret' => $options['secret'],
            ],
            'signature_version' => !empty($options['signature_version']) ? $options['signature_version'] : 'v4',
        ]);
        $adapter = new AwsS3($service, $options['bucket_name'], [], true);

        return $adapter;
    }

    /**
     * {@inheritdoc}
     */
    protected function getOptionsResolver()
    {
        $resolver = new OptionsResolver();

        $resolver->setRequired([
            'key',
            'secret',
            'bucket_name'
        ]);

        $resolver->setDefined([
            'cname',
            'region',
            'signature_version',
        ]);

        $resolver->setAllowedValues('signature_version', self::$availableSignatureVersions);

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
        $hostname = isset($adapterOptions['cname']) ? $adapterOptions['cname'] : sprintf("%s.s3.amazonaws.com", $adapterOptions['bucket_name']);

        switch ($referenceType) {
            case StorageUrlResolverInterface::PERMANENT_URL:
                $url = sprintf('%s/%s', $this->permanentBaseUrl, $file->getKey());
                break;
            case StorageUrlResolverInterface::ABSOLUTE_URL:
                $url = sprintf("%s://%s/%s", $this->router->getContext()->getScheme(), $hostname, $file->getKey());
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
        $adapterOptions = $file->getStorage()->getAdapterOptions();
        $opts = [
            'http' => [
                'header' => sprintf("Host: %s.s3.amazonaws.com\r\n", $adapterOptions['bucket_name'])
            ]
        ];

        return stream_context_create($opts);
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
        $url = sprintf('%s://%s.s3.amazonaws.com/%s', $scheme, $adapter['bucket_name'], $storageKey);

        return new RedirectResponse($url);
    }
}
