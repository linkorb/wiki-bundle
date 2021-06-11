<?php

namespace LinkORB\Bundle\WikiBundle\Form;

use App\Validator\Constraint\CodeConstraint;
use LinkORB\Bundle\WikiBundle\Entity\WikiPage;
use LinkORB\Bundle\WikiBundle\Services\WikiPageService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContext;

class WikiPageType extends AbstractType
{
    private $wikiPageService;

    public function __construct(WikiPageService $wikiPageService)
    {
        $this->wikiPageService = $wikiPageService;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $entity = $options['data'];

        $parentArray = $this->wikiPageService->pageRecursiveArray($entity->getWiki()->getId(), 0, (int) $entity->getId());

        $builder
            ->add('name', TextType::class, [
                'required' => true,
                'trim' => true,
                'label' => 'Page name',
                'help' => 'Part of the URL. Accepts lower case, a-z, 0-9, use dashes for spaces',
                'constraints' => [
                    new Assert\NotBlank(),
                    // TODO: Resolve this
                    // new CodeConstraint(),
                    new Assert\Callback(
                        function ($name, ExecutionContext $context) use ($entity) {
                            if ($findEntity = $this->wikiPageService->getOneByWikiIdAndPageName($entity->getWiki()->getId(), $name)) {
                                if ($findEntity->getId() != $entity->getId()) {
                                    $context->addViolation('Name already exist');
                                }
                            }
                        }
                    ),
                ],
            ])
            ->add('parent_id', ChoiceType::class, [
                'required' => false,
                'trim' => true,
                'label' => 'Parent',
                'placeholder' => ' -- select --',
                'choices' => array_flip($parentArray),
            ]);

        if (!$entity->getId()) {
            $builder
                ->add('content', TextareaType::class, [
                    'required' => false,
                    'trim' => true,
                ]);
        }

        $builder->add('data', TextareaType::class, [
                'required' => false,
                'trim' => true,
                'attr' => [
                    'class' => 'ace-editor',
                    'data-mode' => 'yaml',
                    'data-lines' => '10',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => WikiPage::class,
        ]);
    }
}
