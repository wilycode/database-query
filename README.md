# WilyCode Database Query

[![PHP 8.4+](https://img.shields.io/badge/PHP-8.4%2B-777BB4)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Tests](https://github.com/wilycode/database-query/actions/workflows/tests.yml/badge.svg)](https://github.com/wilycode/database-query/actions/workflows/tests.yml)

**A fluent SQL query builder for PHP 8.4+.** Build `SELECT`, `INSERT`, `UPDATE`, `DELETE`, and `UNION` statements, then compile them into SQL and safely bound values—**without opening a database connection**.

> [!IMPORTANT]
> ### WilyCode PHP Ecosystem
> **WilyCode Database Query** is part of the [**WilyCode PHP Ecosystem**](https://github.com/wilycode/wilycode), a collection of independent, reusable PHP packages. Every package has its own repository, releases, and Composer installation.
>
> **[Explore all WilyCode libraries →](https://github.com/wilycode/wilycode)**

## SQL dialects (0.2)

Part of the **[WilyCode ecosystem](https://github.com/wilycode/wilycode)**.

One package contains the builder and its dialects. MySQL is the standalone default.
The same query can be compiled repeatedly for a different platform without changing
its parameter state, including nested expressions, WHERE/HAVING groups and UNIONs.

```php
use WilyCode\Database\Query\Query;
use WilyCode\Database\Query\Dialect\PostgreSqlDialect;
use WilyCode\Database\Query\Dialect\SqliteDialect;

$query = Query::select('users')->column('id', 'id')->orderBy('id')->limit(10);
$mysql = $query->compile();
$postgres = $query->compile(new PostgreSqlDialect());

// Optional process-wide default for standalone compilation:
Query::dialectDefault(new SqliteDialect());
$current = Query::dialectDefault();
Query::dialectDefault(null); // reset to MySQL

$count = $query->forPaginationCount('total', new PostgreSqlDialect());
```

Prefer explicit dialects in workers serving multiple connections. The executor uses
its own explicit choice or the actual connection platform; it never changes this
process default. PDO is a transport, not a dialect: PDO/MySQL uses MySQL SQL,
PDO/PostgreSQL uses PostgreSQL SQL, and PDO/SQLite uses SQLite SQL.

| Dialect | Generated SQL | Requirements / limitations |
| --- | --- | --- |
| `MySqlDialect` | Backtick result aliases, `LIMIT/OFFSET`, `JSON_OBJECT/ARRAY/ARRAYAGG` | MySQL 8+ or MariaDB 10.5+ for JSON aggregation; DISTINCT aggregation rejected for this common dialect |
| `PostgreSqlDialect` | Double-quoted result aliases, `LIMIT/OFFSET`, JSONB builders and aggregation | PostgreSQL with JSONB; JSON parameters receive explicit casts |
| `SqliteDialect` | Double-quoted result aliases, SQLite UNION operands, JSON functions | SQLite 3.39+ with JSON enabled (including RIGHT JOIN support) |
| SQL Server / Oracle | Extension points only | No built-in dialect or claim of driver compatibility yet |

Only SQL owned by the builder is adapted. Table names, column strings, ORDER BY,
GROUP BY and raw fragments retain the existing trusted-SQL contract. They are not
user input and are not automatically quoted or translated. Use
`$dialect->quoteIdentifier($name)` for a **single** identifier; quote each part of a
qualified name separately. RawQuery and already CompiledQuery instances keep their
SQL unchanged. Parameter binding remains the binder's responsibility.

PostgreSQL raw JSON `?`, `?|`, `?&` operators are not accepted by the canonical
placeholder format; use the corresponding functions (`jsonb_exists`, etc.).
Dollar-quoted strings and `#>` / `#>>` operators are recognized by the scanner.
Raw fragments must follow the scanner's documented common SQL subset; they are not
a general-purpose SQL parser or cross-platform translation layer.

JSON strings supplied as values remain SQL fragments; wrap literal strings in
`ValueExpression::of()`. SQLite preserves boolean/float literal types and restores
JSON typing around `JsonArraySubquery`. For ordinary scalar `Subquery` results used
as JSON values, explicitly wrap the SQL in the engine's JSON conversion function.
An aggregate source uses FROM/JOIN/WHERE and optionally GROUP BY/HAVING; its SELECT
list, ordering and pagination are not applied. With grouping, the caller must
ensure that a scalar aggregate subquery returns at most one row.

### Migration from 0.1

Custom `QueryInterface` implementations must accept
`compile(?DialectInterface $dialect = null)`. The `Expression` signature is unchanged;
custom expressions read the chosen dialect from `$bag->dialect()`. SQL expressions
in WHERE/HAVING are evaluated at compilation time, so changes to a referenced
subquery before compilation are visible. Raw conditions keep their existing binding
behavior. MySQL offset-only queries now emit the required unlimited LIMIT, and
unsupported MySQL `JSON_ARRAYAGG(DISTINCT ...)` throws instead of producing invalid SQL.

See [dialect architecture and rollout](docs/dialects.md) for extension contracts,
release ordering and integration coverage.

## Documentation

This README is organized as a topic-by-topic guide, with **PHP examples followed by the SQL they compile to**. Use the links below to jump to a feature.

| Getting started | Query building | Advanced topics |
| --- | --- | --- |
| [Introduction](#introduction) | [SELECT](#select) | [Raw SQL & parameters](#raw-sql-and-parameters) |
| [Installation](#installation) | [JOIN](#join) | [UNION](#union) |
| [Quick start](#quick-start) | [WHERE](#where) | [Subqueries](#subqueries-and-expressions) |
| [Compiling queries](#compiling-queries) | [GROUP BY & HAVING](#group-by-and-having) | [JSON expressions](#json-expressions) |
| [API reference](#api-reference) | [ORDER BY & pagination](#order-by-and-pagination) | [Driver integration](#driver-integration) |
| [Security & limits](#security-and-limitations) | [INSERT / UPDATE / DELETE](#insert-update-and-delete) | [Development](#development) |

## Introduction

`wilycode/database-query` focuses on **building SQL, not executing it**. It provides:

- Fluent builders for `SELECT`, `INSERT`, `UPDATE`, `DELETE`, and `UNION`.
- Table aliases and selected-column aliases, including columns selected from joins.
- Conditions, nested `WHERE`/`HAVING` groups, grouping, sorting, and pagination.
- Subqueries, raw expressions, and MySQL-oriented JSON object/array expressions.
- Parameter binding with unique named placeholders and optional type metadata.
- A driver-independent `CompiledQuery` result for separate PDO/MySQLi adapters.

The package does **not** include a database connection, query execution, model hydration, schema migrations, automatic table prefixes, or a universal SQL-dialect compiler.

## Installation

**Requirements:** PHP 8.4 or newer and the `ctype` extension. No third-party runtime packages are required.

```bash
composer require wilycode/database-query
```

For a fresh application, include Composer's autoloader before using the library:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use WilyCode\Database\Query\Query;
```

## Quick start

Build a query, apply conditions and sorting, then call `compile()`:

```php
use WilyCode\Database\Query\Query;

$query = Query::select('users', 'u')
    ->columns(['id' => 'id', 'email' => 'email'])
    ->where('u.active', true)
    ->orderBy('u.id', 'DESC')
    ->limit(10);

$compiled = $query->compile();

echo $compiled->sql;
print_r($compiled->params);
```

**Compiled SQL**

```sql
SELECT u.id AS `id`, u.email AS `email`
FROM users AS u
WHERE u.active = :param_1
ORDER BY u.id DESC
LIMIT 10
```

**Bound values**

```php
[':param_1' => true]
```

> [!NOTE]
> SQL examples below are formatted for readability. `compile()` usually returns the SQL on a single line. Values stay separate from SQL; placeholders are not replaced with literal values.

## Compiling queries

All statement builders expose `compile(): CompiledQuery`.

| Property | Type | Description |
| --- | --- | --- |
| `$compiled->sql` | `string` | SQL statement containing named placeholders. |
| `$compiled->params` | `array` | Bound values, keyed by placeholder names **including `:`**. |
| `$compiled->types` | `array` | Parameter type information (`ParameterType` enum values). |

```php
$compiled = Query::select('users', 'u')
    ->where('u.id', 15)
    ->compile();

// $compiled->sql:   SELECT * FROM users AS u WHERE u.id = :param_1
// $compiled->params: [':param_1' => 15]
```

Calling `compile()` again on an unchanged `SELECT` produces an equivalent query without duplicating its parameters. **Compilation does not contact the database.** To execute compiled queries, use a separate driver such as [`wilycode/database-pdo`](https://github.com/wilycode/database-pdo).

---

## SELECT

### Basic SELECT

`Query::select(string $table = '', ?string $alias = null)` starts a query. Pass a short table alias to simplify joins and field references.

```php
$query = Query::select('posts', 'p')
    ->columns([
        'postId' => 'id',
        'title' => 'title',
    ]);
```

**Compiled SQL**

```sql
SELECT p.id AS `postId`, p.title AS `title`
FROM posts AS p
```

If no columns are supplied, the builder uses `SELECT *`.

### Selecting and aliasing columns

| Method | Example | Behavior |
| --- | --- | --- |
| `column($name, $alias = null)` | `->column('p.id', 'postId')` | Select one column with an optional result alias. |
| `columns($columns)` | `->columns(['id' => 'id', 'title' => 'title'])` | Select several columns. Array **keys** are result aliases. |
| `columnRaw($alias, $sql, $params = [])` | `->columnRaw('length', 'CHAR_LENGTH(p.title)')` | Add a raw SQL expression with an alias. |
| `columnExp($alias, $expression)` | `->columnExp('profile', JsonObject::of(...))` | Select a typed expression. |

```php
$query = Query::select('posts', 'p')
    ->columns([
        'postId' => 'id',
        'title' => 'title',
        'p.created_at', // numeric key: select without AS
    ])
    ->columnRaw('titleLength', 'CHAR_LENGTH(p.title)');
```

**Compiled SQL**

```sql
SELECT p.id AS `postId`, p.title AS `title`, p.created_at,
       CHAR_LENGTH(p.title) AS `titleLength`
FROM posts AS p
```

Unqualified *simple* column names are qualified using the main table alias (`id` → `p.id`). For `WHERE`, `ORDER BY`, and `GROUP BY`, provide qualified names explicitly when ambiguity is possible. Raw SQL and complex identifiers are the application's responsibility.

---

## JOIN

### INNER, LEFT, and RIGHT JOIN

Available methods: `join()`, `leftJoin()`, and `rightJoin()`.

They share the same signature:

```php
public function leftJoin(
    string $table,
    string $alias,
    string $on,
    array $parameters = [],
    array $columns = [],
): self
```

The **fifth argument** is important: it selects extra columns from the joined table, without having to add them separately to `columns()`.

```php
$query = Query::select('posts', 'p')
    ->columns(['postId' => 'id', 'title' => 'title'])
    ->leftJoin(
        'users',
        'u',
        'u.id = p.user_id',
        [],
        ['authorName' => 'name'],
    )
    ->leftJoin(
        'categories',
        'c',
        'c.id = p.category_id',
        [],
        ['categoryName' => 'name'],
    );
```

**Compiled SQL**

```sql
SELECT p.id AS `postId`, p.title AS `title`,
       u.name AS `authorName`, c.name AS `categoryName`
FROM posts AS p
LEFT JOIN users AS u ON u.id = p.user_id
LEFT JOIN categories AS c ON c.id = p.category_id
```

### JOIN columns and aliases

A joined column name such as `'name'` is automatically qualified using the joined table's alias (`u.name`, `c.name`).

| Fifth argument (`$columns`) | SQL selection |
| --- | --- |
| `['authorName' => 'name']` | `u.name AS \`authorName\`` |
| `['authorId' => 'id']` | `u.id AS \`authorId\`` |
| `['u.id']` | `u.id` (no result alias) |
| `['*']` | `u.*` |
| `[]` | No additional selected columns |

The returned row is **flat** (for example, `['authorName' => 'Alice']`), not a nested `author` object.

### JOIN with conditions and bound values

Use `?` or named placeholders in the `ON` condition, and pass values as the fourth argument:

```php
$query = Query::select('posts', 'p')
    ->leftJoin(
        'users',
        'u',
        'u.id = p.user_id AND u.active = ?',
        [true],
        ['authorName' => 'name'],
    );
```

**Compiled SQL**

```sql
SELECT u.name AS `authorName`
FROM posts AS p
LEFT JOIN users AS u ON u.id = p.user_id AND u.active = :param_1
```

```php
// $compiled->params === [':param_1' => true]
```

### What about prefixes?

Three different concepts must not be confused:

- **Table alias:** `'users', 'u'` means `users AS u` and enables `u.name`.
- **Result alias:** `['authorName' => 'name']` means `u.name AS \`authorName\``.
- **Table name prefix:** not managed by this package; if your physical table is `app_users`, pass `'app_users'` explicitly.

There is **no sixth `$prefix` argument**, no automatic `categoryName` generator, and no implicit mapping from `category.name` to an object. For nested data, see [JSON expressions](#json-expressions).

> [!NOTE]
> The builder provides `INNER`, `LEFT`, and `RIGHT JOIN` methods. It has no dedicated `FULL JOIN`, `CROSS JOIN`, or arbitrary JOIN-builder callback API.

---

## WHERE

### Basic comparisons

`where()` has the signature `where($column, $value, $operator = '=', $logical = 'AND')`. Use `andWhere()` and `orWhere()` for clearer intent.

```php
$query = Query::select('users', 'u')
    ->where('u.active', true)
    ->andWhere('u.age', 18, '>=')
    ->orWhere('u.role', 'admin');
```

**Compiled SQL**

```sql
SELECT * FROM users AS u
WHERE u.active = :param_1 AND u.age >= :param_2 OR u.role = :param_3
```

Be mindful of SQL's `AND`/`OR` precedence. Use condition groups when you need explicit parentheses.

Supported operators: `=`, `<>` (or `!=`), `>`, `<`, `>=`, `<=`, `LIKE`, `NOT LIKE`, `IN`, `NOT IN`, `IS`, `IS NOT`.

### Nested WHERE groups

```php
$query = Query::select('posts', 'p')
    ->where('p.published', true)
    ->whereGroup(fn ($group) => $group
        ->where('p.role', 'admin')
        ->orWhere('p.role', 'editor'));
```

**Compiled SQL**

```sql
SELECT * FROM posts AS p
WHERE p.published = :param_1
  AND (p.role = :param_2 OR p.role = :param_3)
```

You can also use `andWhereGroup()` and `orWhereGroup()`.

### NULL and IN conditions

```php
$query = Query::select('users', 'u')
    ->where('u.deleted_at', null)
    ->where('u.id', [1, 3, 8], 'IN');
```

**Compiled SQL**

```sql
SELECT * FROM users AS u
WHERE u.deleted_at IS NULL
  AND u.id IN (:param_1, :param_2, :param_3)
```

- `where('column', null)` generates `IS NULL`.
- `where('column', null, '<>')` generates `IS NOT NULL`.
- Array values are supported only with `IN` and `NOT IN` in builder conditions.
- Empty `IN` lists compile to an always-false condition; empty `NOT IN` lists to an always-true condition.

### Comparing columns

`whereColumn()` compares two **SQL identifiers**, not a column and a bound value.

```php
$query = Query::select('users', 'u')
    ->whereColumn('u.manager_id', 'u.id', '<>');
```

**Compiled SQL**

```sql
SELECT * FROM users AS u WHERE u.manager_id <> u.id
```

### Raw WHERE expressions

Use raw fragments for conditions outside the fluent API. Bind *values* rather than concatenating them:

```php
$query = Query::select('users', 'u')
    ->whereRaw('LOWER(u.email) LIKE ?', ['%@example.test']);
```

**Compiled SQL**

```sql
SELECT * FROM users AS u WHERE LOWER(u.email) LIKE :param_1
```

`whereExp()`, `whereRaw()`, `andWhereRaw()`, and `orWhereRaw()` are also available for custom expressions and combinations.

---

## GROUP BY and HAVING

`groupBy()` accepts one or multiple columns (including arrays). `having()` works like `where()`, but applies an aggregate/result filter.

```php
$query = Query::select('orders', 'o')
    ->column('o.customer_id', 'customerId')
    ->columnRaw('total', 'SUM(o.amount)')
    ->groupBy('o.customer_id')
    ->having('total', 100, '>=');
```

**Compiled SQL**

```sql
SELECT o.customer_id AS `customerId`, SUM(o.amount) AS `total`
FROM orders AS o
GROUP BY o.customer_id
HAVING total >= :param_1
```

Available HAVING operations: `having()`, `andHaving()`, `orHaving()`, `havingExp()`, `havingRaw()`, `andHavingRaw()`, `orHavingRaw()`, `havingGroup()`, `andHavingGroup()`, and `orHavingGroup()`.

```php
$query->orHavingRaw('COUNT(*) > ?', [10]);
```

Use grouped HAVING conditions when `AND` and `OR` must have explicit precedence.

---

## ORDER BY and pagination

### Sorting results

```php
$query = Query::select('posts', 'p')
    ->orderBy('p.created_at', 'DESC')
    ->orderBy('p.id', 'ASC');
```

**Compiled SQL**

```sql
SELECT * FROM posts AS p ORDER BY p.created_at DESC, p.id ASC
```

`orderBy()` accepts `ASC`, `DESC`, or an `OrderDirection` enum case.

### LIMIT, OFFSET, and pages

```php
$query = Query::select('posts', 'p')
    ->where('p.published', true)
    ->orderBy('p.created_at', 'DESC')
    ->pagination(page: 2, perPage: 20);
```

**Compiled SQL**

```sql
SELECT * FROM posts AS p
WHERE p.published = :param_1
ORDER BY p.created_at DESC
LIMIT 20 OFFSET 20
```

Alternatively use `limit(20)->offset(20)`. Page numbers in `pagination()` are **1-based**; negative limits/offsets and invalid pages are rejected.

### Counting all matching rows

Use `forPaginationCount()` on a `Select` builder:

```php
$page = $query->compile();
$count = $query->forPaginationCount();
```

**Count SQL**

```sql
SELECT COUNT(*) AS total
FROM (
    SELECT * FROM posts AS p WHERE p.published = :param_1
) AS _count
```

The count query ignores `ORDER BY`, `LIMIT`, and `OFFSET`. It preserves the query's filtering and handles `GROUP BY`/`HAVING` by counting rows in the derived query. You may specify a safe result alias: `forPaginationCount('matchingRows')`.

---

## INSERT, UPDATE, and DELETE

### INSERT one row

```php
$query = Query::insert('users')
    ->values(['email' => 'hello@example.test', 'active' => true]);
```

**Compiled SQL**

```sql
INSERT INTO users (email, active) VALUES (:param_1, :param_2)
```

### INSERT multiple rows

```php
$query = Query::insert('users')->valuesMany([
    ['email' => 'alice@example.test', 'active' => true],
    ['email' => 'bob@example.test', 'active' => false],
]);
```

**Compiled SQL**

```sql
INSERT INTO users (email, active)
VALUES (:param_1, :param_2), (:param_3, :param_4)
```

All rows must supply **exactly the same columns in the same order**.

### UPDATE

```php
$query = Query::update('users')
    ->set(['active' => false])
    ->where('id', 15);
```

**Compiled SQL**

```sql
UPDATE users SET active = :param_1 WHERE id = :param_2
```

### DELETE

```php
$query = Query::delete('users')
    ->where('id', 15);
```

**Compiled SQL**

```sql
DELETE FROM users WHERE id = :param_1
```

> [!WARNING]
> `UPDATE` and `DELETE` **do not require a WHERE clause**. Omitting it can affect every row in the table. Add your intended filters before executing the statement.

---

## Raw SQL and parameters

### Compiling raw SQL

You can compile a complete SQL statement without using the fluent builder:

```php
$query = Query::raw(
    'SELECT * FROM users WHERE id IN (:ids) AND status = :status',
    ['ids' => [2, 5, 8], 'status' => 'active'],
);

$compiled = $query->compile();
```

**Compiled SQL**

```sql
SELECT * FROM users
WHERE id IN (:ids_param_1, :ids_param_2, :ids_param_3)
  AND status = :status
```

**Bound values**

```php
[
    ':ids_param_1' => 2,
    ':ids_param_2' => 5,
    ':ids_param_3' => 8,
    ':status' => 'active',
]
```

Raw SQL accepts positional `?` and named `:name` bindings. Repeated named placeholders are canonicalized; SQL strings and comments are respected by the placeholder scanner. Arrays can expand only when their placeholder is inside parentheses (such as `IN (:ids)` or `IN (?)`). Empty arrays compile to an empty-result subquery rather than `IN ()`.

### Query parameters and types

`CompiledQuery` contains three separate pieces of data: `sql`, `params`, and `types`. Parameters are **not interpolated into the SQL string**.

The `ParameterType` enum supports: `STRING`, `INTEGER`, `BOOLEAN`, `NULL`, `LOB`, `FLOAT`, `DATE`, and `DATETIME`. Its `fromPhpType()` helper infers types from PHP values; `DateTimeInterface` and backed enums are normalized by parameter handling.

For lower-level code, `ParameterBag` exposes `add()`, `addAuto()`, `addList()`, `getParameters()`, `getTypes()`, and `has()`. Most applications should use the fluent builders rather than construct parameter bags directly.

### SQL injection safety

Use **bound parameters for untrusted values**. The builder does *not* bind table names, column identifiers, aliases, order expressions, or arbitrary SQL fragments. **Allow-list those inputs in your application**; never concatenate unchecked user input into `columnRaw()`, `whereRaw()`, `orderBy()`, or `JOIN ... ON`.

---

## UNION

Combine multiple SELECT statements into one `UNION` query:

```php
$customers = Query::select('customers', 'c')
    ->column('c.id', 'id')
    ->where('c.active', true);

$staff = Query::select('staff', 's')
    ->column('s.id', 'id')
    ->where('s.active', true);

$query = Query::union($customers, $staff)
    ->orderBy('id')
    ->limit(20);
```

**Compiled SQL**

```sql
(SELECT c.id AS `id` FROM customers AS c WHERE c.active = :union_1_param_1)
UNION
(SELECT s.id AS `id` FROM staff AS s WHERE s.active = :union_2_param_1)
ORDER BY id ASC
LIMIT 20
```

Each branch receives collision-free parameter names. You may add branches with `Union::add()` and call `orderBy()`, `limit()`, `offset()`, or `pagination()` on the union.

> [!NOTE]
> `UNION` is available; a dedicated `unionAll()`, `EXCEPT`, or `INTERSECT` builder is **not** part of the current API.

---

## Subqueries and expressions

`Subquery` embeds a separately built query inside a selected expression. It imports the subquery parameters into the outer query without name collisions.

```php
use WilyCode\Database\Query\Expression\Subquery;

$orders = Query::select('orders', 'o')
    ->columnRaw('cnt', 'COUNT(*)')
    ->whereRaw('o.user_id = u.id');

$query = Query::select('users', 'u')
    ->column('u.id', 'id')
    ->columnExp('orderCount', new Subquery($orders));
```

**Compiled SQL**

```sql
SELECT u.id AS `id`,
       (SELECT COUNT(*) AS `cnt` FROM orders AS o WHERE o.user_id = u.id)
       AS `orderCount`
FROM users AS u
```

Other expression classes include:

| Expression | Purpose |
| --- | --- |
| `RawExpression::raw($sql, $params)` / `Query::rawExp(...)` | Arbitrary SQL fragment with bound values. |
| `ValueExpression::of($value)` | A **bound literal** value. |
| `Subquery` | Nested query expression. |
| `JsonObject`, `JsonArray`, `JsonArraySubquery` | MySQL-oriented JSON structures. |

The `Expression` interface exposes `compile(ParameterBag $bag): string` for advanced custom expressions.

---

## JSON expressions

MySQL-oriented JSON expressions are useful when your application needs a **structured value** rather than many flat columns from a JOIN.

### JSON objects (including optional LEFT JOIN relations)

```php
use WilyCode\Database\Query\Expression\JsonObject;

$query = Query::select('posts', 'p')
    ->column('p.id', 'id')
    ->leftJoin('categories', 'c', 'c.id = p.category_id')
    ->columnExp('category', JsonObject::of([
        'id' => 'c.id',
        'name' => 'c.name',
    ], 'c.id'));
```

**Compiled SQL**

```sql
SELECT p.id AS `id`,
       IF(c.id IS NULL, NULL, JSON_OBJECT('id', c.id, 'name', c.name))
       AS `category`
FROM posts AS p
LEFT JOIN categories AS c ON c.id = p.category_id
```

The second argument to `JsonObject::of()` is an optional **null guard**. It prevents a missing LEFT JOIN row from becoming a JSON object full of `null` fields.

> [!IMPORTANT]
> Strings used as expression values (such as `'c.name'`) are treated as **SQL expressions/columns**, not text literals. Use `ValueExpression::of('literal text')` for a bound string value.

### JSON arrays

`JsonArray::of([...])` builds a JSON array expression. For example, you can include it as a field inside `JsonObject::of()` to produce structured JSON with nested values.

### Correlated JSON arrays

```php
use WilyCode\Database\Query\Expression\JsonArraySubquery;
use WilyCode\Database\Query\Expression\JsonObject;

$comments = Query::select('comments', 'c')
    ->whereRaw('c.post_id = p.id')
    ->where('c.approved', true);

$query = Query::select('posts', 'p')
    ->column('p.id', 'id')
    ->columnExp('comments', JsonArraySubquery::of(
        $comments,
        JsonObject::of(['id' => 'c.id', 'body' => 'c.body']),
    ));
```

**Compiled SQL**

```sql
SELECT p.id AS `id`,
       (SELECT COALESCE(
           JSON_ARRAYAGG(JSON_OBJECT('id', c.id, 'body', c.body)),
           JSON_ARRAY()
       ) FROM comments AS c
       WHERE c.post_id = p.id AND c.approved = :comments_param_1)
       AS `comments`
FROM posts AS p
```

`JsonArraySubquery::of()` supports options for empty-array coalescing, `DISTINCT`, `GROUP BY`, and `HAVING` inclusion. It also supports `->paramPrefix('comments')` to namespace **bound parameter names**; if no prefix is set, `columnExp('comments', ...)` automatically uses the result alias as its prefix. This is *not* a table/column prefix.

Check generated JSON SQL against your MySQL/MariaDB version. This package does **not** provide separate dialect compilers for PostgreSQL, SQLite, SQL Server, or Oracle.

---

## Driver integration

WilyCode Database Query creates a **driver-neutral intermediate query**, while separate packages handle connections and execution.

```text
Query builder
    ↓ compile()
CompiledQuery { sql, params, types }
    ↓ driver-specific BinderInterface::bind()
BoundQuery { sql, params, types }
    ↓ separate database driver
Database execution
```

`BinderInterface` and `BoundQuery` are included in this package. PDO and MySQLi adapters live in separate repositories.

| Package | Purpose |
| --- | --- |
| [wilycode/database](https://github.com/wilycode/database) | Database connection and transaction contracts. |
| [wilycode/database-pdo](https://github.com/wilycode/database-pdo) | PDO database implementation and query binder. |
| [wilycode/database-mysqli](https://github.com/wilycode/database-mysqli) | MySQLi implementation and query binder. |
| [wilycode/database-model](https://github.com/wilycode/database-model) | Models and relationship hydration. |

These libraries are **not required** to install or use the query builder itself. `compile()` does not run SQL, fetch records, or hydrate models.

## API reference

### Statement factories

| Factory | Returns | Description |
| --- | --- | --- |
| `Query::select($table = '', $alias = null)` | `Select` | Build a SELECT statement. |
| `Query::insert($table)` | `Insert` | Build an INSERT statement. |
| `Query::update($table)` | `Update` | Build an UPDATE statement. |
| `Query::delete($table)` | `Delete` | Build a DELETE statement. |
| `Query::union(Select ...$queries)` | `Union` | Combine SELECT statements. |
| `Query::raw($sql, $parameters = [])` | `RawQuery` | Compile a complete raw query. |
| `Query::rawExp($sql, $parameters = [])` | `RawExpression` | Create a SQL expression. |

### Common builder methods

| Builder | Methods |
| --- | --- |
| `Select` | `column`, `columns`, `columnRaw`, `columnExp`, `join`, `leftJoin`, `rightJoin`, `where`, `whereColumn`, `whereRaw`, `whereExp`, `whereGroup`, `groupBy`, `having`, `havingRaw`, `havingExp`, `havingGroup`, `orderBy`, `limit`, `offset`, `pagination`, `forPaginationCount`, `compile` |
| `Insert` | `values`, `valuesMany`, `compile` |
| `Update` | `set`, `where` and shared WHERE variants, `compile` |
| `Delete` | `where` and shared WHERE variants, `compile` |
| `Union` | `add`, `orderBy`, `limit`, `offset`, `pagination`, `compile` |
| Shared WHERE variants | `andWhere`, `orWhere`, `andWhereRaw`, `orWhereRaw`, `andWhereGroup`, `orWhereGroup` |
| Shared HAVING variants | `andHaving`, `orHaving`, `andHavingRaw`, `orHavingRaw`, `andHavingGroup`, `orHavingGroup` |

Supported enum families: `ComparisonOperator`, `LogicalOperator`, `OrderDirection`, `JoinType`, and `ParameterType`.

## Security and limitations

- **Trusted identifiers only.** Parameter binding protects values, **not** SQL identifiers or arbitrary fragments.
- **SQL dialect:** identifiers, JSON functions, and some compiled fragments use MySQL-oriented conventions. Test SQL on your actual database engine.
- **No execution:** there is no connection, fetch, logging, or ORM layer in this package.
- **No automatic table prefix:** supply physical table names directly (e.g. `app_posts`).
- **No automatic JOIN result prefix:** select explicit aliases such as `['categoryName' => 'name']`.
- **No implicit nested relation hydration:** joined columns produce flat rows; JSON expressions can explicitly produce structured data for a separate model layer.
- **No CTE, `UNION ALL`, `EXCEPT`, `INTERSECT`, or dedicated `FULL JOIN` API** in this version.
- **Potentially destructive queries:** `UPDATE` and `DELETE` permit statements without `WHERE`.
- **Invalid input:** unsupported operators, invalid pagination, malformed bindings, and certain invalid statement shapes throw exceptions (typically `InvalidArgumentException`).

## Development

```bash
composer install
composer validate --strict
composer test
```

The PHPUnit suite runs on PHP 8.4 in [GitHub Actions](https://github.com/wilycode/database-query/actions). Tests are tracked in Git but excluded from Composer distribution archives.

## Contributing

Issues and pull requests are welcome. See [CONTRIBUTING.md](CONTRIBUTING.md) or [open an issue](https://github.com/wilycode/database-query/issues).

## License

MIT © 2026 Simon Piter. See [LICENSE](LICENSE).
