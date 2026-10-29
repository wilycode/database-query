<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use WilyCode\Database\Query\Dialect\{DialectInterface, MySqlDialect, PostgreSqlDialect};
use WilyCode\Database\Query\Enum\ParameterType;
use WilyCode\Database\Query\Expression\{JsonArraySubquery, JsonObject, ValueExpression};
use WilyCode\Database\Query\Query;
use WilyCode\Database\Query\Statement\QueryInterface;

/** CI supplies a disposable database; these tests never use application credentials. */
final class ServerDialectIntegrationTest extends TestCase
{
    public function testMysqlWithNativePrepares(): void
    {
        $this->checkServer('WILY_TEST_MYSQL_DSN', new MySqlDialect());
    }
    public function testPostgresWithNativePrepares(): void
    {
        $this->checkServer('WILY_TEST_PGSQL_DSN', new PostgreSqlDialect());
    }
    private function checkServer(string $variable, DialectInterface $dialect): void
    {
        $dsn = getenv($variable);
        if (!$dsn) { self::markTestSkipped($variable . ' not configured.'); }
        $pdo = new PDO($dsn, 'wily_test', 'wily_test', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
        $pdo->exec('CREATE TABLE dialect_users (id INTEGER PRIMARY KEY, name VARCHAR(80), active BOOLEAN)');
        try {
            $run = static function (QueryInterface $query) use ($pdo, $dialect): \PDOStatement {
                $q = $query->compile($dialect);
                $stmt = $pdo->prepare($q->sql);
                foreach ($q->params as $name => $value) {
                    $type = match ($q->types[$name] ?? null) {
                        ParameterType::INTEGER => PDO::PARAM_INT,
                        ParameterType::BOOLEAN => PDO::PARAM_BOOL,
                        ParameterType::NULL => PDO::PARAM_NULL,
                        default => PDO::PARAM_STR,
                    };
                    $stmt->bindValue($name, $value, $type);
                }
                $stmt->execute();
                return $stmt;
            };
            $run(Query::insert('dialect_users')->valuesMany([
                ['id' => 1, 'name' => 'Ada', 'active' => true],
                ['id' => 2, 'name' => 'Bob', 'active' => false],
            ]));
            $q = Query::select('dialect_users')->column('name', 'name')->orderBy('id')->offset(1);
            self::assertSame('Bob', $run($q)->fetchColumn());
            self::assertSame(2, (int) $run($q->forPaginationCount('total', $dialect))->fetchColumn());
            $union = Query::union(Query::select('dialect_users')->column('id')->where('id', 1)->limit(1), Query::select('dialect_users')->column('id')->where('id', 2))->orderBy('id');
            self::assertSame([1, 2], array_map('intval', $run($union)->fetchAll(PDO::FETCH_COLUMN)));
            $items = JsonArraySubquery::of(Query::select('dialect_users')->where('id', 1), JsonObject::of(['name' => 'name']));
            $json = Query::select()->columnExp('data', JsonObject::of([
                'items' => $items, 'flag' => true, 'number' => 1.5, "key'\\x" => ValueExpression::of("value'\\x"),
            ]));
            $decoded = json_decode($run($json)->fetchColumn(), true, flags: JSON_THROW_ON_ERROR);
            self::assertEquals(['items' => [['name' => 'Ada']], 'flag' => true, 'number' => 1.5, "key'\\x" => "value'\\x"], $decoded);
            self::assertIsBool($decoded['flag']);
            self::assertIsFloat($decoded['number']);
            self::assertSame(1, $run(Query::update('dialect_users')->set(['name' => 'Alice'])->where('id', 1))->rowCount());
            self::assertSame(1, $run(Query::delete('dialect_users')->where('id', 2))->rowCount());
        } finally {
            $pdo->exec('DROP TABLE dialect_users');
        }
    }
}
