<?php declare(strict_types=1);

namespace Jamesdolan16\Mosslang\Parser\AstNodes;

final class DefinitionStatement implements Statement
{
    public Identifier $name;
    public Expression $value;
    public ?int $position;
    public ?int $line;
}