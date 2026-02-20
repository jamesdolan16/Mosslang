<?php declare(strict_types=1);

namespace Jamesdolan16\Mosslang\Parser\AstNodes;

final class Comparison implements Expression
{
    public string $operator;
    public Expression $left;
    public Expression $right;
    public ?int $position;
    public ?int $line;
}