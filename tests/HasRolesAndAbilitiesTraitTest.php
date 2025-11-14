<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use Cline\Warden\Database\Models;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\TestsClipboards;
use Tests\Fixtures\Models\User;
use Tests\Fixtures\Models\UserWithSoftDeletes;

/**
 * @internal
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class HasRolesAndAbilitiesTraitTest extends TestCase
{
    use TestsClipboards;

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function get_abilities_gets_all_allowed_abilities($provider): void
    {
        [$warden, $user] = $provider();

        $warden->allow('admin')->to('edit-site');
        $warden->allow($user)->to('create-posts');
        $warden->assign('admin')->to($user);

        $warden->forbid($user)->to('create-sites');
        $warden->allow('editor')->to('edit-posts');

        $this->assertEquals(['create-posts', 'edit-site'], $user->getAbilities()->pluck('name')->sort()->values()->all());
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function get_forbidden_abilities_gets_all_forbidden_abilities($provider): void
    {
        [$warden, $user] = $provider();

        $warden->forbid('admin')->to('edit-site');
        $warden->forbid($user)->to('create-posts');
        $warden->assign('admin')->to($user);

        $warden->allow($user)->to('create-sites');
        $warden->forbid('editor')->to('edit-posts');

        $this->assertEquals(['create-posts', 'edit-site'], $user->getForbiddenAbilities()->pluck('name')->sort()->values()->all());
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function can_give_and_remove_abilities($provider): void
    {
        [$warden, $user] = $provider();

        $user->allow('edit-site');

        $this->assertTrue($warden->can('edit-site'));

        $user->disallow('edit-site');

        $this->assertTrue($warden->cannot('edit-site'));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function can_give_and_remove_model_abilities($provider): void
    {
        [$warden, $user] = $provider();

        $user->allow('delete', $user);

        $this->assertTrue($warden->cannot('delete'));
        $this->assertTrue($warden->cannot('delete', User::class));
        $this->assertTrue($warden->can('delete', $user));

        $user->disallow('delete', $user);

        $this->assertTrue($warden->cannot('delete', $user));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function can_give_and_remove_ability_for_everything($provider): void
    {
        [$warden, $user] = $provider();

        $user->allow()->everything();

        $this->assertTrue($warden->can('delete'));
        $this->assertTrue($warden->can('delete', '*'));
        $this->assertTrue($warden->can('*', '*'));

        $user->disallow()->everything();

        $this->assertTrue($warden->cannot('delete'));
        $this->assertTrue($warden->cannot('delete', '*'));
        $this->assertTrue($warden->cannot('*', '*'));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function can_forbid_and_unforbid_abilities($provider): void
    {
        [$warden, $user] = $provider();

        $user->allow('edit-site');
        $user->forbid('edit-site');

        $this->assertTrue($warden->cannot('edit-site'));

        $user->unforbid('edit-site');

        $this->assertTrue($warden->can('edit-site'));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function can_forbid_and_unforbid_model_abilities($provider): void
    {
        [$warden, $user] = $provider();

        $user->allow('delete', $user);
        $user->forbid('delete', $user);

        $this->assertTrue($warden->cannot('delete', $user));

        $user->unforbid('delete', $user);

        $this->assertTrue($warden->can('delete', $user));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function can_forbid_and_unforbid_everything($provider): void
    {
        [$warden, $user] = $provider();

        $user->allow('delete', $user);
        $user->forbid()->everything();

        $this->assertTrue($warden->cannot('delete', $user));

        $user->unforbid()->everything();

        $this->assertTrue($warden->can('delete', $user));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function can_assign_and_retract_roles($provider): void
    {
        [$warden, $user] = $provider();

        $warden->allow('admin')->to('edit-site');
        $user->assign('admin');

        $this->assertEquals(['admin'], $user->getRoles()->all());
        $this->assertTrue($warden->can('edit-site'));

        $user->retract('admin');

        $this->assertEquals([], $user->getRoles()->all());
        $this->assertTrue($warden->cannot('edit-site'));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function can_check_roles($provider): void
    {
        [$warden, $user] = $provider();

        $this->assertTrue($user->isNotAn('admin'));
        $this->assertFalse($user->isAn('admin'));

        $this->assertTrue($user->isNotA('admin'));
        $this->assertFalse($user->isA('admin'));

        $user->assign('admin');

        $this->assertTrue($user->isAn('admin'));
        $this->assertFalse($user->isAn('editor'));
        $this->assertFalse($user->isNotAn('admin'));
        $this->assertTrue($user->isNotAn('editor'));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function can_check_multiple_roles($provider): void
    {
        [$warden, $user] = $provider();

        $this->assertFalse($user->isAn('admin', 'editor'));

        $user->assign('moderator');
        $user->assign('editor');

        $this->assertTrue($user->isAn('admin', 'moderator'));
        $this->assertTrue($user->isAll('editor', 'moderator'));
        $this->assertFalse($user->isAll('moderator', 'admin'));
    }

    #[Test()]
    public function deleting_a_model_deletes_the_permissions_pivot_table_records(): void
    {
        $warden = $this->bouncer();

        $user1 = User::query()->create();
        $user2 = User::query()->create();

        $warden->allow($user1)->everything();
        $warden->allow($user2)->everything();

        $this->assertEquals(2, $this->db()->table('permissions')->count());

        $user1->delete();

        $this->assertEquals(1, $this->db()->table('permissions')->count());
    }

    #[Test()]
    public function soft_deleting_a_model_persists_the_permissions_pivot_table_records(): void
    {
        Models::setUsersModel(UserWithSoftDeletes::class);

        $warden = $this->bouncer();

        $user1 = UserWithSoftDeletes::query()->create();
        $user2 = UserWithSoftDeletes::query()->create();

        $warden->allow($user1)->everything();
        $warden->allow($user2)->everything();

        $this->assertEquals(2, $this->db()->table('permissions')->count());

        $user1->delete();

        $this->assertEquals(2, $this->db()->table('permissions')->count());
    }

    #[Test()]
    public function deleting_a_model_deletes_the_assigned_roles_pivot_table_records(): void
    {
        $warden = $this->bouncer();

        $user1 = User::query()->create();
        $user2 = User::query()->create();

        $warden->assign('admin')->to($user1);
        $warden->assign('admin')->to($user2);

        $this->assertEquals(2, $this->db()->table('assigned_roles')->count());

        $user1->delete();

        $this->assertEquals(1, $this->db()->table('assigned_roles')->count());
    }

    #[Test()]
    public function soft_deleting_a_model_persists_the_assigned_roles_pivot_table_records(): void
    {
        Models::setUsersModel(UserWithSoftDeletes::class);

        $warden = $this->bouncer();

        $user1 = UserWithSoftDeletes::query()->create();
        $user2 = UserWithSoftDeletes::query()->create();

        $warden->assign('admin')->to($user1);
        $warden->assign('admin')->to($user2);

        $this->assertEquals(2, $this->db()->table('assigned_roles')->count());

        $user1->delete();

        $this->assertEquals(2, $this->db()->table('assigned_roles')->count());
    }
}
