# Dialect architecture and rollout

## Responsibilities

- `QueryInterface::compile(?DialectInterface)` chooses SQL syntax; standalone calls
  resolve the default exactly once and pass it through compilation bags.
- `ParameterBag` carries the dialect to expressions without changing their public
  interface. No connection state or native PDO types enter the query package.
- `DialectInterface` owns result-identifier quoting, table aliases, complete query
  pagination, INSERT row syntax, UNION operands, JSON construction, aggregation,
  null guards and JSON parameter/result conversions.
- `PlatformProviderInterface` in `wilycode/database` is an optional driver capability.
  PDO reports `PDO::ATTR_DRIVER_NAME`; MySQLi reports `mysql`.
- `QueryExecutor` resolves explicit dialect > driver platform. Unknown and not-yet-
  implemented platforms throw. No silent MySQL fallback, reflection into connections,
  or inference from package/class names. Old custom drivers can pass a dialect.
- Binders retain canonical placeholder ordering and driver-native type conversion.
  Drivers own execution, transactions and generated IDs.

## Why pagination receives the whole SELECT

A suffix-only interface cannot model TOP or query wrapping. `paginate()` receives
SQL, nullable limit/offset (zero is meaningful), and whether the outer query has an
ORDER BY. SQL Server can require ORDER BY for OFFSET/FETCH, while Oracle can choose
FETCH FIRST/OFFSET and table aliases without AS. Neither should invent a hidden
ordering. `insert()` can later handle Oracle versions without multi-row VALUES.
These are extension points, not implemented SQL Server or Oracle support.

## Deliberate boundaries

No new contract package or per-engine Composer package is needed. No automatic
server-version queries run in the builder. MySQL/MariaDB share their common SQL;
MariaDB-only DISTINCT JSON aggregation needs a future explicit MariaDB dialect.
No RETURNING, OUTPUT, sequence lookup, schema migration, JSON path API or date
function translation is added here. Oracle OCI8 may be a future separate driver.
`getLastInsertId()` remains unchanged; it is not a portable RETURNING abstraction.

Do not add SQL Server or Oracle to automatic resolution until the dialect and the
specific driver/binder combination have execution tests, including native prepares,
repeated parameters, booleans, LOBs, dates and generated IDs. Keep native connection
objects encapsulated and expose only platform identity through the provider.

## Release order

These changes span five repositories and must be released together in this order:

1. `database` 0.1.2: additive `PlatformProviderInterface`.
2. `database-query` 0.2.0: changed compile contract and dialects.
3. `database-pdo` / `database-mysqli` 0.1.2: provider implementation and compatibility
   with query 0.1 or 0.2, requiring database >=0.1.2.
4. `database-query-executor` 0.2.0: query >=0.2 and platform-aware execution.

Version numbers are intended releases, not published tags. Until dependencies are
released, use local Composer path repositories with explicit development versions.
Do not merge/release a dependent package while its minimum dependency is unavailable.

## Validation

Unit tests compare dialect SQL, aliases, nested propagation, parameter collisions,
manual/default selection, zero/offset-only pagination, UNION, and failure paths.
SQLite integration tests execute CRUD, counts, UNION and nested JSON with real PDO.
Executor tests cover all execution methods and ensure detection never overwrites an
explicit choice or falls back for an unknown platform. PDO tests check detection on
a real SQLite connection. MySQL and PostgreSQL execution must also pass in CI before
calling those combinations release-verified; generated-SQL assertions alone do not
prove native server compatibility.

## Reference documentation

- [PostgreSQL JSON functions](https://www.postgresql.org/docs/current/functions-json.html)
- [PostgreSQL aggregate functions](https://www.postgresql.org/docs/current/functions-aggregate.html)
- [SQLite JSON functions](https://www.sqlite.org/json1.html)
- [MySQL aggregate functions](https://dev.mysql.com/doc/refman/8.0/en/aggregate-functions.html)
