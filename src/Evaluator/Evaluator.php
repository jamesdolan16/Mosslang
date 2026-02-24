<?php declare(strict_types=1);

namespace Jamesdolan16\Mosslang\Evaluator;

use Jamesdolan16\Mosslang\Parser\AstNodes\Arithmetic;
use Jamesdolan16\Mosslang\Parser\AstNodes\DefinitionStatement;
use Jamesdolan16\Mosslang\Parser\AstNodes\Expression;
use Jamesdolan16\Mosslang\Parser\AstNodes\ExpressionStatement;
use Jamesdolan16\Mosslang\Parser\AstNodes\Program;
use Jamesdolan16\Mosslang\Parser\AstNodes\Statement;
use Jamesdolan16\Mosslang\Parser\AstNodes\Identifier;
use Jamesdolan16\Mosslang\Evaluator\Env;
use Jamesdolan16\Mosslang\Evaluator\Exceptions\EvaluatorException;
use Jamesdolan16\Mosslang\Evaluator\Exceptions\UserError;
use Jamesdolan16\Mosslang\Parser\AstNodes\Call;
use Jamesdolan16\Mosslang\Parser\AstNodes\Comparison;
use Jamesdolan16\Mosslang\Parser\AstNodes\Concatenation;
use Jamesdolan16\Mosslang\Parser\AstNodes\Conditional;
use Jamesdolan16\Mosslang\Parser\AstNodes\Construction;
use Jamesdolan16\Mosslang\Parser\AstNodes\Inspect;
use Jamesdolan16\Mosslang\Parser\AstNodes\Lambda as LambdaNode;
use Jamesdolan16\Mosslang\Parser\AstNodes\Pipeline;
use Jamesdolan16\Mosslang\Parser\AstNodes\ReducerStage;
use Jamesdolan16\Mosslang\Parser\AstNodes\ScalarLiteral;
use Jamesdolan16\Mosslang\Parser\AstNodes\Symbol;
use Jamesdolan16\Mosslang\Parser\AstNodes\Term;
use Jamesdolan16\Mosslang\Parser\AstNodes\Unary;
use Jamesdolan16\Mosslang\Parser\Lexer;
use Jamesdolan16\Mosslang\Parser\NewLexer;
use Jamesdolan16\Mosslang\Parser\Parser;

final class Evaluator
{
    private NewLexer $lexer;
    private Parser $parser;

    /** @var list<Env> */
    private array $envs = [];

    /** @var list<array{call: Expression, lambda: Lambda|NativeLambda}> */
    private array $callStack = [];

    public function __construct(NewLexer $lexer, Parser $parser) {
        $this->lexer = $lexer;
        $this->parser = $parser;
        $this->envs[] = new Env();
    }

    public function dumpEnvs(): string
    {
        return json_encode($this->envs);
        //dump($this->envs);
    }

    public function loadFile(string $filename): Program
    {
        if (!is_readable($filename)) {
            fwrite(STDERR, "Error: Cannot read file '$filename'\n");
            exit(1);
        }

        $source = file_get_contents($filename);

        if ($source === false) {
            fwrite(STDERR, "Error: Failed to read file\n");
            exit(1);
        }

        $source = str_replace(["\r\n", "\r"], "\n", $source);

        $tokens = $this->lexer->tokenize($source);
        $program = $this->parser->parse($tokens);
        return $program;
    }

    public function evaluateFile(string $filename): void
    {
        $program = $this->loadFile($filename);
        $this->evaluateProgram($program);
    }

