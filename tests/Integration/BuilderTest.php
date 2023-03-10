<?php
/**
 * @author Aaron Francis <aarondfrancis@gmail.com|https://twitter.com/aarondfrancis>
 */

namespace Danvaly\PrimeDatasource\Tests\Integration;

use Danvaly\PrimeDatasource\Datasource;
use Danvaly\PrimeDatasource\Tests\Support\NotificationStringKey;
use Danvaly\PrimeDatasource\Tests\Support\User;
use Danvaly\PrimeDatasource\Tests\Support\UserCustomPage;
use Danvaly\PrimeDatasource\Tests\Support\UserMutatedId;

class BuilderTest extends Base
{
    private const TOTAL_USERS = 29;

    private const TOTAL_POSTS_FIRST_USER = 1;

    /** @test */
    public function basic_test()
    {
        $queries = $this->withQueriesLogged(function () use (&$results) {
            $results = User::query()->toDatasource();
        });


        $this->assertInstanceOf(Datasource::class, $results);
        /** @var \Danvaly\PrimeDatasource\Datasource $results */
        $this->assertEquals(15, $results->count());
        $this->assertEquals('Person 15', $results->last()->name);
        $this->assertCount(3, $queries);

        $this->assertEquals(
            'select * from `users` where `users`.`id` in (1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15) limit 16 offset 0',
            $queries[2]['query']
        );

        $this->assertTrue($results->hasMorePages());
        $this->assertEquals(1, $results->currentPage());
        $this->assertEquals(self::TOTAL_USERS, $results->total());
    }

    /** @test */
    public function different_page_size()
    {
        $queries = $this->withQueriesLogged(function () use (&$results) {
            $results = User::query()->toDatasource(5);
        });

        /** @var \Illuminate\Pagination\LengthAwarePaginator $results */
        $this->assertEquals(5, $results->count());

        $this->assertEquals(
            'select * from `users` where `users`.`id` in (1, 2, 3, 4, 5) limit 6 offset 0',
            $queries[2]['query']
        );

        $this->assertTrue($results->hasMorePages());
        $this->assertEquals(1, $results->currentPage());
        $this->assertEquals(self::TOTAL_USERS, $results->total());
    }

    /** @test */
    public function page_2()
    {
        $queries = $this->withQueriesLogged(function () use (&$results) {
            $results = User::query()->toDatasource(5, ['*'], 'page', 2);
        });

        /** @var \Illuminate\Pagination\LengthAwarePaginator $results */
        $this->assertEquals(5, $results->count());

        $this->assertEquals(
            'select * from `users` where `users`.`id` in (6, 7, 8, 9, 10) limit 6 offset 0',
            $queries[2]['query']
        );

        $this->assertTrue($results->hasMorePages());
        $this->assertEquals(2, $results->currentPage());
        $this->assertEquals(self::TOTAL_USERS, $results->total());
    }

    /** @test */
    public function pk_attribute_mutations_are_skipped()
    {
        $queries = $this->withQueriesLogged(function () use (&$results) {
            $results = UserMutatedId::query()->toDatasource(5);
        });

        /** @var Datasource $results */
        $this->assertEquals(5, $results->count());

        $this->assertEquals(
            'select * from `users` where `users`.`id` in (1, 2, 3, 4, 5) limit 6 offset 0',
            $queries[2]['query']
        );
    }

    /** @test */
    public function custom_page_is_preserved()
    {
        $queries = $this->withQueriesLogged(function () use (&$results) {
            $results = UserCustomPage::query()->toDatasource();
        });

        /** @var \Illuminate\Pagination\LengthAwarePaginator $results */
        $this->assertEquals(2, $results->count());

        $this->assertEquals(
            'select * from `users` where `users`.`id` in (1, 2) limit 3 offset 0',
            $queries[2]['query']
        );

        $this->assertTrue($results->hasMorePages());
        $this->assertEquals(1, $results->currentPage());
        $this->assertEquals(self::TOTAL_USERS, $results->total());
    }

    /** @test */
    public function order_is_propagated()
    {
        $queries = $this->withQueriesLogged(function () use (&$results) {
            $results = User::query()->orderBy('name')->toDatasource(5);
        });

        $this->assertEquals(
            'select * from `users` where `users`.`id` in (1, 10, 11, 12, 13) order by `name` asc limit 6 offset 0',
            $queries[2]['query']
        );
    }

