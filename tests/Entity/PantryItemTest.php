<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\PantryItem;
use App\Entity\Product;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class PantryItemTest extends TestCase
{
    public function testNewItemStartsAtZero(): void
    {
        $item = $this->makeItem();
        self::assertSame(0, $item->getQuantity());
    }

    public function testIncrement(): void
    {
        $item = $this->makeItem();
        $item->increment();
        $item->increment(2);
        self::assertSame(3, $item->getQuantity());
    }

    public function testDecrementFloorsAtZero(): void
    {
        $item = $this->makeItem();
        $item->increment();
        self::assertTrue($item->decrement());
        self::assertSame(0, $item->getQuantity());

        self::assertFalse($item->decrement());
        self::assertSame(0, $item->getQuantity());
    }

    public function testDecrementByLargeAmountFloorsAtZero(): void
    {
        $item = $this->makeItem();
        $item->increment(2);
        self::assertTrue($item->decrement(10));
        self::assertSame(0, $item->getQuantity());
    }

    public function testIncrementRejectsNonPositive(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->makeItem()->increment(0);
    }

    public function testDecrementRejectsNonPositive(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->makeItem()->decrement(-1);
    }

    private function makeItem(): PantryItem
    {
        return new PantryItem(new User(), new Product('3017620422003', 'Nutella'));
    }
}
