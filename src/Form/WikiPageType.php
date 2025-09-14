<?php

namespace LinkORB\Bundle\WikiBundle\Form;

use LinkORB\Bundle\WikiBundle\Entity\Wiki;
use LinkORB\Bundle\WikiBundle\Entity\WikiPage;
use LinkORB\Bundle\WikiBundle\Services\WikiPageService;
use LinkORB\Bundle\WikiBundle\Services\WikiService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class WikiPageType extends AbstractType
{
    /** @var array<int, string> */
    private array $arrayPageTemplates = [];

    public function __construct(
        private readonly WikiService $wikiService,
        private readonly WikiPageService $wikiPageService
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $wikiTemplate = $this->wikiService->getWikiByName('templates');
        if ($wikiTemplate instanceof Wiki) {
            foreach ($wikiTemplate->getWikiPages() as $wikiPage) {
                $id = $wikiPage->getId();
                assert(is_int($id));
                $name = $wikiPage->getName();
                assert(is_string($name));

                $this->arrayPageTemplates[$id] = $name;
            }

            asort($this->arrayPageTemplates);
        }

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
                    new Assert\Regex(pattern: '/^[a-z0-9\-_]+$/', htmlPattern: '^[a-z0-9\-_]+$', message: 'The string {{ value }} contains an illegal character: it can only contain small letters, numbers, - and _ sign'),
                    new Assert\Callback(
                        function ($name, ExecutionContextInterface $context) use ($entity): void {
                            if ($findEntity = $this->wikiPageService->getOneByWikiIdAndPageName($entity->getWiki()->getId(), $name)) {
                                if ($findEntity->getId() != $entity->getId()) {
                                    $context->addViolation('Name already exist');
                                }
                            }
                        }
                    ),
                ],
            ])
            ->add('owner', TextType::class, [
                'disabled' => true,
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
                ->add('page_template', ChoiceType::class, [
                    'required' => false,
                    'trim' => true,
                    'mapped' => false,
                    'placeholder' => '- no template selected-',
                    'choices' => array_flip($this->arrayPageTemplates),
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

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => WikiPage::class,
        ]);
    }
}
