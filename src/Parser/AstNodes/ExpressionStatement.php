<?php declare(strict_types=1);

namespace Jamesdolan16\Mosslang\Parser\AstNodes;

final class ExpressionStatement implements Statement
{
    public Expression $expression;
    public ?int $position;
    public ?int $line;
}