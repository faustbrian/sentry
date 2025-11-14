<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use Cline\Warden\Database\Ability;
use Cline\Warden\Database\Role;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\TestsClipboards;
use Tests\Fixtures\Models\Account;
use Tests\Fixtures\Models\User;

use function array_filter;
use function mb_strtolower;
use function str_contains;

/**
 * @internal
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class SyncTest extends TestCase
{
    use TestsClipboards;

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function syncing_roles($provider): void
    {
        [$warden, $user] = $provider();

        $admin = $this->role('admin');
        $editor = $this->role('editor');
        $reviewer = $this->role('reviewer');
        $subscriber = $this->role('subscriber');

        $user->assign([$admin, $editor]);

        $this->assertTrue($warden->is($user)->all($admin, $editor));

        $warden->sync($user)->roles([$editor->id, $reviewer->name, $subscriber]);

        $this->assertTrue($warden->is($user)->all($editor, $reviewer, $subscriber));
        $this->assertTrue($warden->is($user)->notAn($admin->name));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function syncing_abilities($provider): void
    {
        [$warden, $user] = $provider();

        $editSite = Ability::query()->create(['name' => 'edit-site']);
        $banUsers = Ability::query()->create(['name' => 'ban-users']);
        Ability::query()->create(['name' => 'access-dashboard']);

        $warden->allow($user)->to([$editSite, $banUsers]);

        $this->assertTrue($warden->can('edit-site'));
        $this->assertTrue($warden->can('ban-users'));
        $this->assertTrue($warden->cannot('access-dashboard'));

        $warden->sync($user)->abilities([$banUsers->id, 'access-dashboard']);

        $this->assertTrue($warden->cannot('edit-site'));
        $this->assertTrue($warden->can('ban-users'));
        $this->assertTrue($warden->can('access-dashboard'));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function syncing_abilities_with_a_map($provider): void
    {
        [$warden, $user] = $provider();

        $deleteUser = Ability::createForModel($user, 'delete');
        $createAccounts = Ability::createForModel(Account::class, 'create');

        $warden->allow($user)->to([$deleteUser, $createAccounts]);

        $this->assertTrue($warden->can('delete', $user));
        $this->assertTrue($warden->can('create', Account::class));

        $warden->sync($user)->abilities([
            'access-dashboard',
            'create' => Account::class,
            'view' => $user,
        ]);

        $this->assertTrue($warden->cannot('delete', $user));
        $this->assertTrue($warden->cannot('view', User::class));
        $this->assertTrue($warden->can('create', Account::class));
        $this->assertTrue($warden->can('view', $user));
        $this->assertTrue($warden->can('access-dashboard'));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function syncing_forbidden_abilities($provider): void
    {
        [$warden, $user] = $provider();

        $editSite = Ability::query()->create(['name' => 'edit-site']);
        $banUsers = Ability::query()->create(['name' => 'ban-users']);
        Ability::query()->create(['name' => 'access-dashboard']);

        $warden->allow($user)->everything();
        $warden->forbid($user)->to([$editSite, $banUsers->id]);

        $this->assertTrue($warden->cannot('edit-site'));
        $this->assertTrue($warden->cannot('ban-users'));
        $this->assertTrue($warden->can('access-dashboard'));

        $warden->sync($user)->forbiddenAbilities([$banUsers->id, 'access-dashboard']);

        $this->assertTrue($warden->can('edit-site'));
        $this->assertTrue($warden->cannot('ban-users'));
        $this->assertTrue($warden->cannot('access-dashboard'));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function syncing_a_roles_abilities($provider): void
    {
        [$warden, $user] = $provider();

        $editSite = Ability::query()->create(['name' => 'edit-site']);
        $banUsers = Ability::query()->create(['name' => 'ban-users']);
        Ability::query()->create(['name' => 'access-dashboard']);

        $warden->assign('admin')->to($user);
        $warden->allow('admin')->to([$editSite, $banUsers]);

        $this->assertTrue($warden->can('edit-site'));
        $this->assertTrue($warden->can('ban-users'));
        $this->assertTrue($warden->cannot('access-dashboard'));

        $warden->sync('admin')->abilities([$banUsers->id, 'access-dashboard']);

        $this->assertTrue($warden->cannot('edit-site'));
        $this->assertTrue($warden->can('ban-users'));
        $this->assertTrue($warden->can('access-dashboard'));
    }

    #[Test()]
    public function syncing_user_abilities_does_not_alter_role_abilities_with_same_id(): void
    {
        $user = User::query()->create(['id' => 1]);
        $warden = $this->bouncer($user);
        $role = $warden->role()->create(['id' => 1, 'name' => 'alcoholic']);

        $warden->allow($user)->to(['eat', 'drink']);
        $warden->allow($role)->to('drink');

        $warden->sync($user)->abilities(['eat']);

        $this->assertTrue($user->can('eat'));
        $this->assertTrue($user->cannot('drink'));
        $this->assertTrue($role->can('drink'));
    }

    #[Test()]
    public function syncing_abilities_does_not_affect_another_subject_type_with_same_id(): void
    {
        $user = User::query()->create(['id' => 1]);
        $account = Account::query()->create(['id' => 1]);

        $warden = $this->bouncer();

        $warden->allow($user)->to('relax');
        $warden->allow($account)->to('relax');

        $this->assertTrue($user->can('relax'));
        $this->assertTrue($account->can('relax'));

        $warden->sync($user)->abilities([]);

        $this->assertTrue($user->cannot('relax'));
        $this->assertTrue($account->can('relax'));
    }

    #[Test()]
    public function syncing_roles_does_not_affect_another_subject_type_with_same_id(): void
    {
        $user = User::query()->create(['id' => 1]);
        $account = Account::query()->create(['id' => 1]);

        $warden = $this->bouncer();

        $warden->assign('admin')->to($user);
        $warden->assign('admin')->to($account);

        $this->assertTrue($user->isAn('admin'));
        $this->assertTrue($account->isAn('admin'));

        $warden->sync($user)->roles([]);

        $this->assertTrue($user->isNotAn('admin'));
        $this->assertTrue($account->isAn('admin'));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function syncing_with_same_roles_does_not_execute_delete_queries($provider): void
    {
        [$warden, $user] = $provider();

        $admin = $this->role('admin');
        $editor = $this->role('editor');

        // Initial sync to assign roles
        $warden->sync($user)->roles([$admin, $editor]);
        $this->assertTrue($warden->is($user)->all($admin, $editor));

        // Sync again with same roles - should trigger empty detach array (line 121)
        // which returns early without executing any DELETE queries
        DB::enableQueryLog();
        $warden->sync($user)->roles([$admin, $editor]);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Verify no DELETE queries were executed (detach returned early)
        $deleteQueries = array_filter($queries, fn (array $q): bool => str_contains(mb_strtolower((string) $q['query']), 'delete'));
        $this->assertCount(0, $deleteQueries, 'Expected no DELETE queries when detach array is empty');

        // Verify roles remain assigned
        $this->assertTrue($warden->is($user)->all($admin, $editor));
    }

    /**
     * Create a new role with the given name.
     *
     * @return Role
     */
    private function role(string $name)
    {
        return Role::query()->create(['name' => $name]);
    }
}
