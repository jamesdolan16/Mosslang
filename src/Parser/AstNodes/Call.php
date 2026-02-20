<?php declare(strict_types=1);

namespace Jamesdolan16\Mosslang\Parser\AstNodes;

final class Call implements Expression
{
    public Expression $callee;
    /** @var list<Expression> */
    public array $args = [];
    public ?int $position;
    public ?int $line;
}
