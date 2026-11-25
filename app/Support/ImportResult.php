<?php

namespace App\Support;

final class ImportResult
{
    /**
     * @param  array<int, array{line: int, status: string, message: ?string, warning: ?string, data: ?array}>  $rows
     */
    public function __construct(
        public readonly array $rows,
        public readonly ?string $fatal = null,
    ) {}

    /** Number of rows with the given status: 'ok', 'duplicate' or 'error'. */
    public function count(string $status): int
    {
        return count(array_filter($this->rows, fn (array $row) => $row['status'] === $status));
    }
}
