<?php declare(strict_types=1);

namespace Opensoft\StorageBundle\Form\Extension;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class CheckboxTypeExtension extends HelpTypeExtension
{
    /**
     * {@inheritdoc}
     */
    public function getExtendedType()
    {
        return CheckboxType::class;
    }
}
