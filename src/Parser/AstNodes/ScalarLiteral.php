<?php declare(strict_types=1);

namespace Jamesdolan16\Mosslang\Parser\AstNodes;

final class ScalarLiteral implements Expression
{
    public int|float|string|bool $value;
    public ?int $position;
    public ?int $line;
}