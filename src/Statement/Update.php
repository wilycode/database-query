<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Statement;

use InvalidArgumentException;
use WilyCode\Database\Query\Clause\WhereClause;
use WilyCode\Database\Query\CompiledQuery;

final class Update extends WhereClause implements QueryInterface
{
    private string $table;
    /** @var array<string, string> */
    private array $set = [];

    public function __construct(string $table)
    {
        parent::__construct();
        $this->table = $table;
    }

    /** @param array<string, mixed> $data */
    public function set(array $data): self
    {
        foreach ($data as $column => $value) {
            $column = trim((string) $column);
            if ($column === '') {
                throw new InvalidArgumentException('UPDATE column cannot be empty.');
            }
            if (array_key_exists($column, $this->set)) {
                throw new InvalidArgumentException(sprintf(
                    'UPDATE column "%s" was assigned more than once.',
                    $column,
                ));
            }

            $this->set[$column] = $column . ' = ' . $this->parameters->add($value);
        }

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
        if (empty($this->table)) {
            throw new InvalidArgumentException('No table specified for UPDATE');
        }
        if (empty($this->set)) {
            throw new InvalidArgumentException('No columns specified for update');
        }

        $parts = [
            'UPDATE ' . $this->table,
            'SET ' . implode(', ', array_values($this->set))
        ];

        if ($where = $this->renderWheres()) {
            $parts[] = 'WHERE ' . $where;
        }

        return implode(' ', $parts);
    }

}
