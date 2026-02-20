<?php declare(strict_types=1);

namespace Jamesdolan16\Mosslang\Parser;

use RuntimeException;

class NewLexer
{
    private string $source;
    private int $length = 0;
    private int $index = 0;
    private int $line = 1;
    private int $column = 0;

    /** @return list<Token> */
    public function tokenize(string $source): array
    {
        $this->source = $source;
        $this->length = strlen($source);
        $this->index = 0;
        $this->line = 1;
        $this->column = 0;

        $tokens = [];

        while (!$this->isAtEnd()) {
            $token = $this->scanToken();
            if ($token !== null) {
                $tokens[] = $token;
            }
        }

        return $tokens;
    }

    private function isAtEnd(): bool
    {
        return $this->index >= $this->length;
    }

    private function advance(): string
    {
        $char = $this->source[$this->index++];

        if ($char === "\n") {
            $this->line++;
            $this->column = 0;
        } else {
            $this->column++;
        }

        return $char;
    }

    private function peek(): string
    {
        return $this->source[$this->index] ?? "\0";
    }

    private function peekNext(): string
    {
        return $this->source[$this->index + 1] ?? "\0";
    }

    private function match(string $expected): bool
    {
        if ($this->isAtEnd()) return false;
        if ($this->source[$this->index] !== $expected) return false;

        $this->index++;
        $this->column++;
        return true;
    }

    private function scanToken(): ?Token
    {
        $startLine = $this->line;
        $startColumn = $this->column;

        $char = $this->advance();

        return match ($char) {
            '(' => new Token('l_paren', line: $startLine, position: $startColumn),
            ')' => new Token('r_paren', line: $startLine, position: $startColumn),
            '[' => new Token('l_brack', line: $startLine, position: $startColumn),
            ']' => new Token('r_brack', line: $startLine, position: $startColumn),
            '{' => new Token('l_brace', line: $startLine, position: $startColumn),
            '}' => new Token('r_brace', line: $startLine, position: $startColumn),
            '+' => new Token('addOperator', '+', $startLine, $startColumn),
            '*' => new Token('mulOperator', '*', $startLine, $startColumn),
            '|' => new Token('concat', line: $startLine, position: $startColumn),
            ',' => new Token('comma', line: $startLine, position: $startColumn),
            ';' => new Token('semicolon', line: $startLine, position: $startColumn),
            '=' => new Token('comparison', '=', $startLine, $startColumn),
            '!' => new Token('unary', '!', $startLine, $startColumn),
            '?' => new Token('unary', '?', $startLine, $startColumn),
            '-' => $this->match('>')
                ? new Token('arrow', line: $startLine, position: $startColumn)
                : new Token('addOperator', '-', $startLine, $startColumn),
            '~' => $this->match('>')
                ? new Token('pipeline', line: $startLine, position: $startColumn)
                : throw new RuntimeException("Unexpected '~' at line $startLine"),
            '<' => $this->match('=')
                ? new Token('comparison', '<=', $startLine, $startColumn)
                : new Token('comparison', '<', $startLine, $startColumn),
            '>' => $this->match('=')
                ? new Token('comparison', '>=', $startLine, $startColumn)
                : new Token('comparison', '>', $startLine, $startColumn),
            ':' => $this->colon($startLine, $startColumn),
            '"'  => $this->stringLiteral('"', $startLine, $startColumn),
            '\'' => $this->stringLiteral('\'', $startLine, $startColumn),
            '/'  => $this->slash($startLine, $startColumn),
            default => $this->defaultHandler($char, $startLine, $startColumn)
        };
    }

    private function slash(int $line, int $col): ?Token
    {
        if ($this->match('/')) {
            while ($this->peek() !== "\n" && !$this->isAtEnd()) {
                $this->advance();
            }
            return null;
        }

        if ($this->match('*')) {
            while (!$this->isAtEnd()) {
                if ($this->peek() === '*' && $this->peekNext() === '/') {
                    $this->advance();
                    $this->advance();
                    return null;
                }
                $this->advance();
            }
            throw new RuntimeException("Unterminated block comment at line $line");
        }

        return new Token('mulOperator', '/', $line, $col);
    }

    private function colon(int $line, int $col): Token
    {
        if ($this->match('=')) {
            return new Token('assignment', line: $line, position: $col);
        }

        // if ($this->match(':')) {
        //     return new Token('signature', line: $line, position: $col);
        // }

        if ($this->validAtomChar($this->peek())) {
            return $this->symbol($line, $col);
        }

        return new Token('colon', line: $line, position: $col);
    }

    private function stringLiteral(string $terminator, int $line, int $col): Token
    {
        $value = '';

        while (!$this->isAtEnd()) {
            $char = $this->advance();

            if ($char === $terminator) {
                return new Token('string_literal', $value, $line, $col);
            }

            $value .= $char;
        }

        throw new RuntimeException("Unterminated string at line $line");
    }

    private function symbol(int $line, int $col): Token
    {
        $value = '';

        while ($this->validAtomChar($this->peek())) {
            $value .= $this->advance();
        }

        return new Token('symbol', $value, $line, $col);
    }

    private function defaultHandler(string $char, int $line, int $col): ?Token
    {
        if (ctype_space($char)) return null;

        if (ctype_digit($char)) {
            return $this->number($char, $line, $col);
        }

        if (ctype_alpha($char) || $char === '_') {
            return $this->atom($char, $line, $col);
        }

        throw new RuntimeException("Unexpected character '$char' at line $line");
    }

    private function number(string $first, int $line, int $col): Token
    {
        $value = $first;
        $isFloat = false;

        while (ctype_digit($this->peek()) || (!$isFloat && $this->peek() === '.')) {
            if ($this->peek() === '.') {
                $isFloat = true;
            }
            $value .= $this->advance();
        }

        return new Token(
            $isFloat ? 'float_literal' : 'int_literal',
            $value,
            $line,
            $col
        );
    }

    private function atom(string $first, int $line, int $col): Token
    {
        $value = $first;

        while ($this->validAtomChar($this->peek())) {
            $value .= $this->advance();
        }

        if (in_array($value, ['true', 'false'])) {
            return new Token('bool_literal', $value, $line, $col);
        }

        return new Token('atom', $value, $line, $col);
    }

    private function validAtomChar(string $char): bool
    {
        return ctype_alnum($char) || $char === '_' || $char === '-';
    }
}
