<?php

namespace LinkORB\Bundle\WikiBundle\Form;

use LinkORB\Bundle\WikiBundle\Entity\Wiki;
use LinkORB\Bundle\WikiBundle\Repository\WikiRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContext;

class WikiType extends AbstractType
{
    public function __construct(private readonly WikiRepository $wikiRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $wikiRepository = $this->wikiRepository;
        $entity = $options['data'];

        $builder
            ->add('name', TextType::class, [
                'required' => true,
                'trim' => true,
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Regex([
                        'pattern' => '/^[a-z0-9\-_]+$/',
                        'htmlPattern' => '^[a-z0-9\-_]+$',
                        'message' => 'The string {{ value }} contains an illegal character: it can only contain small letters, numbers, - and _ sign',
                    ]),
                    new Assert\Callback(
                        function ($name, ExecutionContext $context) use ($wikiRepository, $entity): void {
                            if ($findEntity = $wikiRepository->findOneByName($name)) {
                                if ($findEntity->getId() != $entity->getId()) {
                                    $context->addViolation('Name already exist');
                                }
                            }
                        }
                    ),
                ],
            ])
            ->add('description', TextType::class, [
                'required' => true,
                'trim' => true,
                'constraints' => [
                    new Assert\NotBlank(),
                ],
            ])
            ->add('read_role', TextType::class, [
                'required' => false,
                'trim' => true,
                'help' => 'ex. ROLE_EXAMPLE, ROLE_SUPERUSER',
            ])
            ->add('write_role', TextType::class, [
                'required' => false,
                'trim' => true,
                'help' => 'ex. ROLE_EXAMPLE, ROLE_SUPERUSER',
            ])
            ->add('config', TextareaType::class, [
                'required' => false,
                'trim' => true,
                'attr' => [
                    'class' => 'ace-editor',
                    'data-mode' => 'yaml',
                    'data-lines' => '10',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Wiki::class,
        ]);
    }
}
