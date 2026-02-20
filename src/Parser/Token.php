<?php declare(strict_types=1);

namespace Jamesdolan16\Mosslang\Parser;

class Token
{
    public function __construct(
        public string $kind,
        public ?string $value = null,
        public ?int $line = null,
        public ?int $position = null
    ){}
}