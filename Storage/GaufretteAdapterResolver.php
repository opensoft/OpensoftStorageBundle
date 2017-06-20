<?php

namespace Opensoft\StorageBundle\Storage;

use Doctrine\Common\Collections\ArrayCollection;
use Gaufrette\Adapter;
use Opensoft\StorageBundle\Storage\Adapter\AdapterConfigurationInterface;
use Opensoft\StorageBundle\Storage\Adapter\AwsS3AdapterConfiguration;
use Opensoft\StorageBundle\Storage\Adapter\LocalAdapterConfiguration;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;

/**
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
class GaufretteAdapterResolver
{
    /**
     * @var ArrayCollection|AdapterConfigurationInterface[]
     */
    protected $configurations;

    public function __construct()
    {
        $this->configurations = new ArrayCollection();
    }

    /**
     * @param AdapterConfigurationInterface $adapterConfiguration
     */
    public function addConfiguration(AdapterConfigurationInterface $adapterConfiguration)
    {
        $adapterClass = get_class($adapterConfiguration);

        // BC shim to support new namespaces while extracting storage engine code into bundle
        if ($adapterClass == 'Opensoft\Onp\Bundle\CoreBundle\Storage\Adapter\LocalAdapterConfiguration') {
            $adapterClass = LocalAdapterConfiguration::class;
        } elseif ($adapterClass == 'Opensoft\Onp\Bundle\CoreBundle\Storage\Adapter\AwsS3AdapterConfiguration') {
            $adapterClass = AwsS3AdapterConfiguration::class;
        }

        $this->configurations->set($adapterClass, $adapterConfiguration);
    }

    /**
     * @param string $class
     * @return AdapterConfigurationInterface
     */
    public function getConfigurationByClass($class)
    {
        // BC shim to support new namespaces while extracting storage engine code into bundle
        if ($class == 'Opensoft\Onp\Bundle\CoreBundle\Storage\Adapter\LocalAdapterConfiguration') {
            $class = LocalAdapterConfiguration::class;
        } elseif ($class == 'Opensoft\Onp\Bundle\CoreBundle\Storage\Adapter\AwsS3AdapterConfiguration') {
            $class = AwsS3AdapterConfiguration::class;
        }

        return $this->configurations->get($class);
    }

    /**
     * @return ArrayCollection|AdapterConfigurationInterface[]
     */
    public function getConfigurations()
    {
        return $this->configurations;
    }

    /**
     * @return array
     */
    public function getAdapterChoices()
    {
        $choices = [];
        foreach ($this->configurations as $class => $configuration) {
            $choices[$class] = $configuration::getName();
        }

        return $choices;
    }

    /**
     * @param array $options
     * @throws InvalidOptionsException|MissingOptionsException|\InvalidArgumentException
     * @return Adapter
     */
    public function getAdapter(array $options)
    {
        $configuration = $this->getConfigurationByClass($options['class']);
        if (!$configuration) {
            throw new \InvalidArgumentException(sprintf("Class '%s' is not a valid adapter configuration", $options['class']));
        }

        unset($options['class']);

        return $configuration->createAdapter($options);
    }
}
