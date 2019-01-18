<?php

namespace Opensoft\StorageBundle\Entity;

use DateTime;
use DateTimeImmutable;
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
     * @var int
     *
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", name="type")
     */
    private $type;

    /**
     * @var DateTime
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
        $this->createdAt = new DateTime();
    }

    /**
     * @return int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return DateTime
     */
    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    /**
     * @return int
     */
    public function getType(): ?int
    {
        return $this->type;
    }

    /**
     * @param int $type
     */
    public function setType(int $type): void
    {
        $this->type = $type;
    }

    /**
     * @return Storage
     */
    public function getCreateInStorage(): ?Storage
    {
        return $this->createInStorage;
    }

    /**
     * @param Storage $createInStorage
     */
    public function setCreateInStorage(Storage $createInStorage): void
    {
        $this->createInStorage = $createInStorage;
    }

    /**
     * @return string|null
     */
    public function getMoveAfterInterval(): ?string
    {
        return $this->moveAfterInterval;
    }

    /**
     * Set a php parsable date interval
     *
     * @param string $moveAfterInterval
     */
    public function setMoveAfterInterval(string $moveAfterInterval): void
    {
        $this->moveAfterInterval = $moveAfterInterval;
    }

    /**
     * @return Storage|null
     */
    public function getMoveFromStorage(): ?Storage
    {
        return $this->moveFromStorage;
    }

    /**
     * @param Storage|null $moveFromStorage
     */
    public function setMoveFromStorage(Storage $moveFromStorage = null): void
    {
        $this->moveFromStorage = $moveFromStorage;
    }

    /**
     * @return Storage|null
     */
    public function getMoveToStorage(): ?Storage
    {
        return $this->moveToStorage;
    }

    /**
     * @param Storage|null $moveToStorage
     */
    public function setMoveToStorage(Storage $moveToStorage = null): void
    {
        $this->moveToStorage = $moveToStorage;
    }

    /**
     * @return string|null
     */
    public function getDeleteAfterInterval(): ?string
    {
        return $this->deleteAfterInterval;
    }

    /**
     * @param string $deleteAfterInterval
     */
    public function setDeleteAfterInterval(string $deleteAfterInterval): void
    {
        $this->deleteAfterInterval = $deleteAfterInterval;
    }

    /**
     * @Assert\Callback()
     *
     * @param ExecutionContextInterface $context
     */
    public function validate(ExecutionContextInterface $context): void
    {
        // validate move constraints
        if ($this->moveAfterInterval || $this->moveToStorage || $this->moveFromStorage) {
            // validate move interval in past constraint
            $now = new DateTimeImmutable();
            $proposedMoveTime = new DateTimeImmutable($this->moveAfterInterval);

            if ($proposedMoveTime < $now) {
                // cannot have a negative time period
                $context->buildViolation('Cannot have a move policy interval that is negative.')
                    ->atPath('moveAfterInterval')
                    ->addViolation();
            }

            if ($this->moveToStorage && $this->moveFromStorage && $this->moveToStorage === $this->moveFromStorage) {
                $context->buildViolation('Cannot move file to a destination storage which is the same as its source.')
                    ->atPath('moveToStorage')
                    ->addViolation();
            }

            if ($this->moveAfterInterval && $this->moveToStorage === null) {
                $context->buildViolation('Must specify a move to storage target if you have selected a move interval.')
                    ->atPath('moveToStorage')
                    ->addViolation();
            }

            if ($this->moveAfterInterval && $this->moveFromStorage === null) {
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
        $now = new DateTimeImmutable();
        $proposedDeleteTime = new DateTimeImmutable($this->deleteAfterInterval);

        if ($proposedDeleteTime < $now) {
            // cannot have a negative time period
            $context->buildViolation('Cannot have a delete policy interval that is negative.')
                ->atPath('deleteAfterInterval')
                ->addViolation();
        }
    }
}
