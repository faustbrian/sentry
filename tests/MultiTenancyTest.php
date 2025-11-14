<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use Cline\Warden\Contracts\ScopeInterface;
use Cline\Warden\Database\Models;
use Cline\Warden\Database\Scope\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\TestsClipboards;
use Tests\Fixtures\Models\User;

/**
 * @internal
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MultiTenancyTest extends TestCase
{
    use TestsClipboards;

    /**
     * Reset any scopes that have been applied in a test.
     */
    #[Override()]
    protected function tearDown(): void
    {
        Models::scope(
            new Scope(),
        );

        parent::tearDown();
    }

    #[Test()]
    public function can_set_and_get_the_current_scope(): void
    {
        $warden = $this->bouncer();

        $this->assertNull($warden->scope()->get());

        $warden->scope()->to(1);
        $this->assertEquals(1, $warden->scope()->get());
    }

    #[Test()]
    public function can_remove_the_current_scope(): void
    {
        $warden = $this->bouncer();

        $warden->scope()->to(1);
        $this->assertEquals(1, $warden->scope()->get());

        $warden->scope()->remove();
        $this->assertNull($warden->scope()->get());
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function creating_roles_and_abilities_automatically_scopes_them($provider): void
    {
        [$warden, $user] = $provider();

        $warden->scope()->to(1);

        $warden->allow('admin')->to('create', User::class);
        $warden->assign('admin')->to($user);

        $this->assertEquals(1, $warden->ability()->query()->value('scope'));
        $this->assertEquals(1, $warden->role()->query()->value('scope'));
        $this->assertEquals(1, $this->db()->table('permissions')->value('scope'));
        $this->assertEquals(1, $this->db()->table('assigned_roles')->value('scope'));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function syncing_roles_is_properly_scoped($provider): void
    {
        [$warden, $user] = $provider();

        $warden->scope()->to(1);
        $warden->assign(['writer', 'reader'])->to($user);

        $warden->scope()->to(2);
        $warden->assign(['eraser', 'thinker'])->to($user);

        $warden->scope()->to(1);
        $warden->sync($user)->roles(['writer']);

        $this->assertTrue($warden->is($user)->a('writer'));
        $this->assertEquals(1, $user->roles()->count());

        $warden->scope()->to(2);
        $this->assertTrue($warden->is($user)->all('eraser', 'thinker'));
        $this->assertFalse($warden->is($user)->a('writer', 'reader'));

        $warden->sync($user)->roles(['thinker']);

        $this->assertTrue($warden->is($user)->a('thinker'));
        $this->assertEquals(1, $user->roles()->count());
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function syncing_abilities_is_properly_scoped($provider): void
    {
        [$warden, $user] = $provider();

        $warden->scope()->to(1);
        $warden->allow($user)->to(['write', 'read']);

        $warden->scope()->to(2);
        $warden->allow($user)->to(['erase', 'think']);

        $warden->scope()->to(1);
        $warden->sync($user)->abilities(['write', 'color']); // "read" is not deleted

        $this->assertTrue($warden->can('write'));
        $this->assertEquals(2, $user->abilities()->count());

        $warden->scope()->to(2);
        $this->assertTrue($warden->can('erase'));
        $this->assertTrue($warden->can('think'));
        $this->assertFalse($warden->can('write'));
        $this->assertFalse($warden->can('read'));

        $warden->sync($user)->abilities(['think']);

        $this->assertTrue($warden->can('think'));
        $this->assertEquals(1, $user->abilities()->count());
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function scoped_abilities_do_not_work_when_unscoped($provider): void
    {
        [$warden, $user] = $provider();

        $warden->scope()->to(1);
        $warden->allow($user)->to(['write', 'read']);

        $this->assertTrue($warden->can('write'));
        $this->assertTrue($warden->can('read'));
        $this->assertEquals(2, $user->abilities()->count());

        $warden->scope()->to(null);
        $this->assertFalse($warden->can('write'));
        $this->assertFalse($warden->can('read'));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function relation_queries_are_properly_scoped($provider): void
    {
        [$warden, $user] = $provider();

        $warden->scope()->to(1);
        $warden->allow($user)->to('create', User::class);

        $warden->scope()->to(2);
        $warden->allow($user)->to('delete', User::class);

        $warden->scope()->to(1);
        $abilities = $user->abilities()->get();

        $this->assertCount(1, $abilities);
        $this->assertEquals(1, $abilities->first()->scope);
        $this->assertEquals('create', $abilities->first()->name);
        $this->assertTrue($warden->can('create', User::class));
        $this->assertTrue($warden->cannot('delete', User::class));

        $warden->scope()->to(2);
        $abilities = $user->abilities()->get();

        $this->assertCount(1, $abilities);
        $this->assertEquals(2, $abilities->first()->scope);
        $this->assertEquals('delete', $abilities->first()->name);
        $this->assertTrue($warden->can('delete', User::class));
        $this->assertTrue($warden->cannot('create', User::class));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function relation_queries_can_be_scoped_exclusively($provider): void
    {
        [$warden, $user] = $provider();

        $warden->scope()->to(1)->onlyRelations();
        $warden->allow($user)->to('create', User::class);

        $warden->scope()->to(2);
        $warden->allow($user)->to('delete', User::class);

        $warden->scope()->to(1);
        $abilities = $user->abilities()->get();

        $this->assertCount(1, $abilities);
        $this->assertNull($abilities->first()->scope);
        $this->assertEquals('create', $abilities->first()->name);
        $this->assertTrue($warden->can('create', User::class));
        $this->assertTrue($warden->cannot('delete', User::class));

        $warden->scope()->to(2);
        $abilities = $user->abilities()->get();

        $this->assertCount(1, $abilities);
        $this->assertNull($abilities->first()->scope);
        $this->assertEquals('delete', $abilities->first()->name);
        $this->assertTrue($warden->can('delete', User::class));
        $this->assertTrue($warden->cannot('create', User::class));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function scoping_also_returns_global_abilities($provider): void
    {
        [$warden, $user] = $provider();

        $warden->allow($user)->to('create', User::class);

        $warden->scope()->to(1)->onlyRelations();
        $warden->allow($user)->to('delete', User::class);

        $abilities = $user->abilities()->orderBy('id')->get();

        $this->assertCount(2, $abilities);
        $this->assertNull($abilities->first()->scope);
        $this->assertEquals('create', $abilities->first()->name);
        $this->assertTrue($warden->can('create', User::class));
        $this->assertTrue($warden->can('delete', User::class));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function forbidding_abilities_only_affects_the_current_scope($provider): void
    {
        [$warden, $user] = $provider();

        $warden->scope()->to(1);
        $warden->allow($user)->to('create', User::class);

        $warden->scope()->to(2);
        $warden->allow($user)->to('create', User::class);
        $warden->forbid($user)->to('create', User::class);

        $warden->scope()->to(1);

        $this->assertTrue($warden->can('create', User::class));

        $warden->unforbid($user)->to('create', User::class);

        $warden->scope()->to(2);

        $this->assertTrue($warden->cannot('create', User::class));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function disallowing_abilities_only_affects_the_current_scope($provider): void
    {
        [$warden, $user] = $provider();

        $admin = $warden->role()->create(['name' => 'admin']);
        $user->assign($admin);

        $warden->scope()->to(1)->onlyRelations();
        $admin->allow('create', User::class);

        $warden->scope()->to(2);
        $admin->allow('create', User::class);
        $admin->disallow('create', User::class);

        $warden->scope()->to(1);

        $this->assertTrue($warden->can('create', User::class));

        $warden->scope()->to(2);

        $this->assertTrue($warden->cannot('create', User::class));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function unforbidding_abilities_only_affects_the_current_scope($provider): void
    {
        [$warden, $user] = $provider();

        $admin = $warden->role()->create(['name' => 'admin']);
        $user->assign($admin);

        $warden->scope()->to(1)->onlyRelations();
        $admin->allow()->everything();
        $admin->forbid()->to('create', User::class);

        $warden->scope()->to(2);
        $admin->allow()->everything();
        $admin->forbid()->to('create', User::class);
        $admin->unforbid()->to('create', User::class);

        $warden->scope()->to(1);

        $this->assertTrue($warden->cannot('create', User::class));

        $warden->scope()->to(2);

        $this->assertTrue($warden->can('create', User::class));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function assigning_and_retracting_roles_scopes_them_properly($provider): void
    {
        [$warden, $user] = $provider();

        $warden->scope()->to(1)->onlyRelations();
        $warden->assign('admin')->to($user);

        $warden->scope()->to(2);
        $warden->assign('admin')->to($user);
        $warden->retract('admin')->from($user);

        $warden->scope()->to(1);
        $this->assertTrue($warden->is($user)->an('admin'));

        $warden->scope()->to(2);
        $this->assertFalse($warden->is($user)->an('admin'));

        $warden->scope()->to(null);
        $this->assertFalse($warden->is($user)->an('admin'));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function role_abilities_can_be_excluded_from_scopes($provider): void
    {
        [$warden, $user] = $provider();

        $warden->scope()->to(1)
            ->onlyRelations()
            ->dontScopeRoleAbilities();

        $warden->allow('admin')->to('delete', User::class);

        $warden->scope()->to(2);

        $warden->assign('admin')->to($user);

        $this->assertTrue($warden->can('delete', User::class));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function can_set_custom_scope($provider): void
    {
        [$warden, $user] = $provider();

        $warden->scope(
            new MultiTenancyNullScopeStub(),
        )->to(1);

        $warden->allow($user)->to('delete', User::class);

        $warden->scope()->to(2);

        $this->assertTrue($warden->can('delete', User::class));
    }

    #[Test()]
    public function can_set_the_scope_temporarily(): void
    {
        $warden = $this->bouncer();

        $this->assertNull($warden->scope()->get());

        $result = $warden->scope()->onceTo(1, function () use ($warden): string {
            $this->assertEquals(1, $warden->scope()->get());

            return 'result';
        });

        $this->assertEquals('result', $result);
        $this->assertNull($warden->scope()->get());
    }

    #[Test()]
    public function can_remove_the_scope_temporarily(): void
    {
        $warden = $this->bouncer();

        $warden->scope()->to(1);

        $result = $warden->scope()->removeOnce(function () use ($warden): string {
            $this->assertEquals(null, $warden->scope()->get());

            return 'result';
        });

        $this->assertEquals('result', $result);
        $this->assertEquals(1, $warden->scope()->get());
    }
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class MultiTenancyNullScopeStub implements ScopeInterface
{
    public function to(): void
    {
        //
    }

    public function appendToCacheKey(string $key): string
    {
        return $key;
    }

    public function applyToModel(Model $model): Model
    {
        return $model;
    }

    public function applyToModelQuery(Builder|\Illuminate\Database\Query\Builder $query, ?string $table = null): Builder|\Illuminate\Database\Query\Builder
    {
        return $query;
    }

    public function applyToRelationQuery(Builder|\Illuminate\Database\Query\Builder $query, string $table): Builder|\Illuminate\Database\Query\Builder
    {
        return $query;
    }

    public function applyToRelation(BelongsToMany $relation): BelongsToMany
    {
        return $relation;
    }

    public function get(): null
    {
        return null;
    }

    public function getAttachAttributes(Model|string|null $authority = null): array
    {
        return [];
    }

    public function onceTo($scope, callable $callback): mixed
    {
        return null;
    }

    public function remove(): static
    {
        return $this;
    }

    public function removeOnce(callable $callback): mixed
    {
        return null;
    }
}
