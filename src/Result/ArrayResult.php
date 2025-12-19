<?php

declare(strict_types=1);

namespace Tourze\DoctrineCacheBundle\Result;

use Doctrine\DBAL\Driver\Result;

final class ArrayResult implements Result
{
    private int $currentRow = 0;

    /**
     * @param array<array<string, mixed>> $data
     */
    public function __construct(private readonly array $data)
    {
    }

    public function fetchNumeric(): array|false
    {
        if (!isset($this->data[$this->currentRow])) {
            return false;
        }

        // Return the row as a numeric array (array_values removes associative keys)
        return array_values($this->data[$this->currentRow++]);
    }

    public function fetchAssociative(): array|false
    {
        if (!isset($this->data[$this->currentRow])) {
            return false;
        }

        // Return the row as an associative array (assumes each row is already associative)
        return $this->data[$this->currentRow++];
    }

    public function fetchOne(): mixed
    {
        if (!isset($this->data[$this->currentRow])) {
            return false;
        }

        // Return the first value of the next row
        $row = $this->data[$this->currentRow++];

        return reset($row); // Get the first element of the array
    }

    /**
     * @return list<list<mixed>>
     */
    public function fetchAllNumeric(): array
    {
        // Return all rows as numeric arrays
        return array_values(array_map('array_values', $this->data));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchAllAssociative(): array
    {
        // Return all rows as associative arrays
        return array_values($this->data);
    }

    /**
     * @return list<mixed>
     */
    public function fetchFirstColumn(): array
    {
        // Return the first column of each row
        return array_values(array_map(function ($row) {
            return reset($row);
        }, $this->data));
    }

    public function rowCount(): int
    {
        // Return the number of rows in the data set
        return count($this->data);
    }

    public function columnCount(): int
    {
        // Return the number of columns in the first row, if available
        if ([] === $this->data) {
            return 0;
        }

        return count($this->data[0]);
    }

    public function free(): void
    {
        // Reset the current row index to the beginning
        $this->currentRow = 0;
    }
}
