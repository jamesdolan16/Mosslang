<?php declare(strict_types=1);

namespace Jamesdolan16\Mosslang\Parser\AstNodes;

final class Lambda implements Expression
{
    /** @var list<Identifier> */
    public array $params = [];
    /** @var list<Identifier> */
    public array $captures = [];
    /** @var list<Identifier> */
    public array $definitions = [];
    /** @var list<Statement> */
    public array $body = [];
    public ?int $line;
    public ?int $position;
}