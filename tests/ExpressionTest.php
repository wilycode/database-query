<?php
declare(strict_types=1);

namespace WilyCode\Database\Query\Tests;

use PHPUnit\Framework\TestCase;
use WilyCode\Database\Query\Enum\ParameterType;
use WilyCode\Database\Query\Expression\JsonArraySubquery;
use WilyCode\Database\Query\Expression\JsonObject;
use WilyCode\Database\Query\Query;

final class ExpressionTest extends TestCase
{
    public function test_json_array_subquery_is_compiled_and_its_parameters_are_collected_in_main_query(): void
    {
        $comments = Query::select('comments', 'c')
            ->whereRaw('c.post_id = p.id')
            ->andWhere('c.status', 'approved');

        $item = JsonObject::of([
            'id' => 'c.id',
            'body' => 'c.body',
        ]);

        $q = Query::select('posts', 'p')->columns([
            'id' => 'id',
            'comments' => JsonArraySubquery::of($comments, $item),
        ]);

        $compiled = $q->compile();

        $this->assertStringContainsString(
            "SELECT p.id AS `id`, (SELECT COALESCE(JSON_ARRAYAGG(JSON_OBJECT('id', c.id, 'body', c.body)), JSON_ARRAY()) FROM comments AS c WHERE c.post_id = p.id AND c.status = :comments_param_1) AS `comments` FROM posts AS p",
            $compiled->sql
        );

        $this->assertSame([
            ':comments_param_1' => 'approved',
        ], $compiled->params);

        $this->assertSame([
            ':comments_param_1' => ParameterType::STRING,
        ], $compiled->types);
    }


    public function test_single_comment_object_with_user_and_tags_is_compiled_and_parameters_are_collected(): void
    {
        $tags = Query::select('comment_tag', 'ct')
            ->join('tags', 't', 't.id = ct.tag_id')
            ->whereRaw('ct.comment_id = c.id')
            ->andWhere('t.is_active', 1);

        $tagItem = JsonObject::of([
            'id' => 't.id',
            'name' => 't.name',
        ]);

        // comment object: comment -> user -> tags[]
        $commentItem = JsonObject::of([
            'id' => 'c.id',
            'body' => 'c.body',
            'user' => JsonObject::of([
                'id' => 'u.id',
                'name' => 'u.name',
            ]),
            'tags' => JsonArraySubquery::of($tags, $tagItem)->paramPrefix('tags'),
        ]);

        // single comment subquery (correlated by p.id)
        $comment = Query::select('comments', 'c')
            ->leftJoin('users', 'u', 'u.id = c.user_id')
            ->whereRaw('c.post_id = p.id')
            ->andWhere('c.status', 'approved')
            ->columns([
                0 => $commentItem, // expression without alias -> returned as single scalar column
            ])
            ->limit(1);

        $q = Query::select('posts', 'p')->columns([
            'id' => 'id',
            'comment' => new \WilyCode\Database\Query\Expression\Subquery($comment),
        ]);

        $compiled = $q->compile();

        // Main select shape
        $this->assertStringContainsString('SELECT p.id AS `id`,', $compiled->sql);
        $this->assertStringContainsString(' AS `comment` FROM posts AS p', $compiled->sql);

        // Comment subquery basics
        $this->assertStringContainsString('FROM comments AS c', $compiled->sql);
        $this->assertStringContainsString('LEFT JOIN users AS u ON u.id = c.user_id', $compiled->sql);
        $this->assertStringContainsString('WHERE c.post_id = p.id AND c.status = :param_1', $compiled->sql);
        $this->assertStringContainsString('LIMIT 1', $compiled->sql);

        // JSON object nesting (comment -> user)
        $this->assertStringContainsString("JSON_OBJECT('id', c.id, 'body', c.body, 'user', JSON_OBJECT('id', u.id, 'name', u.name), 'tags',", $compiled->sql);

        // Tags array subquery inside JSON
        $this->assertStringContainsString("COALESCE(JSON_ARRAYAGG(JSON_OBJECT('id', t.id, 'name', t.name)), JSON_ARRAY())", $compiled->sql);
        $this->assertStringContainsString('FROM comment_tag AS ct', $compiled->sql);
        $this->assertStringContainsString('JOIN tags AS t', $compiled->sql);
        $this->assertStringContainsString('WHERE ct.comment_id = c.id AND t.is_active = :tags_param_1', $compiled->sql);

        $this->assertSame([
            ':tags_param_1' => 1,
            ':param_1' => 'approved',
        ], $compiled->params);

        $this->assertSame([
            ':tags_param_1' => ParameterType::INTEGER,
            ':param_1' => ParameterType::STRING,
        ], $compiled->types);
    }

}
