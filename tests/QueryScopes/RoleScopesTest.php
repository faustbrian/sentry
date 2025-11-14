<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\QueryScopes;

use Cline\Warden\Database\Role;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Models\User;
use Tests\TestCase;

/**
 * @internal
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class RoleScopesTest extends TestCase
{
    #[Test()]
    public function roles_can_be_constrained_by_a_user(): void
    {
        $warden = $this->bouncer($user = User::query()->create());

        Role::query()->create(['name' => 'admin']);
        Role::query()->create(['name' => 'editor']);
        Role::query()->create(['name' => 'manager']);
        Role::query()->create(['name' => 'subscriber']);

        $warden->assign('admin')->to($user);
        $warden->assign('manager')->to($user);

        $roles = Role::whereAssignedTo($user)->get();

        $this->assertCount(2, $roles);
        $this->assertTrue($roles->contains('name', 'admin'));
        $this->assertTrue($roles->contains('name', 'manager'));
        $this->assertFalse($roles->contains('name', 'editor'));
        $this->assertFalse($roles->contains('name', 'subscriber'));
    }

    #[Test()]
    public function roles_can_be_constrained_by_a_collection_of_users(): void
    {
        $user1 = User::query()->create();
        $user2 = User::query()->create();

        $warden = $this->bouncer($user1);

        Role::query()->create(['name' => 'admin']);
        Role::query()->create(['name' => 'editor']);
        Role::query()->create(['name' => 'manager']);
        Role::query()->create(['name' => 'subscriber']);

        $warden->assign('editor')->to($user1);
        $warden->assign('manager')->to($user1);
        $warden->assign('subscriber')->to($user2);

        $roles = Role::whereAssignedTo(User::all())->get();

        $this->assertCount(3, $roles);
        $this->assertTrue($roles->contains('name', 'manager'));
        $this->assertTrue($roles->contains('name', 'editor'));
        $this->assertTrue($roles->contains('name', 'subscriber'));
        $this->assertFalse($roles->contains('name', 'admin'));
    }

    #[Test()]
    public function roles_can_be_constrained_by_a_model_name_and_keys(): void
    {
        $user1 = User::query()->create();
        $user2 = User::query()->create();

        $warden = $this->bouncer($user1);

        Role::query()->create(['name' => 'admin']);
        Role::query()->create(['name' => 'editor']);
        Role::query()->create(['name' => 'manager']);
        Role::query()->create(['name' => 'subscriber']);

        $warden->assign('editor')->to($user1);
        $warden->assign('manager')->to($user1);
        $warden->assign('subscriber')->to($user2);

        $roles = Role::whereAssignedTo(User::class, User::all()->modelKeys())->get();

        $this->assertCount(3, $roles);
        $this->assertTrue($roles->contains('name', 'manager'));
        $this->assertTrue($roles->contains('name', 'editor'));
        $this->assertTrue($roles->contains('name', 'subscriber'));
        $this->assertFalse($roles->contains('name', 'admin'));
    }
}
