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
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\TestsClipboards;
use Tests\Fixtures\Models\Account;
use Tests\Fixtures\Models\User;

use function collect;

/**
 * @internal
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class BouncerSimpleTest extends TestCase
{
    use TestsClipboards;

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function can_give_and_remove_abilities($provider): void
    {
        [$warden, $user] = $provider();

        $editSite = Ability::query()->create(['name' => 'edit-site']);
        $banUsers = Ability::query()->create(['name' => 'ban-users']);
        $accessDashboard = Ability::query()->create(['name' => 'access-dashboard']);

        $warden->allow($user)->to('edit-site');
        $warden->allow($user)->to([$banUsers, $accessDashboard->id]);

        $this->assertTrue($warden->can('edit-site'));
        $this->assertTrue($warden->can('ban-users'));
        $this->assertTrue($warden->can('access-dashboard'));

        $warden->disallow($user)->to($editSite);
        $warden->disallow($user)->to('ban-users');
        $warden->disallow($user)->to($accessDashboard->id);

        $this->assertTrue($warden->cannot('edit-site'));
        $this->assertTrue($warden->cannot('ban-users'));
        $this->assertTrue($warden->cannot('access-dashboard'));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function can_give_and_remove_abilities_for_everyone($provider): void
    {
        [$warden, $user] = $provider();

        $editSite = Ability::query()->create(['name' => 'edit-site']);
        $banUsers = Ability::query()->create(['name' => 'ban-users']);
        $accessDashboard = Ability::query()->create(['name' => 'access-dashboard']);

        $warden->allowEveryone()->to('edit-site');
        $warden->allowEveryone()->to([$banUsers, $accessDashboard->id]);

        $this->assertTrue($warden->can('edit-site'));
        $this->assertTrue($warden->can('ban-users'));
        $this->assertTrue($warden->can('access-dashboard'));

        $warden->disallowEveryone()->to($editSite);
        $warden->disallowEveryone()->to('ban-users');
        $warden->disallowEveryone()->to($accessDashboard->id);

        $this->assertTrue($warden->cannot('edit-site'));
        $this->assertTrue($warden->cannot('ban-users'));
        $this->assertTrue($warden->cannot('access-dashboard'));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function can_give_and_remove_wildcard_abilities($provider): void
    {
        [$warden, $user] = $provider();

        $warden->allow($user)->to('*');

        $this->assertTrue($warden->can('edit-site'));
        $this->assertTrue($warden->can('ban-users'));
        $this->assertTrue($warden->can('*'));

        $warden->disallow($user)->to('*');

        $this->assertTrue($warden->cannot('edit-site'));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function can_ignore_duplicate_ability_allowances($provider): void
    {
        [$warden, $user1, $user2] = $provider(2);

        $warden->allow($user1)->to('ban-users');
        $warden->allow($user1)->to('ban-users');

        $warden->allow($user1)->to('ban', $user2);
        $warden->allow($user1)->to('ban', $user2);

        $this->assertCount(2, $user1->abilities);

        $admin = $warden->role(['name' => 'admin']);
        $admin->save();

        $warden->allow($admin)->to('ban-users');
        $warden->allow($admin)->to('ban-users');

        $warden->allow($admin)->to('ban', $user1);
        $warden->allow($admin)->to('ban', $user1);

        $this->assertCount(2, $admin->abilities);
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function can_give_and_remove_roles($provider): void
    {
        [$warden, $user] = $provider();

        $warden->allow('admin')->to('ban-users');
        $warden->assign('admin')->to($user);

        $editor = $warden->role()->create(['name' => 'editor']);
        $warden->allow($editor)->to('ban-users');
        $warden->assign($editor)->to($user);

        $this->assertTrue($warden->can('ban-users'));

        $warden->retract('admin')->from($user);
        $warden->retract($editor)->from($user);

        $this->assertTrue($warden->cannot('ban-users'));
    }

    #[Test()]
    public function deleting_a_role_deletes_the_pivot_table_records(): void
    {
        $warden = $this->bouncer();

        $admin = $warden->role()->create(['name' => 'admin']);
        $editor = $warden->role()->create(['name' => 'editor']);

        $warden->allow($admin)->everything();
        $warden->allow($editor)->to('edit', User::class);

        $this->assertEquals(2, $this->db()->table('permissions')->count());

        $admin->delete();

        $this->assertEquals(1, $this->db()->table('permissions')->count());
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function can_give_and_remove_multiple_roles_at_once($provider): void
    {
        [$warden, $user] = $provider();

        $admin = $this->role('admin');
        $editor = $this->role('editor');
        $reviewer = $this->role('reviewer');

        $warden->assign(collect([$admin, 'editor', $reviewer->id]))->to($user);

        $this->assertTrue($warden->is($user)->all($admin->id, $editor, 'reviewer'));

        $warden->retract(['admin', $editor])->from($user);

        $this->assertTrue($warden->is($user)->notAn($admin->name, 'editor'));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function can_give_and_remove_roles_for_multiple_users_at_once($provider): void
    {
        [$warden, $user1, $user2] = $provider(2);

        $warden->assign(['admin', 'editor'])->to([$user1, $user2]);

        $this->assertTrue($warden->is($user1)->all('admin', 'editor'));
        $this->assertTrue($warden->is($user2)->an('admin', 'editor'));

        $warden->retract('admin')->from($user1);
        $warden->retract(collect(['admin', 'editor']))->from($user2);

        $this->assertTrue($warden->is($user1)->notAn('admin'));
        $this->assertTrue($warden->is($user1)->an('editor'));
        $this->assertTrue($warden->is($user1)->an('admin', 'editor'));
    }

    #[Test()]
    public function can_ignore_duplicate_role_assignments(): void
    {
        $warden = $this->bouncer($user = User::query()->create());

        $warden->assign('admin')->to($user);
        $warden->assign('admin')->to($user);

        $this->assertCount(1, $user->roles);
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function can_disallow_abilities_on_roles($provider): void
    {
        [$warden, $user] = $provider();

        $warden->allow('admin')->to('edit-site');
        $warden->disallow('admin')->to('edit-site');
        $warden->assign('admin')->to($user);

        $this->assertTrue($warden->cannot('edit-site'));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function disallow_on_roles_does_not_disallow_for_users_with_matching_id($provider): void
    {
        [$warden, $user] = $provider();

        // Since the user is the first user created, its ID is 1.
        // Creating admin as the first role, it'll have its ID
        // set to 1. Let's test that they're kept separate.
        $warden->allow($user)->to('edit-site');
        $warden->allow('admin')->to('edit-site');
        $warden->disallow('admin')->to('edit-site');

        $this->assertTrue($warden->can('edit-site'));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function can_check_user_roles($provider): void
    {
        [$warden, $user] = $provider();

        $this->assertTrue($warden->is($user)->notA('moderator'));
        $this->assertTrue($warden->is($user)->notAn('editor'));
        $this->assertFalse($warden->is($user)->an('admin'));

        $warden = $this->bouncer($user = User::query()->create());

        $warden->assign('moderator')->to($user);
        $warden->assign('editor')->to($user);

        $this->assertTrue($warden->is($user)->a('moderator'));
        $this->assertTrue($warden->is($user)->an('editor'));
        $this->assertFalse($warden->is($user)->notAn('editor'));
        $this->assertFalse($warden->is($user)->an('admin'));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function can_check_multiple_user_roles($provider): void
    {
        [$warden, $user] = $provider();

        $this->assertTrue($warden->is($user)->notAn('editor', 'moderator'));
        $this->assertTrue($warden->is($user)->notAn('admin', 'moderator'));

        $warden = $this->bouncer($user = User::query()->create());
        $warden->assign('moderator')->to($user);
        $warden->assign('editor')->to($user);

        $this->assertTrue($warden->is($user)->a('subscriber', 'moderator'));
        $this->assertTrue($warden->is($user)->an('admin', 'editor'));
        $this->assertTrue($warden->is($user)->all('editor', 'moderator'));
        $this->assertFalse($warden->is($user)->notAn('editor', 'moderator'));
        $this->assertFalse($warden->is($user)->all('admin', 'moderator'));
    }

    #[Test()]
    public function can_get_an_empty_role_model(): void
    {
        $warden = $this->bouncer($user = User::query()->create());

        $this->assertInstanceOf(Role::class, $warden->role());
    }

    #[Test()]
    public function can_fill_a_role_model(): void
    {
        $warden = $this->bouncer($user = User::query()->create());
        $role = $warden->role(['name' => 'test-role']);

        $this->assertInstanceOf(Role::class, $role);
        $this->assertEquals('test-role', $role->name);
    }

    #[Test()]
    public function can_get_an_empty_ability_model(): void
    {
        $warden = $this->bouncer($user = User::query()->create());

        $this->assertInstanceOf(Ability::class, $warden->ability());
    }

    #[Test()]
    public function can_fill_an_ability_model(): void
    {
        $warden = $this->bouncer($user = User::query()->create());
        $ability = $warden->ability(['name' => 'test-ability']);

        $this->assertInstanceOf(Ability::class, $ability);
        $this->assertEquals('test-ability', $ability->name);
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function can_allow_abilities_from_a_defined_callback($provider): void
    {
        [$warden, $user] = $provider();

        $warden->define('edit', function ($user, $account): bool {
            if (!$account instanceof Account) {
                return null;
            }

            return $user->id === $account->user_id;
        });

        $this->assertTrue($warden->can('edit', new Account(['user_id' => $user->id])));
        $this->assertFalse($warden->can('edit', new Account(['user_id' => 99])));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function authorize_method_returns_response_with_correct_message($provider): void
    {
        [$warden, $user] = $provider();

        $warden->allow($user)->to('have-fun');
        $warden->allow($user)->to('enjoy-life');

        $this->assertEquals('Bouncer granted permission via ability #2', $warden->authorize('enjoy-life')->message());

        $this->assertEquals('Bouncer granted permission via ability #1', $warden->authorize('have-fun')->message());
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function authorize_method_throws_for_unauthorized_abilities($provider): void
    {
        [$warden] = $provider();

        // The exception class thrown from the "authorize" method
        // has changed between different versions of Laravel,
        // so we cannot check for a specific error class.
        $threw = false;

        try {
            $warden->authorize('be-miserable');
        } catch (Exception) {
            $threw = true;
        }

        $this->assertTrue($threw);
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
