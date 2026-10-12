# WilyCode Database Query

[![PHP 8.4+](https://img.shields.io/badge/PHP-8.4%2B-777BB4)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Tests](https://github.com/wilycode/database-query/actions/workflows/tests.yml/badge.svg)](https://github.com/wilycode/database-query/actions/workflows/tests.yml)

**A lightweight SQL query builder and canonical parameter representation for PHP 8.4+.** Build and compile SQL without opening a database connection. Drivers handle execution separately.

> **WilyCode PHP Ecosystem**
>
> Part of **[WilyCode](https://github.com/wilycode/wilycode)** — independent, reusable PHP components with their own repositories, versions, and Composer packages.
>
> **[Explore the WilyCode ecosystem →](https://github.com/wilycode/wilycode)**

## Features

- `SELECT`, `INSERT`, `UPDATE`, `DELETE`, `UNION`, and raw SQL.
- Column selection and aliases; `INNER`, `LEFT`, and `RIGHT JOIN`, with optional selected join columns.
- `WHERE`, nested condition groups, `GROUP BY`, `HAVING`, sorting, and pagination including a separate count query.
- Subqueries and MySQL-oriented JSON expressions, including correlated JSON arrays.
- Unique named placeholders, type information, parameter-list expansion, and separate driver binding.
- No required database connection, PDO, MySQLi, or external runtime libraries.

## Requirements and installation

PHP **8.4+** with the `ctype` extension.

```bash
composer require wilycode/database-query
```

## Quick start

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use WilyCode\Database\Query\Query;

$query = Query::select('users', 'u')
    ->columns(['id' => 'id', 'email' => 'email'])
    ->where('u.active', true)
    ->orderBy('u.id', 'DESC')
    ->limit(10);

$compiled = $query->compile();

echo $compiled->sql;
print_r($compiled->params);
print_r($compiled->types);
```

`compile()` **does not execute SQL**. It returns a `CompiledQuery` with read-only properties:

| Property | Contents |
| --- | --- |
| `$compiled->sql` | SQL with named placeholders (for example `:param_1`). |
| `$compiled->params` | Bound values keyed by placeholders *including* the `:`. |
| `$compiled->types` | `ParameterType` values for those placeholders. |

Recompiling an unchanged builder produces an equivalent result. SQL identifiers and SQL fragments are **not** automatically escaped or validated as trusted application inputs.

## Complete statement API

The following table lists the top-level entry points available through `WilyCode\Database\Query\Query`.

| Entry point | Purpose |
| --- | --- |
| `Query::select(string $table = '', ?string $alias = null)` | Build `SELECT` (alias is optional). |
| `Query::insert(string $table)` | Build `INSERT`. |
| `Query::update(string $table)` | Build `UPDATE`. |
| `Query::delete(string $table)` | Build `DELETE`. |
| `Query::raw(string $sql, array $parameters = [])` | Compile a complete raw statement safely binding values. |
| `Query::rawExp(string $sql, array $parameters = [])` | Create an expression to embed in a query. |
| `Query::union(Select ...$selects)` | Combine `SELECT` statements with `UNION` (not `UNION ALL`). |

### SELECT columns and aliases

| Method | Purpose |
| --- | --- |
| `column(string $name, ?string $alias = null)` | Add one selected expression/column. |
| `columns(array $columns)` | Add multiple columns; `resultAlias => expression`, or numeric keys for no result alias. |
| `columnRaw(string $alias, string $sql, array $parameters = [])` | Add a raw, parameterized expression. |
| `columnExp(string $alias, Expression $expression)` | Add an `Expression` (e.g. JSON or a subquery) under a result alias. |

```php
$compiled = Query::select('posts', 'p')
    ->columns([
        'id' => 'id',         // p.id AS `id`
        'title' => 'title',   // p.title AS `title`
        'authorId' => 'p.user_id',
        'p.created_at',       // no result alias
    ])
    ->columnRaw('titleLength', 'CHAR_LENGTH(p.title)')
    ->compile();
```

Simple unqualified column names are automatically qualified with the main table alias (or table name). Explicit qualified names such as `p.user_id` are left unchanged. A numeric array key selects without adding `AS` (for example `['p.*']`). A string array key represents the **result alias**, not a database table prefix.

### JOINs, aliases, prefixes, and returned columns

| Method | JOIN type | Signature |
| --- | --- | --- |
| `join()` | `INNER JOIN` | `join(string $table, string $alias, string $on, array $parameters = [], array $columns = [])` |
| `leftJoin()` | `LEFT JOIN` | Same five arguments. |
| `rightJoin()` | `RIGHT JOIN` | Same five arguments. |

The arguments are, in order: **table name**, **SQL table alias**, **ON condition**, **ON bound values**, and **columns to add to the SELECT list**.

```php
$compiled = Query::select('posts', 'p')
    ->columns(['postId' => 'id', 'title' => 'title'])
    ->leftJoin(
        'users',
        'u',
        'u.id = p.user_id AND u.active = ?',
        [true],
        ['authorId' => 'id', 'authorName' => 'name'],
    )
    ->leftJoin(
        'categories',
        'c',
        'c.id = p.category_id',
        [],
        ['categoryName' => 'name'],
    )
    ->where('p.published', true)
    ->compile();

// SELECT p.id AS `postId`, p.title AS `title`,
//        u.id AS `authorId`, u.name AS `authorName`,
//        c.name AS `categoryName` FROM posts AS p
// LEFT JOIN users AS u ON ... LEFT JOIN categories AS c ON ...
```

**Distinguish these different meanings of “prefix”:**

- **Table alias / column qualifier:** `Query::select('posts', 'p')`, `leftJoin('users', 'u', ...)`. A joined column `'name'` is rendered as `u.name`; a normal selected column `'title'` as `p.title`. In `where()`, `orderBy()`, and `groupBy()` **write qualified names yourself**, e.g. `p.id` or `u.name`.
- **Result field alias:** `['authorName' => 'name']` creates the SQL result field `u.name AS authorName` (with MySQL-style quoting around the alias) for joined columns, not a nested `author` object. Result rows are flat.
- **Physical table prefix:** there is **no** `prefix()`, `tablePrefix()`, or automatic prefix setting. Specify actual table names, e.g. `Query::select('app_posts', 'p')->leftJoin('app_users', 'u', 'u.id = p.user_id')`.
- **Bound-parameter prefix:** `JsonArraySubquery::paramPrefix('comments')` affects placeholder names such as `:comments_param_1` — **not** table names or result fields. See JSON examples below.

For a JOIN column list, `['authorName' => 'name']` qualifies a simple name with the JOIN alias; `['u.id']` keeps an explicit qualifier; `['*']` selects the joined table's `u.*`. An empty array or omitted fifth argument adds **no** joined columns. The SQL `ON` expression is raw SQL: supply values through `?` or `:name` placeholders, never concatenate untrusted values.

The builder supports `INNER`, `LEFT`, and `RIGHT` joins; it does **not** provide dedicated `crossJoin()` or `fullJoin()` methods.

### WHERE filters and groups

`Select`, `Update`, and `Delete` support the following methods:

| Method | Purpose |
| --- | --- |
| `where($column, $value, $operator = '=', $logical = 'AND')` | Add a comparison. |
| `andWhere($column, $value, $operator = '=')` | Add an `AND` comparison. |
| `orWhere($column, $value, $operator = '=')` | Add an `OR` comparison. |
| `whereColumn($left, $right, $operator = '=', $logical = 'AND')` | Compare two SQL columns, without a bound value. |
| `whereExp(Expression $expression, $logical = 'AND')` | Add an expression. |
| `whereRaw($sql, $parameters = [], $logical = 'AND')` | Add raw SQL with value placeholders. |
| `andWhereRaw($sql, $parameters = [])` / `orWhereRaw(...)` | Add parameterized raw SQL with a chosen conjunction. |
| `whereGroup(callable $callback, $logical = 'AND')` | Add a parenthesized condition group. |
| `andWhereGroup($callback)` / `orWhereGroup($callback)` | Explicit grouping with `AND` or `OR`. |

Supported comparison operators: `=`, `<>` (also `!=`), `>`, `<`, `>=`, `<=`, `LIKE`, `NOT LIKE`, `IN`, `NOT IN`, `IS`, and `IS NOT` (strings or `ComparisonOperator` enum cases).

```php
$compiled = Query::select('users', 'u')
    ->where('u.active', true)
    ->whereGroup(fn ($group) => $group
        ->where('u.role', 'admin')
        ->orWhere('u.role', 'editor'))
    ->andWhere('u.id', [5, 8, 13], 'IN')
    ->whereColumn('u.manager_id', 'u.id', '<>')
    ->where('u.deleted_at', null) // IS NULL
    ->compile();
```

`where('x', null)` becomes `x IS NULL`; `where('x', null, '<>')` becomes `x IS NOT NULL`. For builder comparisons, empty `IN` arrays compile to a false condition (`0=1`), and empty `NOT IN` arrays to a true condition (`1=1`). Array values are only accepted with `IN`/`NOT IN`.

### GROUP BY and HAVING

`Select` supports `groupBy(string|array ...$columns)`, plus:

| Method | Purpose |
| --- | --- |
| `having($column, $value, $operator = '=', $logical = 'AND')` | Add an aggregate/result filter. |
| `andHaving(...)` / `orHaving(...)` | Explicit conjunctions. |
| `havingExp(Expression $expression, $logical = 'AND')` | Expression in `HAVING`. |
| `havingRaw($sql, $parameters = [], $logical = 'AND')` | Raw SQL with bound values. |
| `andHavingRaw(...)` / `orHavingRaw(...)` | Raw filter with chosen conjunction. |
| `havingGroup($callback, $logical = 'AND')` | Parenthesized group. |
| `andHavingGroup($callback)` / `orHavingGroup($callback)` | Group with chosen conjunction. |

```php
$compiled = Query::select('orders', 'o')
    ->columns(['customerId' => 'customer_id'])
    ->columnRaw('total', 'SUM(o.amount)')
    ->groupBy('o.customer_id')
    ->having('total', 100, '>=')
    ->orHavingRaw('COUNT(*) > ?', [10])
    ->compile();
```

### Sorting and pagination

`Select` and `Union` support:

| Method | Effect |
| --- | --- |
| `orderBy(string $column, string|OrderDirection $direction = 'ASC')` | Adds an `ORDER BY` item; can be called more than once. |
| `limit(int $limit)` | Maximum rows; cannot be negative. |
| `offset(int $offset)` | Skip rows; cannot be negative. |
| `pagination(int $page, int $perPage)` | Sets `LIMIT` and `OFFSET` for a 1-based page number. |

```php
$query = Query::select('posts', 'p')
    ->where('p.published', true)
    ->orderBy('p.created_at', 'DESC')
    ->orderBy('p.id', 'ASC')
    ->pagination(2, 20);

$page = $query->compile(); // LIMIT 20 OFFSET 20
$count = $query->forPaginationCount(); // Count without ORDER BY and pagination
```

`Select::forPaginationCount(string $countAlias = 'total')` returns a separate `CompiledQuery` counting the selected result. It uses a derived table, so grouping and `HAVING` still count result rows correctly. The count alias must be a simple SQL identifier.

### INSERT, UPDATE, and DELETE

```php
$insert = Query::insert('users')
    ->values(['email' => 'hello@example.test', 'active' => true])
    ->compile();

$insertMany = Query::insert('users')->valuesMany([
    ['email' => 'alice@example.test', 'active' => true],
    ['email' => 'bob@example.test', 'active' => false],
])->compile();

$update = Query::update('users')
    ->set(['active' => false])
    ->where('id', 15)
    ->compile();

$delete = Query::delete('users')->where('id', 15)->compile();
```

`Insert::values(array $row)` inserts one row; `valuesMany(array $rows)` inserts multiple rows, with **identical column names in identical order**. `Update::set(array $values)` adds assignments; repeat assignments for a column are rejected. `Update` and `Delete` inherit all the `WHERE` methods described above. **They do not automatically require a `WHERE` clause**: always add one when needed to avoid modifying an entire table.

### Raw SQL and parameter binding

```php
$compiled = Query::raw(
    'SELECT * FROM users WHERE id IN (:ids) AND status = :status',
    ['ids' => [10, 20, 30], 'status' => 'active'],
)->compile();

$other = Query::select('users', 'u')
    ->whereRaw('u.age > ? AND u.email LIKE ?', [18, '%@example.test'])
    ->columnRaw('fullName', 'CONCAT(u.first_name, ?, u.last_name)', [' '])
    ->compile();
```

Both positional `?` and named `:name` value placeholders are accepted in raw expressions. The compiler produces unique canonical named placeholders; repeated named occurrences are safely rewritten. Arrays are expanded only when the placeholder is enclosed in parentheses (for example `IN (:ids)` or `IN (?)`); an empty array uses an empty-result subquery rather than `IN ()`. Missing, unused, or conflicting parameters are rejected. Placeholder-like text inside SQL strings and comments is ignored.

The public `ParameterBag` supports `add($value, ?string $name = null, ?ParameterType $type = null)`, `addAuto($value, string $prefix = 'param', ?ParameterType $type = null)`, `addList(array $values, string $prefix = 'param', ?ParameterType $type = null)`, `getParameters()`, `getTypes()`, and `has($name)`. These are lower-level primitives, not a setting for table prefixes.

Parameter types are represented by `ParameterType`: `STRING`, `INTEGER`, `BOOLEAN`, `NULL`, `LOB`, `FLOAT`, `DATE`, and `DATETIME`. `ParameterType::fromPhpType()` infers types from PHP values. `DateTimeInterface` values and backed enums are normalized when bound.

Other public enums used by the API are `OrderDirection` (`ASC`, `DESC`), `ComparisonOperator` (the operators listed above), `LogicalOperator` (`AND`, `OR`, `EMPTY`), and `JoinType` (`INNER`, `LEFT`, `RIGHT`). The enum conversion helpers `fromString()` validate supported string inputs. `ParameterProcessor::process()` is a lower-level parser for SQL placeholders; most applications should use `Query::raw()`, `whereRaw()`, or `columnRaw()` instead.

### UNION

```php
$customers = Query::select('customers', 'c')
    ->column('c.id', 'id')
    ->where('c.active', true);

$staff = Query::select('staff', 's')
    ->column('s.id', 'id')
    ->where('s.active', true);

$compiled = Query::union($customers, $staff)
    ->orderBy('id')
    ->pagination(1, 20)
    ->compile();
```

`Query::union(Select ...$selects)` creates a `Union`; `Union::add(QueryInterface $query)` appends another branch. Each branch is parenthesized and its parameter names are kept collision-free. `Union` also provides `orderBy()`, `limit()`, `offset()`, `pagination()`, and `compile()`. The implementation generates **`UNION` only**; it has no dedicated `unionAll()`.

### Subqueries and expressions

Use `Subquery` where an `Expression` is accepted; values are imported into the outer parameter bag without collisions.

```php
use WilyCode\Database\Query\Expression\Subquery;
use WilyCode\Database\Query\Expression\ValueExpression;

$sub = Query::select('orders', 'o')
    ->columnRaw('count', 'COUNT(*)')
    ->where('o.user_id', 10);

$compiled = Query::select('users', 'u')
    ->columnExp('ordersCount', new Subquery($sub))
    ->columnExp('label', ValueExpression::of('example'))
    ->compile();
```

`ValueExpression::of($value)` binds a literal; `RawExpression::raw($sql, $params)` and `Query::rawExp(...)` create custom SQL expressions. `Expression` defines `compile(ParameterBag $bag): string` for advanced extensions.

### JSON columns and nested objects

The library provides MySQL-oriented expressions: `JsonObject::of(array $fields, ?string $nullGuardColumn = null)`, `JsonArray::of(array $items)`, and `JsonArraySubquery::of(Select $query, string|Expression $item, bool $coalesceEmptyArray = true, bool $distinct = false, bool $includeGroupBy = false, bool $includeHaving = false)`.

```php
use WilyCode\Database\Query\Expression\JsonArray;
use WilyCode\Database\Query\Expression\JsonObject;
use WilyCode\Database\Query\Expression\ValueExpression;

$compiled = Query::select('users', 'u')
    ->column('u.id', 'id')
    ->columnExp('profile', JsonObject::of([
        'id' => 'u.id',                 // SQL column, not a string literal
        'name' => 'u.name',
        'label' => ValueExpression::of('member'), // a bound literal string
        'flags' => JsonArray::of([true, 42]),
    ]))
    ->compile();
```

**Important:** bare strings inside `JsonObject` / `JsonArray` are treated as *SQL fragments or column references*. Use `ValueExpression::of('literal string')` when binding text values.

For an optional `LEFT JOIN` relation, `JsonObject::of($fields, 'u.id')` emits `NULL` when `u.id IS NULL`, avoiding an object whose fields are all null.

#### Correlated JSON arrays and parameter prefixes

```php
use WilyCode\Database\Query\Expression\JsonArraySubquery;
use WilyCode\Database\Query\Expression\JsonObject;

$comments = Query::select('comments', 'c')
    ->whereRaw('c.post_id = p.id')
    ->where('c.approved', true);

$commentItems = JsonArraySubquery::of(
    $comments,
    JsonObject::of(['id' => 'c.id', 'body' => 'c.body']),
)->paramPrefix('comments');

$compiled = Query::select('posts', 'p')
    ->column('p.id', 'id')
    ->columnExp('comments', $commentItems)
    ->compile();
```

`paramPrefix('comments')` prefixes imported **placeholders**, e.g. `:comments_param_1`, to avoid collisions. If a `JsonArraySubquery` is passed directly to `columnExp('comments', ...)` and has no explicitly configured prefix, the column alias `comments` is automatically used as its parameter prefix. `paramPrefix(null)` resets it. This **does not set the SQL table prefix**.

`JsonArraySubquery` creates a JSON array using `JSON_ARRAYAGG`. `coalesceEmptyArray` emits an empty JSON array instead of `NULL` when no rows match; `distinct` adds `DISTINCT`; `includeGroupBy` / `includeHaving` control whether clauses from its source `Select` are included. This expression is intended for MySQL 8+ / compatible MariaDB installations; check SQL support and syntax in your database version.

### Binding to a driver

`CompiledQuery` is a driver-neutral intermediate representation. A driver-specific `BinderInterface` converts it into `BoundQuery` (`sql`, `params`, `types`); another package is responsible for execution.

```php
use WilyCode\Database\Query\Binding\BinderInterface;
use WilyCode\Database\Query\CompiledQuery;

function prepareForDriver(BinderInterface $binder, CompiledQuery $query): void
{
    $bound = $binder->bind($query);
    // $bound->sql, $bound->params, $bound->types
    // Pass this result to your concrete driver.
}
```

## Important limitations and security

- This package **compiles SQL but does not connect to or query a database**.
- A physical table prefix is not configured automatically; pass exact table names to the builder.
- JOIN result columns are **flat**, not automatically nested objects. JSON expressions are available for explicit structured results.
- Table names, column identifiers, sorting expressions, and raw SQL are not value-bound. **Never interpolate untrusted identifiers or SQL fragments**; allow-list or validate them in the calling application.
- The generated SQL includes MySQL-oriented quoting and JSON functions and is **not guaranteed portable** across every SQL dialect.
- `UPDATE` and `DELETE` can compile without a `WHERE` clause.

## Related packages

| Package | Purpose |
| --- | --- |
| [wilycode/database-driver](https://github.com/wilycode/database-driver) | Database connection, query, and transaction contracts. |
| [wilycode/database-driver-pdo](https://github.com/wilycode/database-driver-pdo) | PDO adapter and canonical query binder. |
| [wilycode/database-driver-mysqli](https://github.com/wilycode/database-driver-mysqli) | MySQLi adapter and binder. |

These are **separate packages**, not runtime dependencies of the query builder.

## Development

```bash
composer install
composer validate --strict
composer test
```

Tests run on PHP 8.4 in [GitHub Actions](https://github.com/wilycode/database-query/actions). Tests remain in Git, but are omitted from Composer distribution archives.

## Contributing

Issues and pull requests are welcome. See [CONTRIBUTING.md](CONTRIBUTING.md) and the [issue tracker](https://github.com/wilycode/database-query/issues).

## License

MIT © 2026 Simon Piter. See [LICENSE](LICENSE).
