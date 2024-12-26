<?php

namespace LinkORB\Bundle\WikiBundle\Form;

use App\Validator\Constraint\CodeConstraint;
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
use Symfony\Component\Validator\Context\ExecutionContext;

class WikiPageType extends AbstractType
{
    private $wikiPageService;
    private $arrayPageTemplates = [];

    public function __construct(WikiService $wikiService, WikiPageService $wikiPageService)
    {
        $this->wikiPageService = $wikiPageService;

        if ($wikiTemplate = $wikiService->getWikiByName('templates')) {
            foreach ($wikiTemplate->getWikiPages() as $wikiPage) {
                $this->arrayPageTemplates[$wikiPage->getId()] = $wikiPage->getName();
            }

            asort($this->arrayPageTemplates);
        }
    }

    /*
    protected function pageTemplates(Wiki $wiki): array
    {
        $array = [];

        foreach ($wiki->getWikiPages() as $wikiPage) {
            $array[$wikiPage->getId()] = $wikiPage->getName();
        }

        asort($array);

        return $array;
    }
    */

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
                    new Assert\Regex([
                        'pattern' => '/^[a-z0-9\-_]+$/',
                        'htmlPattern' => '^[a-z0-9\-_]+$',
                        'message' => 'The string {{ value }} contains an illegal character: it can only contain small letters, numbers, - and _ sign',
                    ]),
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

        /*
        if (!$entity->getId()) {
            $builder
                ->add('content', TextareaType::class, [
                    'required' => false,
                    'trim' => true,
                ]);
        }
        */
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

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => WikiPage::class,
        ]);
    }
}