    /**
     * Extract all top-level definitions then prefix their indentifers
     * and evaluate in the calling environment
     */
    public function loadModule(string $filename, string $alias): Box
    {
        $moduleProgram = $this->loadFile($filename);
        $moduleExprStmt = $moduleProgram->statements[0];

        if (!$moduleExprStmt instanceof ExpressionStatement)
            return $this->createUserError(
                'moduleDefinitionError',
                'Modules must be defined on their own in a file as a zero parameter lambda, binding to an identifier is 
                invalid'
            );


        $defLambda = $moduleExprStmt->expression;

        if (!$defLambda instanceof LambdaNode)
            return $this->createUserError(
                'moduleDefinitionError',
                'Modules must be defined on their own in a file as a zero parameter lambda, binding to an identifier is 
                invalid'
            );
        
        $oldEnvs = $this->envs;
        $this->envs = [new Env()];

        $this->loadNativeLambdas();

        $loadCall = new Call();
        $loadCall->callee = $defLambda;
        
        $moduleMappingBox = $this->evaluateCall($loadCall);
        /** @var list<array{0: string, 1: Lambda}> */
        $mappings = $moduleMappingBox->value;

        $this->envs = $oldEnvs;

        $importedFuncCount = 0;
        foreach($defLambda->body as $stmt) {
            if ($stmt instanceof DefinitionStatement) {
                $mappingName = $stmt->name->value;
                $funcMap = array_find(
                    $mappings,
                    static fn (Box $mapping) => $mapping->value[0]->value === $mappingName
                )?->value[1];

                if (!$funcMap) $this->error("Failed to map '$mappingName' in module import '$alias'", $defLambda);

                $this->currentEnv()->set("{$alias}_$mappingName", $funcMap);
                $importedFuncCount++;
            }
        }

        return new Box($importedFuncCount);
    }

    private function createUserError(string $error, string $message): Box
    {
        $errorSymbol = new Symbol();
        $errorSymbol->name = $error;
        return new Box([$error, $message]);
    }

    public function loadNativeLambdas(): void
    {
        $env = $this->currentEnv();
        $env->set(
            'load',
            new Box(
                new NativeLambda(
                    ['filePath'],
                    function (Box $box) {
                        if ($box->type !== 'string') 
                            return $this->createUserError('typeError', "Expected string, found {$box->type}");
                        
                        $this->evaluateFile($box->value);
                    }
                )
            )
        );
        $env->set(
            'loadm',
            new Box(
                new NativeLambda(
                    ['filePath', 'alias'],
                    function (Box $filePathB, Box $aliasB) {
                        if ($filePathB->type !== 'string') {
                            return $this->createUserError('typeError', "Expected string, found {$filePathB->type}");
                        }
                        if ($aliasB->type !== 'string') {
                            return $this->createUserError('typeError', "Expected string, found {$aliasB->type}");
                        }

                        return $this->loadModule($filePathB->value, $aliasB->value);
                    }
                )
            )
        );
        $env->set(
            'print', 
            new Box(
                new NativeLambda(
                    ['value'], 
                    function (Box $value) {
                        echo $value->toString();
                        return new Box(null);
                    }
                )
            )
        );
        $env->set(
            'println',
            new Box(
                new NativeLambda(
                    ['value'],
                    function (Box $value) {
                        echo $value->toString() . "\n";
                        return new Box(null);
                    }
                )
            )
        );
        $env->set(
            'explode',
            new Box(
                new NativeLambda(
                    ['construction'],
                    function (Box $construction) {
                        if ($construction->type !== 'construction') 
                            return new Box('typeError', "Expected construction, found {$construction->type}");
                        
                        return explode("", $construction->value);
                    }
                )
            )
        );
        $env->set(
            'raise',
            new Box(
                new NativeLambda(
                    ['error', 'message'],
                    function (Box $error, Box $message) {
                        throw new UserError($error, $message);
                    }
                )
            )
        );
    }

    public function evaluateProgram(Program $program, bool $replMode = false): string
    {
        try {
            return implode("\n", 
                array_map(function (Statement $statement) use ($replMode) { 
                    try {
                        $result = $this->evaluateStatement($statement);
                        if ($replMode) echo $result->toString() . PHP_EOL;
                    } catch (EvaluatorException $e) {
                        // Continue
                    }
                }, $program?->statements)
            );
        } catch (UserError $e) {
            fwrite(STDERR, $e->error->toString() . " " . $e->userMessage->toString());
            die();
        }
    }

    private function evaluateStatement(Statement $statement): ?Box
    {
        return match(true) {
            $statement instanceof DefinitionStatement => $this->evaluateDefinitionStatement($statement),
            $statement instanceof ExpressionStatement => $this->evaluateExpressionStatement($statement)
        };
    }

