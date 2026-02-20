<?php declare(strict_types=1);

namespace Jamesdolan16\Mosslang\Evaluator;

use Jamesdolan16\Mosslang\Parser\AstNodes\Lambda as AstNodesLambda;

class Lambda
{
    public function __construct(
        public AstNodesLambda $ast,
        public array $params = [],
        /** @var list<Statement> */
        public array $body = [],
        public Env $env = new Env(),
    ) {}
}