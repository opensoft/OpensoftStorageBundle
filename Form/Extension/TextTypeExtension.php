<?php declare(strict_types=1);

namespace Opensoft\StorageBundle\Form\Extension;

use Symfony\Component\Form\Extension\Core\Type\TextType;

class TextTypeExtension extends HelpTypeExtension
{
    /**
     * {@inheritdoc}
     */
    public function getExtendedType()
    {
        return TextType::class;
    }
}
