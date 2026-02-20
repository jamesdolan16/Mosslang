<?php declare(strict_types=1);

namespace Jamesdolan16\Mosslang\Parser;

use RuntimeException;
use Jamesdolan16\Mosslang\Parser\Helpers as h;

class Lexer
{
    public function __construct(){}

    public function readFile(array $lines): array
    {
        $tokens = [];
        $lineIdx = 1;
        foreach($lines as $line) {
            $tokens = $this->next(str_split($line), $tokens, $lineIdx);
            $lineIdx++;
        }

        return $tokens;
    }

    // /**
    //  * @return list<Token>
    //  */
    // public function tokenise(string $query): array
    // {
    //     $chars = str_split($query);

    //     if(count($chars) === 0) throw new \InvalidArgumentException('Empty query');
    //     return $this->next($chars, []);
    // }

    /**
     * @param array<string> $chars
     * @return list<Token>
     */
    private function next(array $chars, array $tokens, int $lineIdx, int $position = 0): array
    {
        if (count($chars) === 0) return $tokens;
        [$head, $tail] = h::decap($chars);
        $nextPosition = $position + 1;
        
        return match(true) {
            $head === '(' => $this->next($tail, [...$tokens, new Token('l_paren', line: $lineIdx, position: $position)], $lineIdx, $nextPosition),
            $head === ')' => $this->next($tail, [...$tokens, new Token('r_paren', line: $lineIdx, position: $position)], $lineIdx, $nextPosition),
            $head === '[' => $this->next($tail, [...$tokens, new Token('l_brack', line: $lineIdx, position: $position)], $lineIdx, $nextPosition),
            $head === ']' => $this->next($tail, [...$tokens, new Token('r_brack', line: $lineIdx, position: $position)], $lineIdx, $nextPosition),
            $head === '{' => $this->next($tail, [...$tokens, new Token('l_brace', line: $lineIdx, position: $position)], $lineIdx, $nextPosition),
            $head === '}' => $this->next($tail, [...$tokens, new Token('r_brace', line: $lineIdx, position: $position)], $lineIdx, $nextPosition),
            $head === '+' => $this->next($tail, [...$tokens, new Token('addOperator', '+', $position)], $lineIdx, $nextPosition),
            $head === '*' => $this->next($tail, [...$tokens, new Token('mulOperator', '*', $position)], $lineIdx, $nextPosition),
            $head === '/' => $this->next($tail, [...$tokens, new Token('mulOperator', '/', $position)], $lineIdx, $nextPosition),
            $head === '|' => $this->next($tail, [...$tokens, new Token('concat', line: $lineIdx, position: $position)], $lineIdx, $nextPosition),
            $head === ',' => $this->next($tail, [...$tokens, new Token('comma', line: $lineIdx, position: $position)], $lineIdx, $nextPosition),
            $head === '=' => $this->next($tail, [...$tokens, new Token('comparison', '=', $lineIdx, $position)], $lineIdx, $nextPosition),
            $head === '!' => $this->next($tail, [...$tokens, new Token('unary', '!', $lineIdx, $position)], $lineIdx, $nextPosition),
            $head === '?' => $this->next($tail, [...$tokens, new Token('unary', '?', $lineIdx, position: $position)], $lineIdx, $nextPosition),
            $head === ';' => $this->next($tail, [...$tokens, new Token('semicolon', line: $lineIdx, position: $position)], $lineIdx, $nextPosition),
            $head === '-' => $this->dash($tail, $tokens, $lineIdx, $position),
            $head === '~' => $this->tilde($tail, $tokens, $lineIdx, $position),
            $head === ':' => $this->colon($tail, $tokens, $lineIdx, $position),
            $head === '>' => $this->greater($tail, $tokens, $lineIdx, $position),
            $head === '<' => $this->less($tail, $tokens, $lineIdx, $position),
            $head === '"' => $this->stringLiteral([], $tail, $tokens, $lineIdx, $position, '"'),
            $head === '\'' => $this->stringLiteral([], $tail, $tokens, $lineIdx, $position, '\''),
            ctype_alpha($head) || $head === '_' => $this->atom([], $chars, $tokens, $lineIdx, $position),
            ctype_digit($head) => $this->numericLiteral([], $chars, $tokens, $lineIdx, $position),
            ctype_space($head) => $this->next($tail, $tokens, $lineIdx, $nextPosition),      // Ignore whitespace
            default => throw new \RuntimeException("Unexpected character '{$head}' on line {$lineIdx} at position {$position}")
        };
    }