    private function evaluateDefinitionStatement(DefinitionStatement $statement): ?Box
    {
        $identifier = $statement->name;
        if ($this->currentEnv()->get($identifier->value)) throw new \LogicException("Cannot redefine '{$identifier}'");
        
        try {
            $this->currentEnv()->set($identifier->value, new Box(null));
            $eval = $this->evaluateExpression($statement->value);
            $this->currentEnv()->swapContents($identifier->value, $eval);

            return $eval;
        } catch (EvaluatorException $e) {
            $this->currentEnv()->unset($identifier->value);
            throw $e;
        }
    }
    
    private function evaluateExpressionStatement(ExpressionStatement $statement): ?Box
    {
        return $this->evaluateExpression($statement->expression);
    }

    private function evaluateExpression(Expression $expression): ?Box
    {
        return match(true) {
            $expression instanceof LambdaNode => $this->evaluateLambda($expression),
            $expression instanceof Call => $this->evaluateCall($expression),
            $expression instanceof Comparison => $this->evaluateComparison($expression),
            $expression instanceof Conditional => $this->evaluateConditional($expression),
            $expression instanceof Construction => $this->evaluateConstruction($expression),
            $expression instanceof Concatenation => $this->evaluateConcatenation($expression),
            $expression instanceof Pipeline => $this->evaluatePipeline($expression),
            $expression instanceof Unary => $this->evaluateUnary($expression),
            $expression instanceof Arithmetic => $this->evaluateArithmetic($expression),
            $expression instanceof Term => $this->evaluateTerm($expression),
            $expression instanceof Identifier => $this->evaluateIdentifier($expression),
            $expression instanceof ScalarLiteral => $this->evaluateScalarLiteral($expression),
            $expression instanceof Symbol => $this->evaluateSymbol($expression),
            default => throw new \RuntimeException("ERR:" . gettype($expression))
        };
    }

    private function evaluateLambda(LambdaNode $lambdaNode): Box
    {
        $lambda = new Lambda(ast: $lambdaNode, env: $this->currentEnv());
        // foreach($lambdaNode->captures as $capture) {
        //     $lambda->env->set($capture->value, $this->evaluateExpression($capture));
        // }
        $lambda->params = $lambdaNode->params;
        $lambda->body = $lambdaNode->body;

        return new Box($lambda);
    }

    private function evaluateCall(Call $call): ?Box
    {
        $functionName = $this->evaluateExpression($call->callee);
        $lambdaB = $this->evaluateExpression($call->callee);
        /** @var Lambda|NativeLambda */
        $lambda = $lambdaB->value;

        //if (!$lambda) return $this->error("Call to undefined function '{$functionName}'", $call);

        return match(true) {
            $lambda instanceof Lambda => $this->callLambda($call, $lambda, $call->args),
            $lambda instanceof NativeLambda => $this->callNativeLambda($call, $lambda, $call->args),
            default => $this->error("Failed to call lambda, expected identifier, found {$lambdaB->toString(true)}", $call)
        };
    }

    /**
     * @param list<Box|Expression> $args
     */
    private function callLambda(Expression $call, Lambda $lambda, array $args): ?Box
    {
        if (count($args) !== count($lambda->params))
            $this->error(
                "Arity mismatch, expected " . count($lambda->params) . " argument(s), received " . count($args),
                $call
            );

        /** @var array<string, ?Box> */
        $evaluatedArgs = array_map(
            fn (Box|Expression $e) =>
                $e instanceof Box ? $e : $this->evaluateExpression($e),
            $args
        );

        $callEnv = new Env(parent: $lambda->env);

        foreach (array_combine($lambda->params, $evaluatedArgs) as $param => $arg) {
            $callEnv->set($param, $arg);
        }

        $this->callStack[] = [
            'call' => $call,
            'lambda' => $lambda
        ];

        array_push($this->envs, $callEnv);

        $return = null;
        foreach($lambda->body as $statement) {
            $return = $this->evaluateStatement($statement);     // Repeatedly overwrite $return as only the last expression statement in a lambda is actually returned
        }

        array_pop($this->envs);
        array_pop($this->callStack);

        return $return;
    }

    /**
     * @param list<Box|Expression> $args
     */
    private function callNativeLambda(Expression $call, NativeLambda $lambda, array $args): ?Box
    {
        if (count($args) !== count($lambda->params))
            $this->error(
                "Artiy mismatch, expected " . count($lambda->params) . " arguments, received " . count($args),
                $call
            );

        $evalArgs = array_map(fn (Box|Expression $e) => match (true) {
            $e instanceof Box => $e,
            $e instanceof Expression => $this->evaluateExpression($e),
        }, $args);
        
        return ($lambda->body)(...$evalArgs);
    }

