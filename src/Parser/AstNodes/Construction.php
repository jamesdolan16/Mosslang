<?php declare(strict_types=1);

namespace Jamesdolan16\Mosslang\Parser\AstNodes;

final class Construction implements Expression
{
    /** @var list<Expression> */
    public array $elements = [];
    public ?int $position;
    public ?int $line;
}