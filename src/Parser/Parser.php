<?php declare(strict_types=1);

namespace Jamesdolan16\Mosslang\Parser;

use Jamesdolan16\Mosslang\Parser\AstNodes\Arithmetic;
use Jamesdolan16\Mosslang\Parser\AstNodes\BinaryOp;
use Jamesdolan16\Mosslang\Parser\AstNodes\Call;
use Jamesdolan16\Mosslang\Parser\AstNodes\Comparison;
use Jamesdolan16\Mosslang\Parser\AstNodes\Concatenation;
use Jamesdolan16\Mosslang\Parser\AstNodes\Conditional;
use Jamesdolan16\Mosslang\Parser\AstNodes\Construction;
use Jamesdolan16\Mosslang\Parser\AstNodes\DefinitionStatement;
use Jamesdolan16\Mosslang\Parser\AstNodes\Expression;
use Jamesdolan16\Mosslang\Parser\AstNodes\ExpressionStatement;
use Jamesdolan16\Mosslang\Parser\AstNodes\Identifier;
use Jamesdolan16\Mosslang\Parser\AstNodes\Inspect;
use Jamesdolan16\Mosslang\Parser\AstNodes\Lambda;
use Jamesdolan16\Mosslang\Parser\AstNodes\Pipeline;
use Jamesdolan16\Mosslang\Parser\AstNodes\Program;
use Jamesdolan16\Mosslang\Parser\AstNodes\ReducerStage;
use Jamesdolan16\Mosslang\Parser\AstNodes\ScalarLiteral;
use Jamesdolan16\Mosslang\Parser\AstNodes\Statement;
use Jamesdolan16\Mosslang\Parser\AstNodes\Symbol;
use Jamesdolan16\Mosslang\Parser\AstNodes\Term;
use Jamesdolan16\Mosslang\Parser\AstNodes\Unary;
use ReflectionClass;

class Parser
{
    public array $tokens;
    public int $index;
    /** @var list<Lambda> */
    private array $captureTargetStack = [];

    public function parse(array $tokens): Program
    {   
        
        $this->tokens = $tokens;
        $this->index = 0;
        return $this->parseProgram();
    }

    private function parseProgram(): Program
    {    
        $program = new Program();
        while($this->index < count($this->tokens)) {
            $statement = $this->tryStatement();
            if ($statement) $program->statements[] = $statement;
        }
        return $program;
    }

    private function tryStatement(): ?Statement
    {    
        if ($this->looksLikeDefinition()) $statement = $this->tryDefinitionStatement();
        else $statement = $this->expressionStatement();

        while ($this->match('semicolon')) $this->consume('semicolon');

        if (!$statement) $this->error('Expected a Definition or an Expression');
        return $statement;
    }

    private function looksLikeDefinition(): bool
    {   
        $i = $this->index + 1;
        if ($this->tokenAt($i)?->kind === 'atom') {
            $i++;
        }
        return $this->tokenAt($i)?->kind === 'assignment';
    }

    private function tryDefinitionStatement(): ?DefinitionStatement
    {   
        $name = $this->tryIdentifier(capture: false);
        if (!$name) return null;
        
        if (!$this->match('assignment')) return null;

        $node = new DefinitionStatement();
        $node->line = $this->peek()?->line;
        $node->position = $this->peek()?->position;
        $node->name = $name;

        $this->advance();

        $node->value = $this->expression();

        if ($captureTarget = array_last($this->captureTargetStack)) $captureTarget->definitions[] = $name;
        return $node;
    }

    private function expressionStatement(): ExpressionStatement
    {    
        $statement = new ExpressionStatement();
        $statement->line = $this->peek()?->line;
        $statement->position = $this->peek()?->position;
        $statement->expression = $this->expression();
        return $statement;
    }

    private function tryIdentifier(bool $capture = true): ?Identifier
    {    
        if (!$this->match('atom')) return null;
    
        $node = new Identifier();
        $node->line = $this->peek()?->line;
        $node->position = $this->peek()?->position;
        $node->value = $this->consume('atom')->value;

        if ($capture && $currentCaptureTarget = array_last($this->captureTargetStack)) {
            if (!$this->identifierInArray($node, $currentCaptureTarget->params) &&
                !$this->identifierInArray($node, $currentCaptureTarget->captures) &&
                !$this->identifierInArray($node, $currentCaptureTarget->definitions)) {
                    $currentCaptureTarget->captures[] = $node;
            }
        }
        return $node;
    }

    private function identifierInArray(Identifier $i, array $a): bool
    {
        return array_any($a, static fn (Identifier $e) => $i->value === $e->value);
    }

