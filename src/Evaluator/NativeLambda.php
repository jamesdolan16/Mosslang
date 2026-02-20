<?php declare(strict_types=1);

namespace Jamesdolan16\Mosslang\Evaluator;

class NativeLambda
{
    public Env $env;
    /**
     * @param callable $body
     */
    public function __construct(
        public array $params,
        public $body,
    )
    {
        $this->env = new Env();
    }
}

