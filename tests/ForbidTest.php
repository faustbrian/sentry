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
final class ForbidTest extends TestCase
{
    use TestsClipboards;

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function an_allowed_simple_ability_is_not_granted_when_forbidden($provider): void
    {
        [$warden, $user] = $provider();

        $warden->allow($user)->to('edit-site');
        $warden->forbid($user)->to('edit-site');

        $this->assertTrue($warden->cannot('edit-site'));

        $warden->unforbid($user)->to('edit-site');

        $this->assertTrue($warden->can('edit-site'));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function an_allowed_model_ability_is_not_granted_when_forbidden($provider): void
    {
        [$warden, $user] = $provider();

        $warden->allow($user)->to('delete', $user);
        $warden->forbid($user)->to('delete', $user);

        $this->assertTrue($warden->cannot('delete', $user));

        $warden->unforbid($user)->to('delete', $user);

        $this->assertTrue($warden->can('delete', $user));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function an_allowed_model_class_ability_is_not_granted_when_forbidden($provider): void
    {
        [$warden, $user] = $provider();

        $warden->allow($user)->to('delete', User::class);
        $warden->forbid($user)->to('delete', User::class);

        $this->assertTrue($warden->cannot('delete', User::class));

        $warden->unforbid($user)->to('delete', User::class);

        $this->assertTrue($warden->can('delete', User::class));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function forbidding_a_single_model_forbids_even_with_allowed_model_class_ability($provider): void
    {
        [$warden, $user] = $provider();

        $warden->allow($user)->to('delete', User::class);
        $warden->forbid($user)->to('delete', $user);

        $this->assertTrue($warden->cannot('delete', $user));

        $warden->unforbid($user)->to('delete', $user);

        $this->assertTrue($warden->can('delete', $user));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function forbidding_a_single_model_does_not_forbid_other_models($provider): void
    {
        [$warden, $user1, $user2] = $provider(2);

        $warden->allow($user1)->to('delete', User::class);
        $warden->forbid($user1)->to('delete', $user2);

        $this->assertTrue($warden->can('delete', $user1));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function forbidding_a_model_class_forbids_individual_models($provider): void
    {
        [$warden, $user] = $provider();

        $warden->allow($user)->to('delete', $user);
        $warden->forbid($user)->to('delete', User::class);

        $this->assertTrue($warden->cannot('delete', $user));

        $warden->unforbid($user)->to('delete', $user);

        $this->assertTrue($warden->cannot('delete', $user));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function forbidding_an_ability_through_a_role($provider): void
    {
        [$warden, $user] = $provider();

        $warden->forbid('admin')->to('delete', User::class);
        $warden->allow($user)->to('delete', User::class);
        $warden->assign('admin')->to($user);

        $this->assertTrue($warden->cannot('delete', User::class));
        $this->assertTrue($warden->cannot('delete', $user));

        $warden->unforbid('admin')->to('delete', User::class);

        $this->assertTrue($warden->can('delete', User::class));
        $this->assertTrue($warden->can('delete', $user));

        $warden->forbid('admin')->to('delete', $user);

        $this->assertTrue($warden->can('delete', User::class));
        $this->assertTrue($warden->cannot('delete', $user));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function forbidding_an_ability_allowed_through_a_role($provider): void
    {
        [$warden, $user] = $provider();

        $warden->allow('admin')->to('delete', User::class);
        $warden->forbid($user)->to('delete', User::class);
        $warden->assign('admin')->to($user);

        $this->assertTrue($warden->cannot('delete', User::class));
        $this->assertTrue($warden->cannot('delete', $user));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function forbidding_an_ability_when_everything_is_allowed($provider): void
    {
        [$warden, $user] = $provider();

        $warden->allow($user)->everything();
        $warden->forbid($user)->toManage(User::class);

        $this->assertTrue($warden->can('create', Account::class));
        $this->assertTrue($warden->cannot('create', User::class));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function forbid_an_ability_on_everything($provider): void
    {
        [$warden, $user] = $provider();

        $warden->allow($user)->everything();
        $warden->forbid($user)->to('delete')->everything();

        $this->assertTrue($warden->can('create', Account::class));
        $this->assertTrue($warden->cannot('delete', User::class));
        $this->assertTrue($warden->cannot('delete', $user));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function forbidding_and_unforbidding_an_ability_for_everyone($provider): void
    {
        [$warden, $user] = $provider();

        $warden->allow($user)->everything();
        $warden->forbidEveryone()->to('delete', Account::class);

        $this->assertTrue($warden->can('delete', User::class));
        $this->assertTrue($warden->cannot('delete', Account::class));

        $warden->unforbidEveryone()->to('delete', Account::class);

        $this->assertTrue($warden->can('delete', Account::class));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function forbidding_an_ability_stops_all_further_checks($provider): void
    {
        [$warden, $user] = $provider();

        $warden->define('sleep', fn (): true => true);

        $this->assertTrue($warden->can('sleep'));

        $warden->forbid($user)->to('sleep');

        $warden->runBeforePolicies();

        $this->assertTrue($warden->cannot('sleep'));
    }
}