    private function dash(array $chars, array $tokens, int $lineIdx, int $position): array
    {
        [$head, $tail] = h::decap($chars);
        $nextPosition = $position + 1;

        return match ($head) {
            '>' => $this->next($tail, [...$tokens, new Token('arrow', line: $lineIdx, position: $position)], $lineIdx, $nextPosition),
            default => $this->next($chars, [...$tokens, new Token('addOperator', '-', $position)], $lineIdx, $nextPosition)
        };
    }

    private function fslash(array $chars, array $tokens, int $lineIdx, int $position): array
    {
        [$head, $tail] = h::decap($chars);
        $nextPosition = $position + 1;

        return match ($head) {
            '/' => $this->lineComment($tail, $tokens, $lineIdx, $nextPosition),
            '/*' => $this->blockComment($tail, $tokens, $lineIdx, $nextPosition),
            default => $this->next($tail, [...$tokens, new Token('mulOperator', '/', $position)], $lineIdx, $nextPosition)
        };
    }

    private function lineComment(array $chars, array $tokens, int $lineIdx, int $position): array
    {
        
    }

    private function blockComment(array $chars, array $tokens, int $lineIdx, int $position): array
    {
        
    }
    
    private function tilde(array $chars, array $tokens, int $lineIdx, int $position): array
    {
        [$head, $tail] = h::decap($chars);
        $nextPosition = $position + 1;

        return match ($head) {
            '>' => $this->next($tail, [...$tokens, new Token('pipeline', line: $lineIdx, position: $position)], $lineIdx, $nextPosition),
            default => throw new \LogicException("Unexpected character '~' on line {$lineIdx} at position {$position}, did you mean '~>'")
        };
    }

    private function colon(array $chars, array $tokens, int $lineIdx, int $position): array
    {
        [$head, $tail] = h::decap($chars);
        $nextPosition = $position + 1;

        return match (true) {
            $head === '=' => $this->next($tail, [...$tokens, new Token('assignment', line: $lineIdx, position: $position)], $lineIdx, $nextPosition),
            $head === ':' => $this->next($tail, [...$tokens, new Token('signature', line: $lineIdx, position: $position)], $lineIdx, $nextPosition),
            $this->validAtomChar($head) => $this->symbol([], $chars, $tokens, $lineIdx, $position),
            default => $this->next($chars, [...$tokens, new Token('colon', line: $lineIdx, position: $position)], $lineIdx, $nextPosition)
        };
    }

    private function symbol(array $symbol, array $chars, array $tokens, int $lineIdx, int $position): array
    {
        if (count($chars) === 0) return $this->next($chars, [...$tokens, new Token('symbol', implode($symbol), $lineIdx, $position)], $lineIdx, $position);

        [$head, $tail] = h::decap($chars);
        $nextPosition = $position + 1;
        
        if ($this->validAtomChar($head)) { 
            return $this->symbol(
                [...$symbol, $head], 
                $tail, 
                $tokens,
                $lineIdx,
                $nextPosition
            );
        }
        
        return $this->next($chars, [...$tokens, new Token('symbol', implode($symbol), $lineIdx, $position)], $lineIdx, $nextPosition);
    }

    private function less(array $chars, array $tokens, int $lineIdx, int $position): array
    {
        [$head, $tail] = h::decap($chars);
        $nextPosition = $position + 1;

        return match($head) {
            '=' => $this->next($tail, [...$tokens, new Token('comparison', '<=', $lineIdx, $position)], $lineIdx, $nextPosition),
            default => $this->next($chars, [...$tokens, new Token('comparison', '<', $lineIdx, $position)], $lineIdx, $nextPosition)
        };
    }

