<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use Cline\Warden\Database\Ability;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Models\Account;
use Tests\Fixtures\Models\User;

/**
 * @internal
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class TitledAbilitiesTest extends TestCase
{
    #[Test()]
    public function allowing_simple_ability(): void
    {
        $warden = $this->bouncer($user = User::query()->create());

        $warden->allow($user)->to('access-dashboard', null, [
            'title' => 'Dashboard administration',
        ]);

        $this->seeTitledAbility('Dashboard administration');
    }

    #[Test()]
    public function allowing_model_class_ability(): void
    {
        $warden = $this->bouncer($user = User::query()->create());

        $warden->allow($user)->to('create', User::class, [
            'title' => 'Create users',
        ]);

        $this->seeTitledAbility('Create users');
    }

    #[Test()]
    public function allowing_model_ability(): void
    {
        $warden = $this->bouncer($user = User::query()->create());

        $warden->allow($user)->to('delete', $user, [
            'title' => 'Delete user #1',
        ]);

        $this->seeTitledAbility('Delete user #1');
    }

    #[Test()]
    public function allowing_everything(): void
    {
        $warden = $this->bouncer($user = User::query()->create());

        $warden->allow($user)->everything([
            'title' => 'Omnipotent',
        ]);

        $this->seeTitledAbility('Omnipotent');
    }

    #[Test()]
    public function allowing_to_manage_a_model_class(): void
    {
        $warden = $this->bouncer($user = User::query()->create());

        $warden->allow($user)->toManage(User::class, [
            'title' => 'Manage users',
        ]);

        $this->seeTitledAbility('Manage users');
    }

    #[Test()]
    public function allowing_to_manage_a_model(): void
    {
        $warden = $this->bouncer($user = User::query()->create());

        $warden->allow($user)->toManage($user, [
            'title' => 'Manage user #1',
        ]);

        $this->seeTitledAbility('Manage user #1');
    }

    #[Test()]
    public function allowing_an_ability_on_everything(): void
    {
        $warden = $this->bouncer($user = User::query()->create());

        $warden->allow($user)->to('create')->everything([
            'title' => 'Create anything',
        ]);

        $this->seeTitledAbility('Create anything');
    }

    #[Test()]
    public function allowing_to_own_a_model_class(): void
    {
        $warden = $this->bouncer($user = User::query()->create());

        $warden->allow($user)->toOwn(Account::class, [
            'title' => 'Manage onwed account',
        ]);

        $this->seeTitledAbility('Manage onwed account');
    }

    #[Test()]
    public function allowing_to_own_a_model(): void
    {
        $warden = $this->bouncer($user = User::query()->create());

        $warden->allow($user)->toOwn($user, [
            'title' => 'Manage user #1 when owned',
        ]);

        $this->seeTitledAbility('Manage user #1 when owned');
    }

    #[Test()]
    public function allowing_to_own_everything(): void
    {
        $warden = $this->bouncer($user = User::query()->create());

        $warden->allow($user)->toOwnEverything([
            'title' => 'Manage anything onwed',
        ]);

        $this->seeTitledAbility('Manage anything onwed');
    }

    #[Test()]
    public function forbidding_simple_ability(): void
    {
        $warden = $this->bouncer($user = User::query()->create());

        $warden->forbid($user)->to('access-dashboard', null, [
            'title' => 'Dashboard administration',
        ]);

        $this->seeTitledAbility('Dashboard administration');
    }

    #[Test()]
    public function forbidding_model_class_ability(): void
    {
        $warden = $this->bouncer($user = User::query()->create());

        $warden->forbid($user)->to('create', User::class, [
            'title' => 'Create users',
        ]);

        $this->seeTitledAbility('Create users');
    }

    #[Test()]
    public function forbidding_model_ability(): void
    {
        $warden = $this->bouncer($user = User::query()->create());

        $warden->forbid($user)->to('delete', $user, [
            'title' => 'Delete user #1',
        ]);

        $this->seeTitledAbility('Delete user #1');
    }

    #[Test()]
    public function forbidding_everything(): void
    {
        $warden = $this->bouncer($user = User::query()->create());

        $warden->forbid($user)->everything([
            'title' => 'Omnipotent',
        ]);

        $this->seeTitledAbility('Omnipotent');
    }

    #[Test()]
    public function forbidding_to_manage_a_model_class(): void
    {
        $warden = $this->bouncer($user = User::query()->create());

        $warden->forbid($user)->toManage(User::class, [
            'title' => 'Manage users',
        ]);

        $this->seeTitledAbility('Manage users');
    }

    #[Test()]
    public function forbidding_to_manage_a_model(): void
    {
        $warden = $this->bouncer($user = User::query()->create());

        $warden->forbid($user)->toManage($user, [
            'title' => 'Manage user #1',
        ]);

        $this->seeTitledAbility('Manage user #1');
    }

    #[Test()]
    public function forbidding_an_ability_on_everything(): void
    {
        $warden = $this->bouncer($user = User::query()->create());

        $warden->forbid($user)->to('create')->everything([
            'title' => 'Create anything',
        ]);

        $this->seeTitledAbility('Create anything');
    }

    #[Test()]
    public function forbidding_to_own_a_model_class(): void
    {
        $warden = $this->bouncer($user = User::query()->create());

        $warden->forbid($user)->toOwn(Account::class, [
            'title' => 'Manage onwed account',
        ]);

        $this->seeTitledAbility('Manage onwed account');
    }

    #[Test()]
    public function forbidding_to_own_a_model(): void
    {
        $warden = $this->bouncer($user = User::query()->create());

        $warden->forbid($user)->toOwn($user, [
            'title' => 'Manage user #1 when owned',
        ]);

        $this->seeTitledAbility('Manage user #1 when owned');
    }

    #[Test()]
    public function forbidding_to_own_everything(): void
    {
        $warden = $this->bouncer($user = User::query()->create());

        $warden->forbid($user)->toOwnEverything([
            'title' => 'Manage anything onwed',
        ]);

        $this->seeTitledAbility('Manage anything onwed');
    }

    /**
     * Assert that there's an ability with the given title in the DB.
     */
    private function seeTitledAbility(string $title): void
    {
        $this->assertTrue(Ability::query()->where(['title' => $title])->exists());
    }
}
