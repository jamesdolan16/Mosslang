<?php declare(strict_types=1);

namespace Jamesdolan16\Mosslang\Parser\AstNodes;

final class Program
{
    /** @var array<Statement> */
    public array $statements = [];
    public ?int $position;
    public ?int $line;
}