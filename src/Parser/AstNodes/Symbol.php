<?php declare(strict_types=1);

namespace Jamesdolan16\Mosslang\Parser\AstNodes;

final class Symbol implements Expression
{
    public string $name;
    public ?int $position;
    public ?int $line;

    public function equal(Symbol $symbol): bool
    {
        return $this->name === $symbol->name;
    }

    public function __toString()
    {
        return ":{$this->name}";
    }
}