    private function evaluateComparison(Comparison $comparison): Box
    {
        $evalLeft = $this->evaluateExpression($comparison->left);
        $evalRight = $this->evaluateExpression($comparison->right);

        return new Box(
            match($comparison->operator) {
                '<' => $evalLeft->value < $evalRight->value,
                '<=' => $evalLeft->value <= $evalRight->value,
                '=' => $this->evaluateEqual($evalLeft, $evalRight),
                '!=' => $evalLeft->value !== $evalRight->value,
                '>=' => $evalLeft->value >= $evalRight->value,
                '>' => $evalLeft->value > $evalRight->value,
                default => $this->error("Invalid comparison operator {$comparison->operator}", $comparison)
            }
        );
    }

    private function evaluateEqual(Box $left, Box $right): bool
    {
        if ($left->value instanceof Symbol) {
            try {
                return $left->value->equal($right->value);
            } catch (\InvalidArgumentException $e) {
                return false;
            }
        }

        return $left->value === $right->value;
    }

    private function evaluateConditional(Conditional $conditional): Box
    {
        $evalCond = $this->evaluateExpression($conditional->condition);

        return match (!!$evalCond->value) {
            true => $this->evaluateExpression($conditional->then),
            false => $conditional->else ?? null ? $this->evaluateExpression($conditional->else) : new Box(null),
        };
    }

    private function evaluateConstruction(Construction $construction): Box
    {
        return new Box(
            array_map(fn (Expression $e) => $this->evaluateExpression($e), $construction->elements));
    }

    private function evaluateConcatenation(Concatenation $concatenation): Box
    {
        $evalLeft = $this->evaluateExpression($concatenation->left);
        $evalRight = $this->evaluateExpression($concatenation->right);

        if ($evalLeft->type === 'construction') $concat = [...$evalLeft->value];
        else $concat = [$evalLeft];

        if ($evalRight->type === 'construction') $concat = [...$concat, ...$evalRight->value];
        else $concat = [...$concat, $evalRight];

        return new Box($concat);
    }

    private function evaluatePipeline(Pipeline $pipeline): Box
    {
        $evalInput = $this->evaluateExpression($pipeline->input);

        $carry = $evalInput;
        foreach($pipeline->stages as $stage) {
            if (!is_array($carry->value)) $this->error("Provided non-reduceable value as reducer input, expected Construction found {$carry->type}({$carry->value})", $pipeline);

            $carry = $this->evaluateReducerStage($stage, $carry->value);
        }

        return $carry;
    }

    /**
     * @param list<Box> $input
     */
    private function evaluateReducerStage(ReducerStage $stage, array $input): ?Box
    {
        $index = new Box(0);
        $return = $stage->init;

        // if ($stage->reducer instanceof Identifier) {
        //     $reducer = $this->evaluateExpression($stage->reducer)->value;

        //     if (!($reducer->value instanceof LambdaNode)) 
        //         $this->error("Expected identifier {$stage->reducer->value} to resolve to a Lambda", $stage->reducer);
        // } else if ($stage->reducer instanceof LambdaNode) {
        $reducer = $this->evaluateExpression($stage->reducer)->value;
        if (!$reducer instanceof Lambda) {
            $this->error("Reducer body must be callable, expected lambda or function identifier", $stage->reducer);
        }

        /** @var Lambda $reducer */

        foreach($input as $element) {
            $allArgs = [$return, $element, $index];
            $args = array_slice($allArgs, 0, count($reducer->params));      // Only pass the amount of args the lambda wants
            $return = $this->callLambda($stage->reducer, $reducer, $args);

            if ($return) {
                try {
                    $tp = TaggedPair::fromConstruction($return);

                    if ($tp?->tag === 'reduced') return $tp->box;
                } catch (\LogicException $e) {
                    // Do nothing
                }
            }

            $index->value += 1;
        }

        return $return;
    }

