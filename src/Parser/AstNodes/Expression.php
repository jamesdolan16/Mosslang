<?php declare(strict_types=1);

namespace Jamesdolan16\Mosslang\Parser\AstNodes;

interface Expression {
    public ?int $line { get; set; }
    public ?int $position { get; set; }
    // public function toString(int $depth): string;
}