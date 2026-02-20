<?php declare(strict_types=1);

namespace Jamesdolan16\Mosslang\Parser\AstNodes;

final class Concatenation implements Expression
{
    public Expression $left;
    public Expression $right;
    public ?int $position;
    public ?int $line;
}