    /** @test */
    public function eager_loads_are_cleared_on_inner_query()
    {
        $queries = $this->withQueriesLogged(function () use (&$results) {
            $results = User::query()->with('posts')->toDatasource(5);
        });

        // If we didn't clear the eager loads, there would be 5 queries.
        $this->assertCount(4, $queries);

        // The eager load should come last, after the outer query has run.
        $this->assertEquals(
            'select * from `posts` where `posts`.`user_id` in (1, 2, 3, 4, 5)',
            $queries[3]['query']
        );
    }

    /** @test */
    public function eager_loads_are_loaded_on_outer_query()
    {
        $this->withQueriesLogged(function () use (&$results) {
            $results = User::query()->with('posts')->toDatasource();
        });

        $this->assertTrue($results->first()->relationLoaded('posts'));
        $this->assertEquals(1, $results->first()->posts->count());
    }

    /** @test */
    public function selects_are_overwritten()
    {
        $queries = $this->withQueriesLogged(function () use (&$results) {
            $results = User::query()->selectRaw('(select 1 as complicated_subquery)')->toDatasource();
        });

        // Dropped for our inner query
        $this->assertEquals(
            'select `users`.`id` from `users` limit 15 offset 0',
            $queries[1]['query']
        );

        // Restored for the user's query
        $this->assertEquals(
            'select (select 1 as complicated_subquery) from `users` where `users`.`id` in (1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15) limit 16 offset 0',
            $queries[2]['query']
        );
    }

    /** @test */
    public function havings_defer()
    {
        $queries = $this->withQueriesLogged(function () use (&$results) {
            User::query()
                ->selectRaw('*, concat(name, id) as name_id')
                ->having('name_id', '!=', '')
                ->toDatasource();
        });

        $this->assertCount(2, $queries);
        $this->assertEquals(
            'select *, concat(name, id) as name_id from `users` having `name_id` != ? limit 15 offset 0',
            $queries[1]['query']
        );
    }

    /** @test */
    public function standard_with_count_works()
    {
        $queries = $this->withQueriesLogged(function () use (&$results) {
            $results = User::query()->withCount('posts')->orderByDesc('posts_count')->toDatasource();
        });

        $this->assertCount(3, $queries);
        $this->assertEquals(
            'select `users`.`id`, (select count(*) from `posts` where `users`.`id` = `posts`.`user_id`) as `posts_count` from `users` order by `posts_count` desc limit 15 offset 0',
            $queries[1]['query']
        );

        /** @var \Illuminate\Pagination\LengthAwarePaginator $results */
        $this->assertTrue($results->hasMorePages());
        $this->assertEquals(1, $results->currentPage());
        $this->assertEquals(self::TOTAL_USERS, $results->total());
    }

    /** @test */
    public function aliased_with_count()
    {
        $queries = $this->withQueriesLogged(function () use (&$results) {
            User::query()->withCount('posts as posts_ct')->orderByDesc('posts_ct')->toDatasource();
        });

        $this->assertCount(3, $queries);
        $this->assertEquals(
            'select `users`.`id`, (select count(*) from `posts` where `users`.`id` = `posts`.`user_id`) as `posts_ct` from `users` order by `posts_ct` desc limit 15 offset 0',
            $queries[1]['query']
        );
    }

    /** @test */
    public function unordered_with_count_is_ignored()
    {
        $queries = $this->withQueriesLogged(function () use (&$results) {
            User::query()->withCount('posts')->orderByDesc('id')->toDatasource();
        });

        $this->assertCount(3, $queries);
        $this->assertEquals(
            'select `users`.`id` from `users` order by `id` desc limit 15 offset 0',
            $queries[1]['query']
        );
    }

    /** @test */
    public function uuids_are_bound_correctly()
    {
        $this->seedStringNotifications();

        $queries = $this->withQueriesLogged(function () use (&$results) {
            NotificationStringKey::query()->toDatasource();
        });

        $this->assertCount(3, $queries);
        $this->assertEquals(
            'select * from `notifications` where `notifications`.`id` in (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) limit 16 offset 0',
            $queries[2]['query']
        );

        $this->assertCount(15, $queries[2]['bindings']);
        $this->assertEquals('64bf6df6-06d7-11ed-b939-0001', $queries[2]['bindings'][0]);
    }

    /** @test */
    public function groups_are_skipped()
    {
        $queries = $this->withQueriesLogged(function () use (&$results) {
            User::query()->select(['name'])->groupBy('name')->toDatasource();
        });

        $this->assertCount(2, $queries);
        $this->assertEquals(
            'select `name` from `users` group by `name` limit 15 offset 0',
            $queries[1]['query']
        );
    }
}
