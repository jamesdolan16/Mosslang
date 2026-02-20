<?php declare(strict_types=1);

namespace Jamesdolan16\Mosslang\Parser\AstNodes;

final class Identifier implements Expression
{
    public function __construct(
        public ?string $value = null,
        public ?int $position = null,
        public ?int $line = null
    ) {}

    public function __toString()
    {
        return $this->value;
    }
}