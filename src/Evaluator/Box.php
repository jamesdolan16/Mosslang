<?php declare(strict_types=1);

namespace Jamesdolan16\Mosslang\Evaluator;

use Jamesdolan16\Mosslang\Parser\AstNodes\Symbol;

class Box {
    public string $type;
    private int $depth = 0;

    public function __construct(
        public mixed $value
    ) {
        $this->deduceType();
    }

    private function deduceType(): void
    {
        $this->type = match(true) {
            is_integer($this->value) => 'integer',
            is_float($this->value) => 'float',
            is_string($this->value) => 'string',
            is_bool($this->value) => 'boolean',
            is_array($this->value) => 'construction',
            $this->value instanceof Symbol => 'symbol',
            $this->value instanceof Lambda => 'lambda',
            $this->value instanceof NativeLambda => 'native_lambda',
            default => 'unknown'
        };
    }

    public function set($value): void
    {
        $this->value = $value;
        $this->deduceType();
    }

    public function SameType(Box $box): bool
    {
        return $this->type === $box->type;
    }

    public function toString(): string
    {
        return $this->stringify($this);
    }

    public function stringify(Box $box): string
    {
        return match($box->type) {
            'string' => "\"{$box->value}\"",
            'integer', 'float' => (string)$box->value,
            'boolean' => $box->value ? 'true' : 'false',
            'construction' => $box->stringifyConstruction($box->value),
            'symbol' => ":{$box->value->name}"
        };
    }

    public function stringifyConstruction(array $con): string
    {
        $this->depth += 1;
        $str = "{" . implode(" ", array_map(
            fn (Box $b) => /*str_repeat(' ', $this->depth * 2) .*/ $this->stringify($b), 
            $con
        )) . "}";
        $this->depth -= 1;
        return $str;
    }
}