<?php

namespace Opensoft\StorageBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * @ORM\Entity(repositoryClass="Opensoft\StorageBundle\Entity\Repository\StoragePolicyRepository")
 * @ORM\Table(name="storage_policy")
 *
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
class StoragePolicy
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
     * @var integer
     *
     * @ORM\Column(type="integer", name="type")
     */
    private $type;

    /**
     * @var \DateTime
     *
     * @ORM\Column(type="datetime", name="created_at")
     */
    private $createdAt;

    /**
     * @var Storage
     *
     * @ORM\ManyToOne(targetEntity="Opensoft\StorageBundle\Entity\Storage")
     * @ORM\JoinColumn(name="create_storage_id", referencedColumnName="id", nullable=false)
     */
    private $createInStorage;

    /**
     * @var string
     *
     * @ORM\Column(type="string", name="move_after_interval", nullable=true)
     */
    private $moveAfterInterval;

    /**
     * @var Storage
     *
     * @ORM\ManyToOne(targetEntity="Opensoft\StorageBundle\Entity\Storage")
     * @ORM\JoinColumn(name="move_from_storage_id", referencedColumnName="id", nullable=true)
     */
    private $moveFromStorage;

    /**
     * @var Storage
     *
     * @ORM\ManyToOne(targetEntity="Opensoft\StorageBundle\Entity\Storage")
     * @ORM\JoinColumn(name="move_to_storage_id", referencedColumnName="id", nullable=true)
     */
    private $moveToStorage;

    /**
     * @var string
     *
     * @ORM\Column(type="string", name="delete_after_interval", nullable=true)
     */
    private $deleteAfterInterval;

    public function __construct()
    {
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
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @return int
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param int $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * @return Storage
     */
    public function getCreateInStorage()
    {
        return $this->createInStorage;
    }

    /**
     * @param Storage $createInStorage
     */
    public function setCreateInStorage(Storage $createInStorage)
    {
        $this->createInStorage = $createInStorage;
    }

    /**
     * @return string
     */
    public function getMoveAfterInterval()
    {
        return $this->moveAfterInterval;
    }

    /**
     * Set a php parsable date interval
     *
     * @param string $moveAfterInterval
     */
    public function setMoveAfterInterval($moveAfterInterval)
    {
        $this->moveAfterInterval = $moveAfterInterval;
    }

    /**
     * @return Storage
     */
    public function getMoveFromStorage()
    {
        return $this->moveFromStorage;
    }

    /**
     * @param Storage|null $moveFromStorage
     */
    public function setMoveFromStorage(Storage $moveFromStorage = null)
    {
        $this->moveFromStorage = $moveFromStorage;
    }

    /**
     * @return Storage
     */
    public function getMoveToStorage()
    {
        return $this->moveToStorage;
    }

    /**
     * @param Storage|null $moveToStorage
     */
    public function setMoveToStorage(Storage $moveToStorage = null)
    {
        $this->moveToStorage = $moveToStorage;
    }

    /**
     * @return string
     */
    public function getDeleteAfterInterval()
    {
        return $this->deleteAfterInterval;
    }

    /**
     * @param string $deleteAfterInterval
     */
    public function setDeleteAfterInterval($deleteAfterInterval)
    {
        $this->deleteAfterInterval = $deleteAfterInterval;
    }

    /**
     * @Assert\Callback()
     *
     * @param ExecutionContextInterface $context
     */
    public function validate(ExecutionContextInterface $context)
    {
        // validate move constraints
        if ($this->moveAfterInterval || $this->moveToStorage || $this->moveFromStorage) {
            // validate move interval in past constraint
            $now = new \DateTimeImmutable();
            $proposedMoveTime = new \DateTimeImmutable($this->moveAfterInterval);

            if ($proposedMoveTime < $now) {
                // cannot have a negative time period
                $context->buildViolation('Cannot have a move policy interval that is negative.')
                    ->atPath('moveAfterInterval')
                    ->addViolation();
            }

            if ($this->moveToStorage && $this->moveFromStorage && $this->moveToStorage == $this->moveFromStorage) {
                $context->buildViolation('Cannot move file to a destination storage which is the same as its source.')
                    ->atPath('moveToStorage')
                    ->addViolation();
            }

            if ($this->moveAfterInterval && $this->moveToStorage == null) {
                $context->buildViolation('Must specify a move to storage target if you have selected a move interval.')
                    ->atPath('moveToStorage')
                    ->addViolation();
            }

            if ($this->moveAfterInterval && $this->moveFromStorage == null) {
                $context->buildViolation('Must specify a move from storage source if you have selected a move interval.')
                    ->atPath('moveFromStorage')
                    ->addViolation();
            }

            if (!$this->moveAfterInterval && ($this->moveFromStorage || $this->moveToStorage)) {
                $context->buildViolation('Must specify a move interval for storage movement.')
                    ->atPath('moveAfterInterval')
                    ->addViolation();
            }
        }

        // validate delete constraints
        $now = new \DateTimeImmutable();
        $proposedDeleteTime = new \DateTimeImmutable($this->deleteAfterInterval);

        if ($proposedDeleteTime < $now) {
            // cannot have a negative time period
            $context->buildViolation('Cannot have a delete policy interval that is negative.')
                ->atPath('deleteAfterInterval')
                ->addViolation();
        }
    }
}
