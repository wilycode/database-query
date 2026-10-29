<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use WilyCode\Database\Query\Dialect\SqliteDialect;
use WilyCode\Database\Query\Expression\{JsonArraySubquery, JsonObject, ValueExpression};
use WilyCode\Database\Query\Query;
use WilyCode\Database\Query\Statement\QueryInterface;
use WilyCode\Database\Query\Enum\ParameterType;

final class SqliteDialectIntegrationTest extends TestCase
{
    private PDO $pdo;
    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) { self::markTestSkipped('pdo_sqlite required.'); }
        $this->pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, active INTEGER)');
        $this->runQuery(Query::insert('users')->valuesMany([
            ['id' => 1, 'name' => 'Ada', 'active' => true],
            ['id' => 2, 'name' => 'Bob', 'active' => false],
        ]));
    }
    private function runQuery(QueryInterface $query): \PDOStatement
    {
        $q = $query->compile(new SqliteDialect());
        $stmt = $this->pdo->prepare($q->sql);
        foreach ($q->params as $key => $value) {
            $type = match ($q->types[$key] ?? null) {
                ParameterType::INTEGER => PDO::PARAM_INT,
                ParameterType::BOOLEAN => PDO::PARAM_BOOL,
                ParameterType::NULL => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $stmt->bindValue($key, $value, $type);
        }
        $stmt->execute();
        return $stmt;
    }
    public function testCrudOffsetCountAndUnionExecute(): void
    {
        $q = Query::select('users')->column('name')->orderBy('id')->offset(1);
        self::assertSame(['Bob'], $this->runQuery($q)->fetchAll(PDO::FETCH_COLUMN));
        self::assertSame(2, $this->runQuery($q->forPaginationCount('total', new SqliteDialect()))->fetchColumn());
        $union = Query::union(Query::select('users')->column('id')->where('id', 1)->limit(1), Query::select('users')->column('id')->where('id', 2))->orderBy('id');
        self::assertSame([1, 2], $this->runQuery($union)->fetchAll(PDO::FETCH_COLUMN));
        self::assertSame(1, $this->runQuery(Query::update('users')->set(['name' => 'Alice'])->where('id', 1))->rowCount());
        self::assertSame(1, $this->runQuery(Query::delete('users')->where('id', 2))->rowCount());
        self::assertSame('Alice', $this->runQuery(Query::select('users')->column('name'))->fetchColumn());
    }
    public function testNestedJsonStaysJsonAndPreservesScalarTypes(): void
    {
        $items = JsonArraySubquery::of(Query::select('users')->where('id', 1), JsonObject::of(['name' => 'name']));
        $q = Query::select()->columnExp('data', JsonObject::of([
            'items' => $items, 'flag' => true, 'decimal' => 1.5, 'text' => ValueExpression::of("a'\\b"),
        ]));
        $actual = json_decode($this->runQuery($q)->fetchColumn(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['items' => [['name' => 'Ada']], 'flag' => true, 'decimal' => 1.5, 'text' => "a'\\b"], $actual);
    }
    public function testEmptyJsonAggregationAndDistinct(): void
    {
        foreach ([true, false] as $coalesce) {
            $q = Query::select()->columnExp('data', JsonArraySubquery::of(Query::select('users')->where('id', 99), 'name', coalesceEmptyArray: $coalesce));
            self::assertSame($coalesce ? '[]' : null, $this->runQuery($q)->fetchColumn());
        }
        $q = Query::select()->columnExp('data', JsonArraySubquery::of(Query::select('users'), ValueExpression::of('same'), distinct: true));
        self::assertSame('["same"]', $this->runQuery($q)->fetchColumn());
    }
}
