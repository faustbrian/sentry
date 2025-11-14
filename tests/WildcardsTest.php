<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\TestsClipboards;
use Tests\Fixtures\Models\Account;
use Tests\Fixtures\Models\User;

/**
 * @internal
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class WildcardsTest extends TestCase
{
    use TestsClipboards;

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function a_wildard_ability_allows_everything($provider): void
    {
        [$warden, $user] = $provider();

        $warden->allow($user)->to('*');

        $this->assertTrue($warden->can('edit-site'));
        $this->assertTrue($warden->can('*'));

        $warden->disallow($user)->to('*');

        $this->assertTrue($warden->cannot('edit-site'));
        $this->assertTrue($warden->cannot('*'));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function manage_allows_all_actions_on_a_model($provider): void
    {
        [$warden, $user] = $provider();

        $warden->allow($user)->toManage($user);

        $this->assertTrue($warden->can('*', $user));
        $this->assertTrue($warden->can('edit', $user));
        $this->assertTrue($warden->cannot('*', User::class));
        $this->assertTrue($warden->cannot('edit', User::class));

        $warden->disallow($user)->toManage($user);

        $this->assertTrue($warden->cannot('*', $user));
        $this->assertTrue($warden->cannot('edit', $user));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function manage_on_a_model_class_allows_all_actions_on_all_its_models($provider): void
    {
        [$warden, $user] = $provider();

        $warden->allow($user)->toManage(User::class);

        $this->assertTrue($warden->can('*', $user));
        $this->assertTrue($warden->can('edit', $user));
        $this->assertTrue($warden->can('*', User::class));
        $this->assertTrue($warden->can('edit', User::class));
        $this->assertTrue($warden->cannot('edit', Account::class));
        $this->assertTrue($warden->cannot('edit', Account::class));

        $warden->disallow($user)->toManage(User::class);

        $this->assertTrue($warden->cannot('*', $user));
        $this->assertTrue($warden->cannot('edit', $user));
        $this->assertTrue($warden->cannot('*', User::class));
        $this->assertTrue($warden->cannot('edit', User::class));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function always_allows_the_action_on_all_models($provider): void
    {
        [$warden, $user] = $provider();

        $warden->allow($user)->to('delete')->everything();

        $this->assertTrue($warden->can('delete', $user));
        $this->assertTrue($warden->cannot('update', $user));
        $this->assertTrue($warden->can('delete', User::class));
        $this->assertTrue($warden->can('delete', '*'));

        $warden->disallow($user)->to('delete')->everything();

        $this->assertTrue($warden->cannot('delete', $user));
        $this->assertTrue($warden->cannot('delete', User::class));
        $this->assertTrue($warden->cannot('delete', '*'));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function everything_allows_everything($provider): void
    {
        [$warden, $user] = $provider();

        $warden->allow($user)->everything();

        $this->assertTrue($warden->can('*'));
        $this->assertTrue($warden->can('*', '*'));
        $this->assertTrue($warden->can('*', $user));
        $this->assertTrue($warden->can('*', User::class));
        $this->assertTrue($warden->can('ban', '*'));
        $this->assertTrue($warden->can('ban-users'));
        $this->assertTrue($warden->can('ban', $user));
        $this->assertTrue($warden->can('ban', User::class));

        $warden->disallow($user)->everything();

        $this->assertTrue($warden->cannot('*'));
        $this->assertTrue($warden->cannot('*', '*'));
        $this->assertTrue($warden->cannot('*', $user));
        $this->assertTrue($warden->cannot('*', User::class));
        $this->assertTrue($warden->cannot('ban', '*'));
        $this->assertTrue($warden->cannot('ban-users'));
        $this->assertTrue($warden->cannot('ban', $user));
        $this->assertTrue($warden->cannot('ban', User::class));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function a_simple_wildard_ability_denies_model_abilities($provider): void
    {
        [$warden, $user] = $provider();

        $warden->allow($user)->to('*');

        $this->assertTrue($warden->cannot('edit', $user));
        $this->assertTrue($warden->cannot('edit', User::class));
        $this->assertTrue($warden->cannot('*', $user));
        $this->assertTrue($warden->cannot('*', User::class));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function manage_denies_simple_abilities($provider): void
    {
        [$warden, $user] = $provider();

        $warden->allow($user)->toManage($user);

        $this->assertTrue($warden->cannot('edit'));
        $this->assertTrue($warden->cannot('*'));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function manage_on_a_model_class_denies_simple_abilities($provider): void
    {
        [$warden, $user] = $provider();

        $warden->allow($user)->toManage(User::class);

        $this->assertTrue($warden->cannot('*'));
        $this->assertTrue($warden->cannot('edit'));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function always_denies_simple_abilities($provider): void
    {
        [$warden, $user] = $provider();

        $warden->allow($user)->to('delete')->everything();

        $this->assertTrue($warden->cannot('delete'));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function manage_allows_all_actions_on_multiple_models($provider): void
    {
        [$warden, $user] = $provider();

        $warden->allow($user)->toManage([User::class, Account::class]);

        $this->assertTrue($warden->can('*', User::class));
        $this->assertTrue($warden->can('edit', User::class));
        $this->assertTrue($warden->can('*', Account::class));
        $this->assertTrue($warden->can('edit', Account::class));
        $this->assertTrue($warden->can('*', $user));
        $this->assertTrue($warden->can('edit', $user));

        $warden->disallow($user)->toManage([User::class, Account::class]);

        $this->assertTrue($warden->cannot('*', User::class));
        $this->assertTrue($warden->cannot('edit', User::class));
        $this->assertTrue($warden->cannot('*', Account::class));
        $this->assertTrue($warden->cannot('edit', Account::class));
        $this->assertTrue($warden->cannot('*', $user));
        $this->assertTrue($warden->cannot('edit', $user));
    }
}