    private function greater(array $chars, array $tokens, int $lineIdx, int $position): array
    {
        [$head, $tail] = h::decap($chars);
        $nextPosition = $position + 1;

        return match($head) {
            '=' => $this->next($tail, [...$tokens, new Token('comparison', '>=', $lineIdx, $position)], $lineIdx, $nextPosition),
            default => $this->next($chars, [...$tokens, new Token('comparison', '>', $lineIdx, $position)], $lineIdx, $nextPosition)
        };
    }

    private function stringLiteral(array $literal, array $chars, array $tokens, int $lineIdx, int $position, string $terminator): array
    {
        if (count($chars) === 0) throw new \RuntimeException('Unterminated string literal in query');

        [$head, $tail] = h::decap($chars);
        $nextPosition = $position + 1;
        
        if ($head !== $terminator) { 
            return $this->stringLiteral(
                [...$literal, $head], 
                $tail, 
                $tokens, 
                $lineIdx,
                $nextPosition,
                $terminator
            );
        }
        
        return $this->next($tail, [...$tokens, new Token('string_literal', implode($literal), $lineIdx, $position)], $lineIdx, $nextPosition);
    }

    private function numericLiteral(array $literal, array $chars, array $tokens, int $lineIdx, int $position): array
    {
        if (count($chars) === 0) return $this->next($chars, [...$tokens, new Token('int_literal', implode($literal), $lineIdx, $position)], $position);

        [$head, $tail] = h::decap($chars);
        $nextPosition = $position + 1;
        
        if ($head === '.') { 
            return $this->consumeDecimal([...$literal, $head], $tail, $tokens, $lineIdx, $nextPosition);
        }

        if (ctype_digit($head)) {
            return $this->numericLiteral(
                [...$literal, $head], 
                $tail, 
                $tokens,
                $lineIdx,
                $nextPosition
            );
            
        }
        
        return $this->next($chars, [...$tokens, new Token('int_literal', implode($literal), $lineIdx, $position)], $lineIdx, $nextPosition);
    }

    private function consumeDecimal(array $literal, array $chars, array $tokens, int $lineIdx, int $position): array
    {
        if (count($chars) === 0) return $this->next($chars, [...$tokens, new Token('float_literal', implode($literal), $lineIdx, $position)], $position); 

        [$head, $tail] = h::decap($chars);
        $nextPosition = $position + 1;
        
        if ($head === '.') { 
            throw new \RuntimeException("Invalid second '.' in decimal, on line {$lineIdx} at position {$position}");
        }

        if (ctype_digit($head)) {
            return $this->consumeDecimal(
                [...$literal, $head], 
                $tail, 
                $tokens,
                $lineIdx,
                $nextPosition
            );
        }
        
        return $this->next($chars, [...$tokens, new Token('float_literal', implode($literal), $lineIdx, $position)], $lineIdx, $nextPosition);    
    }

    /**
     * @return list<Token>
     */
    private function atom(array $atom, array $chars, array $tokens, int $lineIdx, int $position): array
    {
        $atomStr = implode($atom);

        if (in_array($atomStr, ['true', 'false']))
            return $this->next($chars, [...$tokens, new Token('bool_literal', $atomStr, $lineIdx, $position)], $lineIdx, $position);

        if (count($chars) === 0) return $this->next($chars, [...$tokens, new Token('atom', $atomStr, $lineIdx, $position)], $lineIdx, $position);

        [$head, $tail] = h::decap($chars);
        $nextPosition = $position + 1;
        
        if ($this->validAtomChar($head)) { 
            return $this->atom(
                [...$atom, $head], 
                $tail, 
                $tokens,
                $lineIdx,
                $nextPosition
            );
        }
        
        return $this->next($chars, [...$tokens, new Token('atom', implode($atom), $lineIdx, $position)], $lineIdx, $nextPosition);
    }

    private function validAtomChar(string $char): bool
    {
        return ctype_alnum($char) || $char === '_' || $char === '-';
    }
}