    private function evaluateUnary(Unary $unary): Box
    {
        $value = $this->evaluateExpression($unary->value);

        return match($unary->operator) {
            '-' => $this->evaluateArithmeticNegation($unary, $value),
            '!' => $this->evaluateLogicalNegation($unary, $value),
            '?' => $this->evaluateInspect($value),
        };
    }

    private function evaluateArithmeticNegation(Unary $unary, ?Box $box): Box
    {
        if (!in_array($box->type, ['integer', 'double'])) {
            $this->error(
                'Cannot arithmetically negate non-numeric value, expected [int, float], found ' . gettype($box->value),
                $unary
            );
        }
        
        return new Box(-($box->value));
    }

    private function evaluateLogicalNegation(Unary $unary, ?Box $box): Box
    {
        if ($box->type !== 'boolean') {
            $this->error(
                'Cannot logically negate non-boolean value, expected boolean, found ' . gettype($box->value),
                $unary
            );
        }

        return new Box(!($box->value));
    }

    private function evaluateInspect(Box $box): Box
    {
        $symbolicType = new Symbol();
        $symbolicType->name = $box->type;

        return new Box($symbolicType);
    }

    private function evaluateArithmetic(Arithmetic $arithmetic): Box
    {
        $evalLeft = $this->evaluateExpression($arithmetic->left);
        $evalRight = $this->evaluateExpression($arithmetic->right);

        if (!in_array(gettype($evalLeft->value), ['integer', 'double', 'string']))
            $this->error("Left side of {$arithmetic->operator} should be of numeric type or string [int, float, string], found {$evalLeft->type}", $arithmetic);
        if (!in_array(gettype($evalRight->value), ['integer', 'double', 'string']))
            $this->error("Right side of {$arithmetic->operator} should be of numeric type or string [int, float, string], found {$evalRight->type}", $arithmetic);
        if (!$evalLeft->SameType($evalRight))
            $this->error("Left and right side of {$arithmetic->operator} should be of same type [int, float, string]", $arithmetic);
        
        if ($evalLeft->type === 'string') return new Box(
            match ($arithmetic->operator) {
                '+' => $evalLeft->value . $evalRight->toString(),
                default => $this->error("Invalid string operation {$arithmetic->operator}", $arithmetic)
            }
        );
        
        return new Box(
            match ($arithmetic->operator) {
                '+' => $evalLeft->value + $evalRight->value,
                '-' => $evalLeft->value - $evalRight->value
            }
        );
    }

    private function evaluateTerm(Term $term): Box
    {
        $evalLeft = $this->evaluateExpression($term->left);
        $evalRight = $this->evaluateExpression($term->right);

        if (!in_array(gettype($evalLeft->value), ['integer', 'double']))
            $this->error('Left side of term should be of numeric type [int, float], found ' . gettype($evalLeft->value), $term);
        if (!in_array(gettype($evalRight->value), ['integer', 'double']))
            $this->error('Right side of term should be of numeric type [int, float], found ' . gettype($evalRight->value), $term);
        if (!$evalLeft->SameType($evalRight))
            $this->error('Left and right side of term should be of same type [int, float]', $term);
        
        
        return new Box(match ($term->operator) {
            '*' => $evalLeft->value * $evalRight->value,
            '/' => $evalLeft->value / $evalRight->value
        });
    }

    private function evaluateIdentifier(Identifier $identifier): ?Box
    {
        $found = $this->currentEnv()->get($identifier->value);

        if ($found !== null) {
            return $found;
        }

        return $this->error("Undefined Identifier {$identifier->value}", $identifier);
    }

    private function evaluateScalarLiteral(ScalarLiteral $scalar): Box
    {
        return new Box($scalar->value);
    }

    private function evaluateSymbol(Symbol $symbol): Box
    {
        return new Box($symbol);
    }

    private function error(string $message, Expression $expression): void
    {
        echo "Error evaluating on line {$expression->line} at position {$expression->position}: $message\n";
        
        if (!empty($this->callStack)) {
            echo "\nStack Trace:\n";

            foreach (array_reverse($this->callStack) as $frame) {
                $call = $frame['call'];
                $binding = $call->callee->value ?? '<anonymous>';
                echo "  in '$binding' (line {$call->line}, position {$call->position})\n";
            }
        }
        
        throw new EvaluatorException();
    }

    private function currentEnv(): ?Env
    {
        return array_last($this->envs);
    }
}