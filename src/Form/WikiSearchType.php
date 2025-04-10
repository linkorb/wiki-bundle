<?php

namespace LinkORB\Bundle\WikiBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class WikiSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $wikiArray = $options['wikiArray'];

        $builder
            ->add('search', TextType::class, [
                'required' => true,
                'trim' => true,
                'label' => false,
                'attr' => [
                    'placeholder' => 'Search keywords...',
                    'autofocus' => 'autofocus',
                ],
            ])
            ->add('wikiName', ChoiceType::class, [
                'required' => false,
                'trim' => true,
                'label' => false,
                'placeholder' => '- all my wikis-',
                'choices' => array_flip($wikiArray),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'wikiArray' => 'wikiArray',
        ]);
    }

    // This function was to be ovveridden
    public function getBlockPrefix(): string
    {
        return '';
    }
}
