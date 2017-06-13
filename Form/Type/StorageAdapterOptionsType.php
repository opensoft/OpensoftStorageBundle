<?php
/*
 * This file is part of ONP.
 *
 * Copyright (c) 2013 Opensoft (http://opensoftdev.com)
 *
 * The unauthorized use of this code outside the boundaries of
 * Opensoft is prohibited.
 */

namespace Opensoft\StorageBundle\Form\Type;

use Opensoft\StorageBundle\Storage\GaufretteAdapterResolver;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;

/**
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
class StorageAdapterOptionsType extends AbstractType
{
    /**
     * @var GaufretteAdapterResolver
     */
    private $adapterResolver;

    /**
     * @param GaufretteAdapterResolver $adapterResolver
     */
    public function __construct(GaufretteAdapterResolver $adapterResolver)
    {
        $this->adapterResolver = $adapterResolver;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $adapterResolver = $this->adapterResolver;

        $builder
            ->add('class', ChoiceType::class, [
                'label' => 'Adapter Type',
                'choices' => array_flip($adapterResolver->getAdapterChoices()),
            ])
        ;

        $formModifier = function (FormInterface $form, array $options = null) {

            // remove all previously set form fields (except the class)
            foreach ($form->all() as $formElement) {
                if ($formElement->getName() != 'class') {
                    $form->remove($formElement->getName());
                }
            }
            $selectedConfiguration = $this->adapterResolver->getConfigurationByClass($options['class']);

            if (!$selectedConfiguration) {
                $selectedConfiguration = $this->adapterResolver->getConfigurations()->first();
            }

            $selectedConfiguration->buildForm($form, $options);
        };

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($formModifier) {
            $formModifier($event->getForm(), $event->getData());
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($formModifier) {
            $formModifier($event->getForm(), $event->getData());
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'storage_adapter_type';
    }
}
