<?php

namespace App\WikiBundle\Form;

use App\WikiBundle\Entity\Wiki;
use App\WikiBundle\Repository\WikiRepository;
use App\Validator\Constraint\CodeConstraint;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContext;

class WikiType extends AbstractType
{
    protected $wikiRepository;

    public function __construct(WikiRepository $wikiRepository)
    {
        $this->wikiRepository = $wikiRepository;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $wikiRepository = $this->wikiRepository;
        $entity = $options['data'];

        $builder
            ->add('name', TextType::class, [
                'required' => true,
                'trim' => true,
                'constraints' => [
                    new Assert\NotBlank(),
                    new CodeConstraint(),
                    new Assert\Callback(
                        function ($name, ExecutionContext $context) use ($wikiRepository, $entity) {
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
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Wiki::class,
        ]);
    }
}
