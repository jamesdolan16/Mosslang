<?php declare(strict_types=1);

namespace Jamesdolan16\Mosslang\Parser\AstNodes;

final class Conditional implements Expression
{
    public Expression $condition;
    public Expression $then;
    public ?Expression $else;
    public ?int $position;
    public ?int $line;
}