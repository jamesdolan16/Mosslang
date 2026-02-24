<?php declare(strict_types=1);

namespace Jamesdolan16\Mosslang\Parser;

use Exception;

class IncompleteParseException extends Exception
{
    // public function __construct(?Token $currentToken) {
    //     parent::__construct(
    //         "Failed to parse due to incomplete expression on line {$currentToken?->line} at position {$currentToken?->position}."
    //     );
    // }
}