<?php

namespace Opensoft\StorageBundle\Form\Type;

use Opensoft\StorageBundle\Storage\AdapterResolver;
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
     * @var AdapterResolver
     */
    private $adapterResolver;

    /**
     * @param AdapterResolver $adapterResolver
     */
    public function __construct(AdapterResolver $adapterResolver)
    {
        $this->adapterResolver = $adapterResolver;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $adapterResolver = $this->adapterResolver;

        $builder
            ->add('class', ChoiceType::class, [
                'label' => 'Adapter Type',
                'choices' => array_flip($adapterResolver->getAdapterChoices())
            ])
        ;

        $formModifier = function(FormInterface $form, array $options = null) {

            // remove all previously set form fields (except the class)
            foreach ($form->all() as $formElement) {
                if ($formElement->getName() !== 'class') {
                    $form->remove($formElement->getName());
                }
            }

            $selectedConfiguration = isset($options['class']) ? $this->adapterResolver->getConfigurationByClass($options['class']) : null;

            if (!$selectedConfiguration) {
                $selectedConfiguration = $this->adapterResolver->getConfigurations()->first();
            }

            $selectedConfiguration->buildForm($form, $options);
        };

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function(FormEvent $event) use ($formModifier) {
            $formModifier($event->getForm(), $event->getData());
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function(FormEvent $event) use ($formModifier) {
            $formModifier($event->getForm(), $event->getData());
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'storage_adapter_type';
    }
}
