<?php declare(strict_types=1);

namespace Jamesdolan16\Mosslang\Evaluator;

class Env {    
    /** @var array<string, ?Box> */
    private array $vars = [];

    public function __construct(
        public ?Env $parent = null
    ){}

    public function set(string $identifier, ?Box $value): void
    {
        $this->vars[$identifier] = $value;
    }

    public function unset(string $identifier): void
    {
        unset($this->vars[$identifier]);
    }

    public function swapContents(string $identifier, Box $box): void
    {
        $this->vars[$identifier]->set($box->value);
    }

    public function get(string $identifier): ?Box {
        return $this->vars[$identifier] ?? $this->parent?->get($identifier) ?? null;
    }
}