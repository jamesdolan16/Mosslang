<?php declare(strict_types=1);

namespace Jamesdolan16\Mosslang\Parser;

class Helpers
{
    /**
     * Returns the head and tail of the provided array
     * 
     * @template TValue
     * @param list<TValue> $a
     * @return array{0: TValue|null, 1: array<TValue>}
     */
    public static function decap(array $a): array
    {
        return [array_first($a), array_slice($a, 1)];
    }
}