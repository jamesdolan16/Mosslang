<?php declare(strict_types=1);

namespace Jamesdolan16\Mosslang\Parser\AstNodes;

final class Pipeline implements Expression
{
    public Expression $input;
    /** @var list<ReducerStage> */
    public array $stages;
    public ?int $position;
    public ?int $line;
}