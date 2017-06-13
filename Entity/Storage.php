<?php
/**
 * This file is part of ONP.
 *
 * Copyright (c) 2014 Opensoft (http://opensoftdev.com)
 *
 * The unauthorized use of this code outside the boundaries of
 * Opensoft is prohibited.
 */

namespace Opensoft\StorageBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Opensoft\StorageBundle\Storage\Adapter\LocalAdapterConfiguration;

/**
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 *
 * @ORM\Entity(repositoryClass="Opensoft\StorageBundle\Entity\Repository\StorageRepository")
 * @ORM\Table(name="storage")
 */
class Storage
{
    /**
     * @var integer
     *
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(type="string")
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(type="string", unique=true)
     * @Gedmo\Slug(fields={"name"})
     */
    private $slug;

    /**
     * @var array
     *
     * @ORM\Column(type="array", name="adapter_options")
     */
    private $adapterOptions;

    /**
     * @var boolean
     *
     * @ORM\Column(type="boolean")
     */
    private $active = false;

    /**
     * @var \DateTime
     *
     * @ORM\Column(type="datetime", name="created_at")
     */
    private $createdAt;

    /**
     * @var ArrayCollection|StorageFile[]
     *
     * @ORM\OneToMany(targetEntity="Opensoft\StorageBundle\Entity\StorageFile", mappedBy="storage")
     */
    private $files;

    /**
     *
     */
    public function __construct()
    {
        $this->files = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    /**
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return ArrayCollection|StorageFile[]
     */
    public function getFiles()
    {
        return $this->files;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getSlug()
    {
        return $this->slug;
    }

    /**
     * @return string
     */
    public function getAdapterType()
    {
        $class = $this->adapterOptions['class'];

        return $class::getName();
    }

    /**
     * @return array
     */
    public function getAdapterOptions()
    {
        return $this->adapterOptions;
    }

    /**
     * @param array $adapterOptions
     */
    public function setAdapterOptions($adapterOptions)
    {
        $this->adapterOptions = $adapterOptions;
    }

    /**
     * @return bool
     */
    public function isLocal()
    {
        return $this->adapterOptions['class'] == LocalAdapterConfiguration::class;
    }

    /**
     * @throws \BadMethodCallException
     * @return string
     */
    public function getLocalPath()
    {
        if (!$this->isLocal()) {
            throw new \BadMethodCallException('Local paths may not be retrieved from remote storage');
        }

        return $this->adapterOptions['directory'];
    }

    /**
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param \DateTime $createdAt
     */
    public function setCreatedAt(\DateTime $createdAt)
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @return bool
     */
    public function isActive()
    {
        return $this->active;
    }

    /**
     * @param boolean $active
     * @return bool
     */
    public function setActive($active)
    {
        $this->active = (bool) $active;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->name;
    }
}
