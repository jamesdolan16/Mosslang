<?php declare(strict_types=1);

namespace Jamesdolan16\Mosslang\Parser\AstNodes;

final class Unary implements Expression
{
    public string $operator;
    public Expression $value;
    public ?int $position;
    public ?int $line;
}