    private function expression(): Expression
    {    
        static $depth = 0;
        if ($depth++ > 200) {
            throw new \RuntimeException("Parser recursion explosion at token {$this->index}");
        }
        $result = $this->conditional();
        $depth--;
        return $result;
    }

    private function conditional(): Expression
    {    
        if ($this->match('atom') && $this->peek()?->value === 'if') {
            $node = new Conditional();
            $node->line = $this->peek()?->line;
            $node->position = $this->peek()?->position;

            $this->consume('atom');
            $node->condition = $this->pipeline();

            if ($this->peek()?->value !== 'then') $this->error('if <expr> must be followed by then <expr>'); 
            $this->consume('atom');
            $node->then = $this->expression();

            if ($this->match('atom') && $this->peek()?->value === 'else') {
                $this->consume('atom');
                $node->else = $this->expression();
            }

            return $node;
        }

        return $this->pipeline();
    }

    private function pipeline(): Expression
    {    
        $concatenation = $this->concatenation();
        if ($this->match('pipeline')) {
            $pipeline = new Pipeline();
            $pipeline->line = $this->peek()?->line;
            $pipeline->position = $this->peek()?->position;
            $pipeline->input = $concatenation;

            while($this->match('pipeline')) {
                $this->consume('pipeline');
                $pipeline->stages[] = $this->reducerStage();
            }
            return $pipeline;
        }

        return $concatenation;
    }

    private function concatenation(): Expression
    {
        $left = $this->comparison();
        while ($this->match('concat')) {
            $node = new Concatenation();
            $node->line = $this->peek()?->line;
            $node->position = $this->peek()?->position;
            $this->consume('concat');
            $node->right = $this->comparison();
            $node->left = $left;
            $left = $node;
        }

        return $left;
    }

    private function reducerStage(): ReducerStage
    {    
        $init = $this->expression();

        $reducerStage = new ReducerStage();
        $reducerStage->line = $this->peek()?->line;
        $reducerStage->position = $this->peek()?->position;
        $reducerStage->init = $init;
        
        $reducer = $this->tryIdentifier();
        $reducer ??= $this->tryApplication();
        
        if (!$reducer) $this->error('Expected Lambda or Function Identifier as reducer');

        $reducerStage->reducer = $reducer;

        return $reducerStage;
    }

    private function comparison(): Expression
    {    
        $left = $this->arithmetic();
        if($this->match('comparison')) {
            $node = new Comparison();
            $node->line = $this->peek()?->line;
            $node->position = $this->peek()?->position;
            $node->operator = $this->consume('comparison')->value;
            $node->right = $this->arithmetic();
            $node->left = $left;
            return $node;
        }

        return $left;
    }

    private function arithmetic(): Expression
    {
        $left = $this->term();
        while($this->match('addOperator')) {
            $node = new Arithmetic();
            $node->line = $this->peek()?->line;
            $node->position = $this->peek()?->position;
            $node->operator = $this->consume('addOperator')->value;
            $node->right = $this->term();
            $node->left = $left;
            $left = $node;  // Accumulate left
        }
        return $left;
    }

    private function term(): Expression
    {
        $left = $this->unary();
        while($this->match('mulOperator')) {
            $node = new Term();
            $node->line = $this->peek()?->line;
            $node->position = $this->peek()?->position;
            $node->operator = $this->consume('mulOperator')->value;
            $node->right = $this->unary();
            $node->left = $left;
            $left = $node;  // Accumulate left
        }
        return $left;
    }

    private function unary(): Expression
    {    
        if ($this->match('unary') || ($this->match('addOperator') && $this->peek()?->value === '-')) {
            $unary = new Unary();
            $unary->line = $this->peek()?->line;
            $unary->position = $this->peek()?->position;
            $unary->operator = $this->peek()?->value;
            $this->advance();
            $unary->value = $this->unary();
            return $unary;
        }

        return $this->primary();
    }

    private function primary(): Expression
    {    
        $expression = $this->tryApplication();
        $expression ??= $this->tryParenthesisedExpression();
        $expression ??= $this->tryConstruction();
        $expression ??= $this->tryIdentifier();
        $expression ??= $this->trySymbol();
        $expression ??= $this->tryScalar();
        if (!$expression) {
            if ($this->peek() === null) {
                throw new IncompleteParseException("Unexpected end of input");
            }

            throw new SyntaxParseException(
                "Unexpected token '{$this->peek()->kind}'"
            );
        }

        return $expression;
    }

    private function tryApplication(): ?Expression
    {    
        if (!$this->match('l_brack')) return null;

        $this->consume('l_brack');
        
        $start = $this->index;

        $expression = $this->tryLambda();
        
        if (!$expression) {
            $this->index = $start;
            $expression = $this->call();
        }

        $this->consume('r_brack');
        return $expression;
        
        return null;
    }

