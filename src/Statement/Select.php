<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Statement;

use InvalidArgumentException;
use WilyCode\Database\Query\Clause\ConditionClause;
use WilyCode\Database\Query\Clause\GroupByClause;
use WilyCode\Database\Query\Clause\LimitOffset;
use WilyCode\Database\Query\Clause\OrderByClause;
use WilyCode\Database\Query\CompiledQuery;
use WilyCode\Database\Query\Dialect\DialectInterface;
use WilyCode\Database\Query\Enum\JoinType;
use WilyCode\Database\Query\Enum\OrderDirection;
use WilyCode\Database\Query\Expression\Expression;
use WilyCode\Database\Query\Expression\ParameterPrefixAware;
use WilyCode\Database\Query\Expression\RawExpression;
use WilyCode\Database\Query\ParameterBag;
use WilyCode\Database\Query\Query;
use WilyCode\Database\Query\Support\ParameterImporter;

final class Select extends ConditionClause implements QueryInterface
{
    private string $table;
    private ?string $alias;

    /**
     * @var array<int, array{expr: string|Expression, alias: ?string, tableAlias: ?string}>
     */
    private array $selectItems = [];

    /**
     * @var array<int, array{type: JoinType, table: string, alias: string, on: Expression}>
     */
    private array $joins = [];

    private OrderByClause $orderBy;
    private LimitOffset $limitOffset;
    private GroupByClause $groupBy;

    public function __construct(string $table, ?string $alias = null)
    {
        parent::__construct();
        $this->table = $table;
        $this->alias = $alias;

        $this->orderBy = new OrderByClause();
        $this->limitOffset = new LimitOffset();
        $this->groupBy = new GroupByClause();
    }

    /**
     * Add a SELECT item (alias => expr). If alias is empty, no AS will be added.
     */
    public function column(string $name, ?string $alias = null): self
    {
        return $this->columnAs($alias, $name);
    }

    /**
     * Add many SELECT items.
     * Standard format: alias => expr.
     * Numeric keys are allowed and treated as expressions without alias (useful for '*' or 'p.*').
     *
     * @param array<string|int, string|Expression> $columns
     */
    public function columns(array $columns): self
    {
        return $this->addColumns($columns);
    }

    /** @param array<string|int, string|Expression> $columns */
    private function addColumns(array $columns, ?string $tableAlias = null): self
    {
        foreach ($columns as $alias => $expr) {
            $this->columnAs(is_int($alias) ? null : $alias, $expr, $tableAlias);
        }
        return $this;
    }

    private function columnAs(?string $alias, string|Expression $expr, ?string $tableAlias = null): self
    {
        if ($alias !== null && $expr instanceof ParameterPrefixAware && !$expr->hasParamPrefix()) {
            $expr->paramPrefix($alias);
        }

        $this->selectItems[] = [
            'expr' => $expr,
            'alias' => $alias,
            'tableAlias' => $tableAlias,
        ];
        return $this;
    }

    /** @param array<int|string, mixed> $parameters */
    public function columnRaw(string $alias, string $sql, array $parameters = []): self
    {
        return $this->columnAs($alias, RawExpression::raw($sql, $parameters));
    }

    public function columnExp(string $alias, Expression $expression): self
    {
        return $this->columnAs($alias, $expression);
    }

    /**
     * Add an INNER JOIN with ON condition expressed as raw SQL (recommended: use '?' placeholders).
     *
     * Parameters are processed the same way as other raw expressions.
     * Arrays are supported and will be expanded (useful for: "id IN (?)" with [[1,2,3]]).
     * Optional columns are appended to SELECT using this join's alias for simple names and '*'.
     *
     * @param array<int|string, mixed> $parameters
     * @param array<string|int, string|Expression> $columns Result alias => expression, or a list of columns.
     */
    public function join(string $table, string $alias, string $on, array $parameters = [], array $columns = []): self
    {
        return $this->addJoin(JoinType::INNER, $table, $alias, $on, $parameters, $columns);
    }

    /**
     * @param array<int|string, mixed> $parameters
     * @param array<string|int, string|Expression> $columns
     */
    public function leftJoin(string $table, string $alias, string $on, array $parameters = [], array $columns = []): self
    {
        return $this->addJoin(JoinType::LEFT, $table, $alias, $on, $parameters, $columns);
    }

    /**
     * @param array<int|string, mixed> $parameters
     * @param array<string|int, string|Expression> $columns
     */
    public function rightJoin(string $table, string $alias, string $on, array $parameters = [], array $columns = []): self
    {
        return $this->addJoin(JoinType::RIGHT, $table, $alias, $on, $parameters, $columns);
    }

    /**
     * @param array<int|string, mixed> $parameters
     * @param array<string|int, string|Expression> $columns
     */
    private function addJoin(JoinType $type, string $table, string $alias, string $on, array $parameters, array $columns): self
    {
        $this->joins[] = [
            'type' => $type,
            'table' => $table,
            'alias' => $alias,
            'on' => RawExpression::raw($on, $parameters),
        ];

        return $this->addColumns($columns, $alias);
    }

    /** @param string|list<string> ...$columns */
    public function groupBy(string|array ...$columns): self
    {
        foreach ($columns as $c) {
            $this->groupBy->add($c);
        }
        return $this;
    }

    public function orderBy(string $column, string|OrderDirection $direction = OrderDirection::ASC): self
    {
        if (is_string($direction)) {
            $direction = OrderDirection::fromString($direction);
        }
        $this->orderBy->add($column, $direction);
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limitOffset->limit($limit);
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->limitOffset->offset($offset);
        return $this;
    }

