<?php

namespace LinkORB\Bundle\WikiBundle\AccessControl;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

readonly class IsGrantedEval implements EvalInterface
{

    private ExpressionLanguage $expressionLanguage;

    public function __construct(private Security $security)
    {
        $this->setupExpressionLanguage();
    }

    private function setupExpressionLanguage(): void
    {
        $this->expressionLanguage = new ExpressionLanguage();
        $this->expressionLanguage->register(
            'is_granted',
            fn() => null, // ignore compilation related function
            fn (string $attribute, string|null $subject) : bool  => $this->security->isGranted($attribute, $subject)
        );
    }

    public function lint(string $expression): void
    {
        $this->expressionLanguage->lint($expression, []);
    }

    public function eval(string $expression): bool
    {
        return $this->expressionLanguage->evaluate($expression);
    }

    public function getFunctionNames(): array
    {
        return ['is_granted'];
    }

    public function getExamplesHtml(): string
    {
        return '
           <pre>is_granted("manage", "users")</pre>
           <pre>is_granted("ROLE_ADMIN") or is_granted("manage", "platform")</pre>
        ';
    }
}
