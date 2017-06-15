<?php

namespace Opensoft\StorageBundle\Form\Type;

use Opensoft\StorageBundle\Entity\StoragePolicy;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
class StoragePolicyFormType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            // write policy config
            ->add('createInStorage')
            // move policy config
            ->add('moveFromStorage')
            ->add('moveToStorage')
            ->add('moveAfterInterval')
            // delete policy config
            ->add('deleteAfterInterval')
        ;
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => StoragePolicy::class
        ]);
    }
}
