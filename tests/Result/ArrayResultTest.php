<?php

declare(strict_types=1);

namespace Tourze\DoctrineCacheBundle\Tests\Result;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\DoctrineCacheBundle\Result\ArrayResult;

/**
 * @internal
 */
#[CoversClass(ArrayResult::class)]
final class ArrayResultTest extends TestCase
{
    /**
     * @var array<array{id: int, name: string}>
     */
    private array $testData;

    private ArrayResult $arrayResult;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testData = [
            ['id' => 1, 'name' => 'test1'],
            ['id' => 2, 'name' => 'test2'],
            ['id' => 3, 'name' => 'test3'],
        ];

        $this->arrayResult = new ArrayResult($this->testData);
    }

    public function testFetchNumericReturnsFirstRowAsNumericArray(): void
    {
        $expected = [1, 'test1'];
        $result = $this->arrayResult->fetchNumeric();

        $this->assertSame($expected, $result);
    }

    public function testFetchNumericReturnsFalseWhenNoMoreRows(): void
    {
        // 取出所有行
        $this->arrayResult->fetchNumeric();
        $this->arrayResult->fetchNumeric();
        $this->arrayResult->fetchNumeric();

        // 尝试再取一行
        $result = $this->arrayResult->fetchNumeric();

        $this->assertFalse($result);
    }

    public function testFetchAssociativeReturnsFirstRowAsAssociativeArray(): void
    {
        $expected = ['id' => 1, 'name' => 'test1'];
        $result = $this->arrayResult->fetchAssociative();

        $this->assertSame($expected, $result);
    }

    public function testFetchAssociativeReturnsFalseWhenNoMoreRows(): void
    {
        // 取出所有行
        $this->arrayResult->fetchAssociative();
        $this->arrayResult->fetchAssociative();
        $this->arrayResult->fetchAssociative();

        // 尝试再取一行
        $result = $this->arrayResult->fetchAssociative();

        $this->assertFalse($result);
    }

    public function testFetchOneReturnsFirstColumnOfFirstRow(): void
    {
        $expected = 1; // 第一行的第一个元素
        $result = $this->arrayResult->fetchOne();

        $this->assertSame($expected, $result);
    }

    public function testFetchOneReturnsFalseWhenNoMoreRows(): void
    {
        // 取出所有行
        $this->arrayResult->fetchOne();
        $this->arrayResult->fetchOne();
        $this->arrayResult->fetchOne();

        // 尝试再取一行
        $result = $this->arrayResult->fetchOne();

        $this->assertFalse($result);
    }

    public function testFetchAllNumericReturnsAllRowsAsNumericArrays(): void
    {
        $expected = [
            [1, 'test1'],
            [2, 'test2'],
            [3, 'test3'],
        ];
        $result = $this->arrayResult->fetchAllNumeric();

        $this->assertSame($expected, $result);
    }

    public function testFetchAllAssociativeReturnsAllRowsAsAssociativeArrays(): void
    {
        $result = $this->arrayResult->fetchAllAssociative();

        $this->assertSame($this->testData, $result);
    }

    public function testFetchFirstColumnReturnsFirstColumnOfAllRows(): void
    {
        $expected = [1, 2, 3];
        $result = $this->arrayResult->fetchFirstColumn();

        $this->assertSame($expected, $result);
    }

    public function testRowCountReturnsNumberOfRows(): void
    {
        $this->assertSame(3, $this->arrayResult->rowCount());
    }

    public function testColumnCountReturnsNumberOfColumnsInFirstRow(): void
    {
        $this->assertSame(2, $this->arrayResult->columnCount());
    }

    public function testColumnCountReturnsZeroForEmptyData(): void
    {
        $emptyResult = new ArrayResult([]);
        $this->assertSame(0, $emptyResult->columnCount());
    }

    public function testFreeResetsCurrentRowIndex(): void
    {
        // 取出一行数据
        $this->arrayResult->fetchAssociative();

        // 重置游标
        $this->arrayResult->free();

        // 确认游标被重置，重新从第一行开始获取
        $expected = ['id' => 1, 'name' => 'test1'];
        $result = $this->arrayResult->fetchAssociative();

        $this->assertSame($expected, $result);
    }
}
