<?php

namespace Opensoft\StorageBundle\Storage;

use Doctrine\Common\Collections\ArrayCollection;
use League\Flysystem\AdapterInterface;
use Opensoft\StorageBundle\Storage\Adapter\AdapterConfigurationInterface;
use Opensoft\StorageBundle\Storage\Adapter\AwsS3AdapterConfiguration;
use Opensoft\StorageBundle\Storage\Adapter\LocalAdapterConfiguration;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;

/**
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
class AdapterResolver
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
    public function addConfiguration(AdapterConfigurationInterface $adapterConfiguration): void
    {
        $this->configurations->set(get_class($adapterConfiguration), $adapterConfiguration);
    }

    /**
     * @param string $class
     * @return AdapterConfigurationInterface
     */
    public function getConfigurationByClass(string $class): AdapterConfigurationInterface
    {
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
    public function getAdapterChoices(): array
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
     * @return AdapterInterface
     */
    public function getAdapter(array $options): AdapterInterface
    {
        $configuration = $this->getConfigurationByClass($options['class']);
        if (!$configuration) {
            throw new \InvalidArgumentException(sprintf("Class '%s' is not a valid adapter configuration", $options['class']));
        }

        unset($options['class']);

        return $configuration->createAdapter($options);
    }
}
