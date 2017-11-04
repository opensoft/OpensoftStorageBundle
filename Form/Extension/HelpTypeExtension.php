<?php declare(strict_types=1);

namespace Opensoft\StorageBundle\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

abstract class HelpTypeExtension extends AbstractTypeExtension
{
    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        // makes it legal for AbstractType fields to have an help/help_block option
        $resolver->setDefined(array('help', 'help_block'));

        $resolver->setDefaults(array(
            'help' => null,
            'help_block' => null,
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $option = null;
        if (isset($options['help_block'])) {
            $option = $options['help_block'];
        }

        if (isset($options['help'])) {
            $option = $options['help'];
        }

        $view->vars['help'] = $option;
    }
}
