<?php

namespace LinkORB\Bundle\WikiBundle\AccessControl;

use Symfony\Component\ExpressionLanguage\SyntaxError;

interface EvalInterface
{

    /**
     * @param string $expression
     * @return void
     * @throws SyntaxError
     */
    public function lint(string $expression): void;
    public function eval(string $expression): bool;

    public function getFunctionNames(): array;

    public function getExamplesHtml(): string;
}
