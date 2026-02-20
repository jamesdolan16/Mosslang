<?php declare(strict_types=1);

namespace Jamesdolan16\Mosslang\Parser\AstNodes;

final class Inspect implements Expression
{
    public Expression $expression;
    public ?int $position;
    public ?int $line;
}