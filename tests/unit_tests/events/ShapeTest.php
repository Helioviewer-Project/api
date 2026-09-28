<?php declare(strict_types=1);

/**
 * @author Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */

use PHPUnit\Framework\TestCase;
use Helioviewer\Api\Event\Shape;

final class ShapeTest extends TestCase
{
    public function testHoldsKindClosedAndPoints(): void
    {
        $pts = [['x' => 1.0, 'y' => 2.0], ['x' => 3.0, 'y' => 4.0], ['x' => 5.0, 'y' => 6.0]];
        $s = new Shape(Shape::FILL, true, $pts);

        $this->assertSame(Shape::FILL, $s->kind);
        $this->assertTrue($s->closed);
        $this->assertSame($pts, $s->points);
        $this->assertSame(3, $s->count());
    }

    public function testKindPredicates(): void
    {
        $this->assertTrue((new Shape(Shape::TINT, true, []))->isTint());
        $this->assertTrue((new Shape(Shape::GHOST, false, []))->isGhost());
        $this->assertTrue((new Shape(Shape::FILL, true, []))->isFill());
        $this->assertFalse((new Shape(Shape::TINT, true, []))->isFill());
    }

    public function testPropertiesAreReadonly(): void
    {
        $s = new Shape(Shape::FILL, true, []);
        $this->expectException(\Error::class);   // "Cannot modify readonly property"
        $s->kind = Shape::TINT;
    }
}