    public function pagination(int $page, int $perPage): self
    {
        $this->limitOffset->pagination($page, $perPage);
        return $this;
    }

    /**
     * Compile the query without mutating its internal parameter state.
     */
    public function compile(?DialectInterface $dialect = null): CompiledQuery
    {
        $bag = $this->parameters->withDialect($dialect ?? Query::dialectDefault());
        $sql = $this->buildSql($bag);

        return new CompiledQuery($sql, $bag->getParameters(), $bag->getTypes());
    }

    /** @internal Used by correlated aggregate expressions. */
    public function compileBody(
        ParameterBag $target,
        bool $includeGroupBy = false,
        bool $includeHaving = false,
    ): string {
        $source = $this->parameters->withDialect($target->dialect());
        $sql = $this->buildBodySql($source, $includeGroupBy, $includeHaving);

        return ParameterImporter::importSql($sql, $source, $target);
    }

    private function buildSql(ParameterBag $bag): string
    {
        $select = $this->buildSelectItems($bag);
        $from = $this->buildFrom($bag);
        $joins = $this->buildJoins($bag);
        $where = $this->buildWhere($bag);
        $groupBy = $this->buildGroupBy();
        $having = $this->buildHaving($bag);
        $orderBy = $this->buildOrderBy();

        $parts = array_filter([$select, $from, $joins, $where, $groupBy, $having, $orderBy], static fn($p) => $p !== '');
        return $this->limitOffset->apply(implode(' ', $parts), $bag->dialect(), $orderBy !== '');
    }

    private function buildBodySql(ParameterBag $bag, bool $includeGroupBy, bool $includeHaving): string
    {
        // Ensure JOIN parameters are mapped into this query's bag.
        $from = $this->buildFrom($bag);
        $joins = $this->buildJoins($bag);
        $where = $this->buildWhere($bag);
        $groupBy = $includeGroupBy ? $this->buildGroupBy() : '';
        $having = $includeHaving ? $this->buildHaving($bag) : '';

        $parts = array_filter([$from, $joins, $where, $groupBy, $having], static fn($p) => $p !== '');
        return implode(' ', $parts);
    }

    private function buildSelectItems(ParameterBag $bag): string
    {
        if (empty($this->selectItems)) {
            return 'SELECT *';
        }

        $items = [];
        $mainAlias = $this->alias ?? $this->table;

        foreach ($this->selectItems as $item) {
            $expr = $item['expr'];
            $alias = $item['alias'];
            $tableAlias = $item['tableAlias'] ?? $mainAlias;

            if ($expr instanceof Expression) {
                $sql = $expr->compile($bag);
            } else {
                $sql = $expr;

                // Join columns use their own table; standalone columns keep the main table context.
                if ($tableAlias !== '' && ($sql !== '*' || $item['tableAlias'] !== null)
                    && !str_contains($sql, '.') && !str_contains($sql, '(')) {
                    $sql = $tableAlias . '.' . $sql;
                }
            }

            if ($alias !== null && $alias !== '') {
                $items[] = $sql . ' AS ' . $bag->dialect()->quoteIdentifier($alias);
            } else {
                $items[] = $sql;
            }
        }

        return 'SELECT ' . implode(', ', $items);
    }

    private function buildFrom(ParameterBag $bag): string
    {
        if ($this->table === '') {
            return '';
        }

        $alias = $this->alias;
        $hasAlias = $alias !== null && $alias !== '';

        return 'FROM ' . ($hasAlias ? $bag->dialect()->tableAlias($this->table, $alias) : $this->table);
    }


    private function buildJoins(ParameterBag $bag): string
    {
        if (empty($this->joins)) {
            return '';
        }

        $joins = [];
        foreach ($this->joins as $join) {
            $type = $join['type']->value;
            $onSql = $join['on']->compile($bag);

            $joins[] = sprintf(
                '%s %s ON %s',
                $type,
                $bag->dialect()->tableAlias($join['table'], $join['alias']),
                $onSql
            );
        }

        return implode(' ', $joins);
    }

    private function buildWhere(ParameterBag $bag): string
    {
        $whereSql = $this->renderWheres($bag);
        if ($whereSql === null) {
            return '';
        }

        return 'WHERE ' . $whereSql;
    }

    private function buildGroupBy(): string
    {
        return $this->groupBy->toSql();
    }

    private function buildOrderBy(): string
    {
        return $this->orderBy->toSql();
    }


    /**
     * Build COUNT(*) query for pagination.
     *
     * - Removes ORDER BY and LIMIT/OFFSET (count should ignore pagination).
     * - If GROUP BY / HAVING exists, wraps query in a derived table and counts rows of that result.
     *
     * IMPORTANT: Uses a clone with a fresh ParameterBag to avoid parameter duplication.
     */
    public function forPaginationCount(string $countAlias = 'total', ?DialectInterface $dialect = null): CompiledQuery
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $countAlias) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Invalid count alias "%s". Expected a simple SQL identifier.',
                $countAlias,
            ));
        }

        $q = clone $this;
        $q->parameters = clone $this->parameters;
        $q->orderBy = new OrderByClause();
        $q->limitOffset = new LimitOffset();

        $dialect ??= Query::dialectDefault();
        $inner = $q->compile($dialect);
        $sql = 'SELECT COUNT(*) AS ' . $countAlias . ' FROM ' . $dialect->tableAlias('(' . $inner->sql . ')', '_count');

        return new CompiledQuery($sql, $inner->params, $inner->types);
    }
}
