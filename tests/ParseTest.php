<?php declare(strict_types=1);

namespace Jamesdolan16\Mosslang\Tests;

require_once __DIR__ . 'vendor/autoload.php';

use PHPUnit\Framework\TestCase;
use Jamesdolan16\Mosslang\Parser\Lexer;
use Jamesdolan16\Mosslang\Parser\Parser;

class ParseTest extends TestCase
{
    public function testParse(): void
    {
        $lexer = new Lexer();
        $parser = new Parser();

        $tokens = $lexer->tokenise('d:=5*5; x:=1; j:=d*x;');
        echo($parser->stringifyTokenStream($tokens) . "\n");
        dump($parser->parse($tokens));
    }
}