    private function call(): Expression
    {    
        $call = new Call();
        $call->line = $this->peek()?->line;
        $call->position = $this->peek()?->position;
        $call->callee = $this->expression();

        while (!$this->match('r_brack')) {
            $call->args[] = $this->expression();
        }

        return $call;
    }

    private function lambda(): Expression
    {    
        $node = new Lambda();
        $node->line = $this->peek()?->line;
        $node->position = $this->peek()?->position;
        $node->params = $this->lambdaParams();

        $this->consume('arrow');

        array_push($this->captureTargetStack, $node);
        while(!$this->match('r_brack')) {
            $node->body[] = $this->tryStatement();
        }

        array_pop($this->captureTargetStack);

        return $node;
    }

    private function tryLambda(): ?Expression
    {    
        try {
            $lambda = $this->lambda();
            return $lambda;
        } catch (SyntaxParseException $e) {
            return null;
        }
    }

    /**
     * @return list<Identifier>
     */
    private function lambdaParams(): array
    {    
        $identifiers = [];
        while($this->match('atom')) {
            $identifier = $this->tryIdentifier(capture: false);
            if (!$identifier) break;
            $identifiers[] = $identifier;
        }

        return $identifiers;
    }

    private function tryParenthesisedExpression(): ?Expression
    {    
        if (!$this->match('l_paren')) return null;
        $this->consume('l_paren');
        $expression = $this->expression();
        $this->consume('r_paren');

        return $expression;
    }

    private function tryConstruction(): ?Expression
    {    
        if (!$this->match('l_brace')) return null;
        
        $this->consume('l_brace');
        $construction = new Construction();
        $construction->line = $this->peek()?->line;
        $construction->position = $this->peek()?->position;
        while (!$this->match('r_brace')) {
            if ($this->peek() === null) {
                throw new IncompleteParseException("Unclosed '{'");
            }

            $element = $this->tryConstructionElement();
            if ($element) $construction->elements[] = $element;
        }
        $this->consume('r_brace');

        return $construction;
    }

    private function tryConstructionElement(): ?Expression
    {    
        return $this->expression();
    }

    private function trySymbol(): ?Expression
    {    
        if (!$this->match('symbol')) return null;

        $node = new Symbol();
        $node->line = $this->peek()?->line;
        $node->position = $this->peek()?->position;
        $node->name = $this->consume('symbol')->value;

        return $node;
    }

    private function tryScalar(): ?Expression
    {    
        if (!in_array($this->peek()?->kind, ['int_literal', 'float_literal', 'string_literal', 'bool_literal'])) return null;
        $scalar = new ScalarLiteral();
        $scalar->line = $this->peek()?->line;
        $scalar->position = $this->peek()?->position;
        $scalar->value = match($this->peek()?->kind) {
            'int_literal' => (int)$this->peek()?->value,
            'float_literal' => (float)$this->peek()?->value,
            'string_literal' => (string)$this->peek()?->value,
            'bool_literal' => $this->peek()?->value === 'true',
        };
        $this->advance();

        return $scalar;
    }


    private function advance(): void
    {    
        $this->index++;
    }

    private function match(string $kind): bool
    {    
        $token = $this->peek();
        return $token !== null && $token->kind === $kind;
    }

    private function consume(string $kind): Token
    {    
        $currentToken = $this->peek();

        if ($this->match($kind)) {
            $this->advance();
            return $currentToken;
        }

        if ($currentToken === null) {
            throw new IncompleteParseException("Unexpected end of input, expected '$kind'");
        }

        $remaining = $this->stringifyTokenStream(
            array_slice($this->tokens, $this->index)
        );

        throw new SyntaxParseException(
            "Expected '$kind', found '{$currentToken->kind}', remaining '{$remaining}'"
        );
    }

    private function peek(): ?Token
    {
        return $this->index < count($this->tokens) ? $this->tokens[$this->index] : null;
    }

    private function tokenAt(int $index): ?Token
    {
        return $this->tokens[$index] ?? null;
    }

    public function stringifyTokenStream(array $tokenStream): string
    {
        return implode(" ", array_map(static fn ($e) => $e->kind, $tokenStream));
    }

    public function error(string $message): void
    {
        $line = $this->peek()?->line;
        $position = $this->peek()?->position;
        throw new SyntaxParseException("Parse Error on line $line at position $position: $message");
    }

    public function normaliseAst(mixed $node): mixed
    {
        if (is_array($node)) {
            return array_map([$this, 'normaliseAst'], $node);
        }

        if (is_object($node)) {
            return [
                'type' => (new ReflectionClass($node))->getShortName(),
                ...array_map([$this, 'normaliseAst'], get_object_vars($node))
            ];
        }

        return $node;
    }
}