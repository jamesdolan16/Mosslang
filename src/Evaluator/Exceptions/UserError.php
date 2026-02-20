<?php declare(strict_types=1);

namespace Jamesdolan16\Mosslang\Evaluator\Exceptions;

use Exception;
use Jamesdolan16\Mosslang\Evaluator\Box;

class UserError extends Exception
{
    public function __construct(
        public Box $error, 
        public Box $userMessage
    ) {
        parent::__construct($userMessage->toString());
    }
}