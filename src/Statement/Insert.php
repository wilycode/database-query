<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Statement;

use InvalidArgumentException;
use WilyCode\Database\Query\CompiledQuery;
use WilyCode\Database\Query\ParameterBag;

final class Insert implements QueryInterface
{
    private string $table = '';
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $rows = [];

    private ParameterBag $parameters;

    public function __construct(string $table)
    {
        $this->parameters = new ParameterBag();
        $this->table = $table;
    }

    /**
     * Insert a single row.
     *
     * @param array<string, mixed> $data
     */
    public function values(array $data): self
    {
        $this->rows = [$data];
        return $this;
    }

    /**
     * Insert many rows.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public function valuesMany(array $rows): self
    {
        if (empty($rows)) {
            throw new InvalidArgumentException('valuesMany() requires at least one row');
        }
        $this->rows = $rows;
        return $this;
    }
    public function compile(): CompiledQuery
    {
        $sql = $this->buildSql();

        return new CompiledQuery(
            $sql,
            $this->parameters->getParameters(),
            $this->parameters->getTypes(),
        );
    }

    private function buildSql(): string
    {
        if ($this->table === '') {
            throw new InvalidArgumentException('INSERT requires table');
        }
        if (empty($this->rows)) {
            throw new InvalidArgumentException('INSERT requires values');
        }

        $this->parameters = new ParameterBag();

        $columns = array_keys($this->rows[0]);
        if (empty($columns)) {
            throw new InvalidArgumentException('INSERT requires at least one column');
        }

        foreach ($this->rows as $i => $row) {
            $rowCols = array_keys($row);
            if ($rowCols !== $columns) {
                throw new InvalidArgumentException(sprintf(
                    'All INSERT rows must have the same columns. Row %d differs.',
                    $i
                ));
            }
        }

        $colSql = implode(', ', $columns);

        $valuesSqlParts = [];
        foreach ($this->rows as $row) {
            $placeholders = [];
            foreach ($columns as $col) {
                $placeholders[] = $this->parameters->add($row[$col] ?? null);
            }
            $valuesSqlParts[] = '(' . implode(', ', $placeholders) . ')';
        }

        return sprintf('INSERT INTO %s (%s) VALUES %s', $this->table, $colSql, implode(', ', $valuesSqlParts));
    }

}
