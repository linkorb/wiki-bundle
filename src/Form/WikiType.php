<?php

namespace LinkORB\Bundle\WikiBundle\Form;

use LinkORB\Bundle\WikiBundle\AccessControl\EvalInterface;
use LinkORB\Bundle\WikiBundle\Entity\Wiki;
use LinkORB\Bundle\WikiBundle\Repository\WikiRepository;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class WikiType extends AbstractType
{
    public function __construct(
        private readonly WikiRepository $wikiRepository,
        private readonly EvalInterface $accessControlEval
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $wikiRepository = $this->wikiRepository;
        $accessControlEval = $this->accessControlEval;
        $entity = $options['data'];

        $builder
            ->add('name', TextType::class, [
                'required' => true,
                'trim' => true,
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Regex(pattern: '/^[a-z0-9\-_]+$/', htmlPattern: '^[a-z0-9\-_]+$', message: 'The string {{ value }} contains an illegal character: it can only contain small letters, numbers, - and _ sign'),
                    new Assert\Callback(
                        function (string $name, ExecutionContextInterface $context) use ($wikiRepository, $entity): void {
                            if ($findEntity = $wikiRepository->findOneByName($name)) {
                                if ($findEntity->getId() != $entity->getId()) {
                                    $context->addViolation('Name already exist');
                                }
                            }
                        }
                    ),
                ],
            ])
            ->add('owner', TextType::class, [
                'disabled' => true
            ])
            ->add('description', TextType::class, [
                'required' => true,
                'trim' => true,
                'constraints' => [
                    new Assert\NotBlank(),
                ],
            ])
            ->add('access_control_expression', TextareaType::class, [
                "required" => false,
                "attr" => [
                    'rows' => 2
                ],
                "help_html" => true,
                "help" =>
                    "<details><summary>Expression examples</summary>"
                    . $accessControlEval->getExamplesHtml() .
                    "</details>",
                "constraints" => [
                    new Assert\Callback(
                        function (string|null $expression, ExecutionContextInterface $context) use ($accessControlEval): void {
                            if (is_null($expression)) {
                                return;
                            }

                            try {
                                $accessControlEval->lint($expression);
                            } catch (SyntaxError $e) {
                                $syntax_error_message = $e->getMessage();
                                if (str_starts_with($syntax_error_message, 'Unexpected token "end of expression" of value')) {
                                    $context->addViolation("Unexpected end of expression. Have you forgot to add a check?");
                                } else {
                                    $context->addViolation($e->getMessage());
                                }
                            }
                        }
                    )
                ]
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
