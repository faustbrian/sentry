<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use Cline\Warden\Database\Role;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Models\User;

/**
 * @internal
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class AutoTitlesTest extends TestCase
{
    #[Test()]
    public function role_title_is_never_overwritten(): void
    {
        $role = Role::query()->create(['name' => 'admin', 'title' => 'Something Else']);

        $this->assertEquals('Something Else', $role->title);
    }

    #[Test()]
    public function role_title_is_capitalized(): void
    {
        $role = Role::query()->create(['name' => 'admin']);

        $this->assertEquals('Admin', $role->title);
    }

    #[Test()]
    public function role_title_with_spaces(): void
    {
        $role = Role::query()->create(['name' => 'site admin']);

        $this->assertEquals('Site admin', $role->title);
    }

    #[Test()]
    public function role_title_with_dashes(): void
    {
        $role = Role::query()->create(['name' => 'site-admin']);

        $this->assertEquals('Site admin', $role->title);
    }

    #[Test()]
    public function role_title_with_underscores(): void
    {
        $role = Role::query()->create(['name' => 'site_admin']);

        $this->assertEquals('Site admin', $role->title);
    }

    #[Test()]
    public function role_title_with_camel_casing(): void
    {
        $role = Role::query()->create(['name' => 'siteAdmin']);

        $this->assertEquals('Site admin', $role->title);
    }

    #[Test()]
    public function role_title_with_studly_casing(): void
    {
        $role = Role::query()->create(['name' => 'SiteAdmin']);

        $this->assertEquals('Site admin', $role->title);
    }

    #[Test()]
    public function ability_title_is_never_overwritten(): void
    {
        $warden = $this->bouncer($user = User::query()->create());

        $warden->allow($user)->to('ban-users', null, [
            'title' => 'Something Else',
        ]);

        $this->assertEquals('Something Else', $warden->ability()->first()->title);
    }

    #[Test()]
    public function ability_title_is_set_for_wildcards(): void
    {
        $warden = $this->bouncer($user = User::query()->create());

        $warden->allow($user)->everything();

        $this->assertEquals('All abilities', $warden->ability()->first()->title);
    }

    #[Test()]
    public function ability_title_is_set_for_restricted_wildcards(): void
    {
        $warden = $this->bouncer($user = User::query()->create());

        $warden->allow($user)->to('*');

        $this->assertEquals('All simple abilities', $warden->ability()->first()->title);
    }

    #[Test()]
    public function ability_title_is_set_for_simple_abilities(): void
    {
        $warden = $this->bouncer($user = User::query()->create());

        $warden->allow($user)->to('ban-users');

        $this->assertEquals('Ban users', $warden->ability()->first()->title);
    }

    #[Test()]
    public function ability_title_is_set_for_blanket_ownership_ability(): void
    {
        $warden = $this->bouncer($user = User::query()->create());

        $warden->allow($user)->toOwnEverything();

        $this->assertEquals('Manage everything owned', $warden->ability()->first()->title);
    }

    #[Test()]
    public function ability_title_is_set_for_restricted_ownership_ability(): void
    {
        $warden = $this->bouncer($user = User::query()->create());

        $warden->allow($user)->toOwnEverything()->to('edit');

        $this->assertEquals('Edit everything owned', $warden->ability()->first()->title);
    }

    #[Test()]
    public function ability_title_is_set_for_management_ability(): void
    {
        $warden = $this->bouncer($user = User::query()->create());

        $warden->allow($user)->toManage(User::class);

        $this->assertEquals('Manage users', $warden->ability()->first()->title);
    }

    #[Test()]
    public function ability_title_is_set_for_blanket_model_ability(): void
    {
        $warden = $this->bouncer($user = User::query()->create());

        $warden->allow($user)->to('create', User::class);

        $this->assertEquals('Create users', $warden->ability()->first()->title);
    }

    #[Test()]
    public function ability_title_is_set_for_regular_model_ability(): void
    {
        $warden = $this->bouncer($user = User::query()->create());

        $warden->allow($user)->to('delete', User::query()->create());

        $this->assertEquals('Delete user #2', $warden->ability()->first()->title);
    }

    #[Test()]
    public function ability_title_is_set_for_a_global_action_ability(): void
    {
        $warden = $this->bouncer($user = User::query()->create());

        $warden->allow($user)->to('delete')->everything();

        $this->assertEquals('Delete everything', $warden->ability()->first()->title);
    }
}
