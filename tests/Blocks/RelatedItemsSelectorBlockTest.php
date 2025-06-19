<?php
use PHPUnit\Framework\TestCase;
use ArtPulse\Blocks\RelatedItemsSelectorBlock;

class RelatedItemsSelectorBlockTest extends TestCase
{
    public function testRelatedItemsSelectorBlockRegisters()
    {
        $this->assertTrue(method_exists(RelatedItemsSelectorBlock::class, 'register'));
    }
}
