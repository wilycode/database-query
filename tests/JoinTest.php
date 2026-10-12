<?php
declare(strict_types=1);

namespace WilyCode\Database\Query\Tests;

use PHPUnit\Framework\TestCase;
use WilyCode\Database\Query\Enum\ParameterType;
use WilyCode\Database\Query\Query;
use WilyCode\Database\Query\Expression\RawExpression;

final class JoinTest extends TestCase
{
    public function test_join_can_have_parameterized_on_conditions_and_they_are_mapped_to_main_bag(): void
    {
        $q = Query::select('posts', 'p')->column('id', 'id');
        $q->leftJoin('users', 'u', 'u.id = p.user_id');
        $q->where('u.status', 'active');

        $compiled = $q->compile();

        $this->assertSame(
            'SELECT p.id AS `id` FROM posts AS p LEFT JOIN users AS u ON u.id = p.user_id WHERE u.status = :param_1',
            $compiled->sql
        );

        $this->assertSame([
            ':param_1' => 'active',
        ], $compiled->params);

        $this->assertSame([
            ':param_1' => ParameterType::STRING,
        ], $compiled->types);
    }
    public function test_all_join_types_accept_columns_and_keep_the_main_table_context(): void
    {
        foreach (['join' => 'INNER', 'leftJoin' => 'LEFT', 'rightJoin' => 'RIGHT'] as $method => $sqlType) {
            $query = Query::select('posts', 'p')->columns(['postId' => 'id'])
                ->$method('users', 'u', 'u.id = p.user_id', columns: ['userId' => 'id', 'name'])
                ->columns(['title']);

            self::assertSame(
                'SELECT p.id AS `postId`, u.id AS `userId`, u.name, p.title FROM posts AS p '
                    . $sqlType . ' JOIN users AS u ON u.id = p.user_id',
                $query->compile()->sql,
            );
            self::assertSame([], $query->compile()->params);
        }
    }

    public function test_join_column_aliases_are_scoped_per_join_and_quoted(): void
    {
        $query = Query::select('posts')->columns(['id'])
            ->join('users', 'author', 'author.id = posts.user_id', columns: ['authorName' => 'name'])
            ->leftJoin('users', 'editor', 'editor.id = posts.editor_id', columns: [
                'editorName' => 'name', 'sourceId' => 'posts.id', 'odd`alias' => 'editor.name',
            ]);
        self::assertSame(
            'SELECT posts.id, author.name AS `authorName`, editor.name AS `editorName`, '
                . 'posts.id AS `sourceId`, editor.name AS `odd``alias` FROM posts '
                . 'INNER JOIN users AS author ON author.id = posts.user_id '
                . 'LEFT JOIN users AS editor ON editor.id = posts.editor_id',
            $query->compile()->sql,
        );
    }

    public function test_wildcards_and_omitted_join_columns_have_explicit_select_semantics(): void
    {
        $query = Query::select('posts', 'p')->columns(['id'])
            ->join('users', 'u', 'u.id = p.user_id', columns: ['*']);
        self::assertStringStartsWith('SELECT p.id, u.* FROM', $query->compile()->sql);
        $onlyJoined = Query::select('posts', 'p')->join('users', 'u', 'u.id = p.user_id', columns: ['name']);
        self::assertStringStartsWith('SELECT u.name FROM', $onlyJoined->compile()->sql);
        $implicit = Query::select('posts', 'p')->join('users', 'u', 'u.id = p.user_id');
        self::assertStringStartsWith('SELECT * FROM', $implicit->compile()->sql);
        $explicit = Query::select('posts', 'p')->columns(['id'])->join('users', 'u', 'u.id = p.user_id');
        self::assertStringStartsWith('SELECT p.id FROM', $explicit->compile()->sql);
    }

    public function test_join_columns_and_on_parameters_compile_deterministically(): void
    {
        $query = Query::select('posts', 'p')->columns(['id'])
            ->leftJoin('users', 'u', 'u.id = p.user_id AND u.locale = ? AND u.role IN (?)',
                parameters: ['pl', ['editor', 'admin']],
                columns: ['label' => RawExpression::raw('COALESCE(u.name, ?)', ['Anonymous'])])
            ->where('p.status', 'published')->orderBy('p.id')->limit(10);
        $first = $query->compile();
        self::assertSame(['Anonymous', 'pl', 'editor', 'admin', 'published'], array_values($first->params));
        self::assertSame(array_fill(0, 5, ParameterType::STRING), array_values($first->types));
        self::assertStringContainsString('COALESCE(u.name, ', $first->sql);
        self::assertStringContainsString(' AS `label`', $first->sql);

        $count = $query->forPaginationCount();
        self::assertSame($first->params, $count->params);
        self::assertStringNotContainsString('LIMIT 10', $count->sql);
        self::assertSame($first->sql, $query->compile()->sql);
        self::assertSame($first->params, $query->compile()->params);
        self::assertSame($first->types, $query->compile()->types);
    }

    public function test_join_columns_generate_the_same_query_as_explicitly_qualified_columns(): void
    {
        $explicit = Query::select('entry_value', 'ev')->columns(['entry_id', 'mf.id', 'path' => 'mf.storage_path'])
            ->join('media_file', 'mf', 'mf.id = ev.value_int AND mf.mime_type = ?', ['image/jpeg']);
        $grouped = Query::select('entry_value', 'ev')->columns(['entry_id'])
            ->join('media_file', 'mf', 'mf.id = ev.value_int AND mf.mime_type = ?',
                parameters: ['image/jpeg'], columns: ['id', 'path' => 'storage_path']);
        self::assertEquals($explicit->compile(), $grouped->compile());
    }
}
