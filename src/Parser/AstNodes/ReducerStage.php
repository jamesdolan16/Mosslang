<?php declare(strict_types=1);

namespace Jamesdolan16\Mosslang\Parser\AstNodes;

final class ReducerStage
{
    public Expression $init;
    public Lambda|Identifier $reducer;
    public ?int $position;
    public ?int $line;
}

