<?php declare(strict_types=1);

namespace Jamesdolan16\Mosslang\Evaluator;

use Jamesdolan16\Mosslang\Parser\AstNodes\Construction;
use Jamesdolan16\Mosslang\Parser\AstNodes\Symbol;

class TaggedPair
{
    public function __construct(
        public string $tag,
        public Box $box
    ) {}

    /**
     * @param Box $construction
     */
    public static function fromConstruction(Box $construction): ?self
    {
        if (!is_array($construction->value)) return null;
        
        if (count($construction->value) != 2)
            throw new \LogicException("TaggedPair requires only 2 elements, the tag and the value, found " . 
                count($construction->value));
    
        /** @var Box */
        $tagBox = $construction->value[0];
        if (!$tagBox->value instanceof Symbol)
            throw new \LogicException("TaggedPair requires Symbol as first element, found " . $tagBox->type);

        /** @var Box */
        $box = $construction->value[1];
        
        $tag = $tagBox->value;
        return new self($tag->name, $box);
    }
}