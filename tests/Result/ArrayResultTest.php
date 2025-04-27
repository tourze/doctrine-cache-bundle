<?php

declare(strict_types=1);

namespace Tourze\DoctrineCacheBundle\Tests\Result;

use PHPUnit\Framework\TestCase;
use Tourze\DoctrineCacheBundle\Result\ArrayResult;

class ArrayResultTest extends TestCase
{
    private array $testData;
    private ArrayResult $arrayResult;

    protected function setUp(): void
    {
        $this->testData = [
            ['id' => 1, 'name' => 'test1'],
            ['id' => 2, 'name' => 'test2'],
            ['id' => 3, 'name' => 'test3'],
        ];
        $this->arrayResult = new ArrayResult($this->testData);
    }

    public function testFetchNumeric_returnsFirstRowAsNumericArray(): void
    {
        $expected = [1, 'test1'];
        $result = $this->arrayResult->fetchNumeric();

        $this->assertSame($expected, $result);
    }

    public function testFetchNumeric_returnsFalseWhenNoMoreRows(): void
    {
        // 取出所有行
        $this->arrayResult->fetchNumeric();
        $this->arrayResult->fetchNumeric();
        $this->arrayResult->fetchNumeric();

        // 尝试再取一行
        $result = $this->arrayResult->fetchNumeric();

        $this->assertFalse($result);
    }

    public function testFetchAssociative_returnsFirstRowAsAssociativeArray(): void
    {
        $expected = ['id' => 1, 'name' => 'test1'];
        $result = $this->arrayResult->fetchAssociative();

        $this->assertSame($expected, $result);
    }

    public function testFetchAssociative_returnsFalseWhenNoMoreRows(): void
    {
        // 取出所有行
        $this->arrayResult->fetchAssociative();
        $this->arrayResult->fetchAssociative();
        $this->arrayResult->fetchAssociative();

        // 尝试再取一行
        $result = $this->arrayResult->fetchAssociative();

        $this->assertFalse($result);
    }

    public function testFetchOne_returnsFirstColumnOfFirstRow(): void
    {
        $expected = 1; // 第一行的第一个元素
        $result = $this->arrayResult->fetchOne();

        $this->assertSame($expected, $result);
    }

    public function testFetchOne_returnsFalseWhenNoMoreRows(): void
    {
        // 取出所有行
        $this->arrayResult->fetchOne();
        $this->arrayResult->fetchOne();
        $this->arrayResult->fetchOne();

        // 尝试再取一行
        $result = $this->arrayResult->fetchOne();

        $this->assertFalse($result);
    }

    public function testFetchAllNumeric_returnsAllRowsAsNumericArrays(): void
    {
        $expected = [
            [1, 'test1'],
            [2, 'test2'],
            [3, 'test3'],
        ];
        $result = $this->arrayResult->fetchAllNumeric();

        $this->assertSame($expected, $result);
    }

    public function testFetchAllAssociative_returnsAllRowsAsAssociativeArrays(): void
    {
        $result = $this->arrayResult->fetchAllAssociative();

        $this->assertSame($this->testData, $result);
    }

    public function testFetchFirstColumn_returnsFirstColumnOfAllRows(): void
    {
        $expected = [1, 2, 3];
        $result = $this->arrayResult->fetchFirstColumn();

        $this->assertSame($expected, $result);
    }

    public function testRowCount_returnsNumberOfRows(): void
    {
        $this->assertSame(3, $this->arrayResult->rowCount());
    }

    public function testColumnCount_returnsNumberOfColumnsInFirstRow(): void
    {
        $this->assertSame(2, $this->arrayResult->columnCount());
    }

    public function testColumnCount_returnsZeroForEmptyData(): void
    {
        $emptyResult = new ArrayResult([]);
        $this->assertSame(0, $emptyResult->columnCount());
    }

    public function testFree_resetsCurrentRowIndex(): void
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
