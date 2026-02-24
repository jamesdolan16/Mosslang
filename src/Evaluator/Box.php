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
            $this->value === null => 'null',
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

    public function toString(bool $escapeStrings = false): string
    {
        return $this->stringify($this, $escapeStrings);
    }

    public function stringify(Box $box, bool $escapeStrings, int $depth = 0): string
    {
        return match($box->type) {
            'string' => $escapeStrings ? "\"{$box->value}\"" : $box->value,
            'integer', 'float' => (string)$box->value,
            'boolean' => $box->value ? 'true' : 'false',
            'construction' => $box->stringifyConstruction($box->value, $depth),
            'symbol' => ":{$box->value->name}",
            'null' => ':null',
            'unknown' => ':unknown'
        };
    }

    public function stringifyConstruction(array $con, int $depth): string
    {
        $hasNested = array_find($con, fn (Box $b) => $b->type === 'construction');

        if ($hasNested) {
            $indent = str_repeat(' ', $depth * 2);
            $childIndent = str_repeat(' ', ($depth + 1) * 2);

            $elements = implode("\n", array_map(
                fn (Box $b) => $childIndent . $this->stringify($b, true, $depth + 1),
                $con
            ));

            return "{\n" . $elements . "\n" . $indent . "}";
        }

        return "{" . implode(" ", array_map(
            fn (Box $b) => $this->stringify($b, true, $depth),
            $con
        )) . "}";
    }
}