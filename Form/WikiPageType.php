<?php

namespace LinkORB\Bundle\WikiBundle\Form;

use LinkORB\Bundle\WikiBundle\Entity\WikiPage;
use LinkORB\Bundle\WikiBundle\Repository\WikiPageRepository;
use App\Validator\Constraint\CodeConstraint;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContext;

class WikiPageType extends AbstractType
{
    private $wikiPageRepository;

    public function __construct(WikiPageRepository $wikiPageRepository)
    {
        $this->wikiPageRepository = $wikiPageRepository;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $wikiPageRepository = $this->wikiPageRepository;
        $entity = $options['data'];

        $builder
            ->add('name', TextType::class, [
                'required' => true,
                'trim' => true,
                'constraints' => [
                    new Assert\NotBlank(),
                    // TODO: Resolve this
                    // new CodeConstraint(),
                    new Assert\Callback(
                        function ($name, ExecutionContext $context) use ($wikiPageRepository, $entity) {
                            if ($findEntity = $wikiPageRepository->findOneByWikiIdAndName($entity->getWiki()->getId(), $name)) {
                                if ($findEntity->getId() != $entity->getId()) {
                                    $context->addViolation('Name already exist');
                                }
                            }
                        }
                    ),
                ],
            ])
            ->add('content', TextareaType::class, [
                'required' => false,
                'trim' => true,
            ])
            ->add('data', TextareaType::class, [
                'required' => false,
                'trim' => true,
                'attr' => [
                    'class' => 'ace-editor',
                     'data-mode' => 'yaml',
                     'data-lines' => '10',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => WikiPage::class,
        ]);
    }
}
