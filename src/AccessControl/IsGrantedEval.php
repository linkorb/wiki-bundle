<?php

namespace LinkORB\Bundle\WikiBundle\AccessControl;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

readonly class IsGrantedEval implements EvalInterface
{

    private ExpressionLanguage $expressionLanguage;

    public function __construct(private Security $security)
    {
        $this->expressionLanguage = new ExpressionLanguage();
        $this->setupExpressionLanguage();
    }

    private function setupExpressionLanguage(): void
    {
        $this->expressionLanguage->register(
            'is_granted',
            fn() => null, // ignore compilation related function
            fn (array $arguments, string $attribute, string|null $subject) : bool
                => $this->security->isGranted($attribute, $subject)
        );
    }

    public function lint(string $expression): void
    {
        $this->expressionLanguage->lint($expression, []);
    }

    public function eval(string $expression): bool
    {
        $result = $this->expressionLanguage->evaluate($expression);
        if (!is_bool($result)) {
            throw new \InvalidArgumentException(sprintf(
                "Wiki ACL expression should evaluate to a boolean value. Expression '%s' returned type %s",
                $expression,
                gettype($result)
            ));
        }

        return $result;
    }

    public function getExamplesHtml(): string
    {
        return '
           <pre>is_granted("manage", "users")</pre>
           <pre>is_granted("ROLE_ADMIN") or is_granted("manage", "platform")</pre>
        ';
    }
}
