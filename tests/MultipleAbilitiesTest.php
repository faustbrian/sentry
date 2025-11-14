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
final class MultipleAbilitiesTest extends TestCase
{
    use TestsClipboards;

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function allowing_multiple_abilities($provider): void
    {
        [$warden, $user] = $provider();

        $warden->allow($user)->to(['edit', 'delete']);

        $this->assertTrue($warden->can('edit'));
        $this->assertTrue($warden->can('delete'));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function allowing_multiple_model_abilities($provider): void
    {
        [$warden, $user1, $user2] = $provider(2);

        $warden->allow($user1)->to(['edit', 'delete'], $user1);

        $this->assertTrue($warden->can('edit', $user1));
        $this->assertTrue($warden->can('delete', $user1));
        $this->assertTrue($warden->cannot('edit', $user2));
        $this->assertTrue($warden->cannot('delete', $user2));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function allowing_multiple_blanket_model_abilities($provider): void
    {
        [$warden, $user] = $provider();

        $warden->allow($user)->to(['edit', 'delete'], User::class);

        $this->assertTrue($warden->can('edit', User::class));
        $this->assertTrue($warden->can('delete', User::class));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function allowing_an_ability_on_multiple_models($provider): void
    {
        [$warden, $user1, $user2] = $provider(2);

        $warden->allow($user1)->to('delete', [Account::class, $user1]);

        $this->assertTrue($warden->can('delete', Account::class));
        $this->assertTrue($warden->can('delete', $user1));
        $this->assertTrue($warden->cannot('delete', $user2));
        $this->assertTrue($warden->cannot('delete', User::class));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function allowing_multiple_abilities_on_multiple_models($provider): void
    {
        [$warden, $user1, $user2] = $provider(2);

        $warden->allow($user1)->to(['update', 'delete'], [Account::class, $user1]);

        $this->assertTrue($warden->can('update', Account::class));
        $this->assertTrue($warden->can('delete', Account::class));
        $this->assertTrue($warden->can('update', $user1));
        $this->assertTrue($warden->cannot('update', $user2));
        $this->assertTrue($warden->cannot('update', User::class));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function allowing_multiple_abilities_via_a_map($provider): void
    {
        [$warden, $user1, $user2] = $provider(2);

        $account1 = Account::query()->create();
        $account2 = Account::query()->create();

        $warden->allow($user1)->to([
            'edit' => User::class,
            'delete' => $user1,
            'view' => Account::class,
            'update' => $account1,
            'access-dashboard',
        ]);

        $this->assertTrue($warden->can('edit', User::class));
        $this->assertTrue($warden->cannot('view', User::class));
        $this->assertTrue($warden->can('delete', $user1));
        $this->assertTrue($warden->cannot('delete', $user2));

        $this->assertTrue($warden->can('view', Account::class));
        $this->assertTrue($warden->cannot('update', Account::class));
        $this->assertTrue($warden->can('update', $account1));
        $this->assertTrue($warden->cannot('update', $account2));

        $this->assertTrue($warden->can('access-dashboard'));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function disallowing_multiple_abilties($provider): void
    {
        [$warden, $user] = $provider();

        $warden->allow($user)->to(['edit', 'delete']);
        $warden->disallow($user)->to(['edit', 'delete']);

        $this->assertTrue($warden->cannot('edit'));
        $this->assertTrue($warden->cannot('delete'));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function disallowing_multiple_model_abilities($provider): void
    {
        [$warden, $user] = $provider();

        $warden->allow($user)->to(['view', 'edit', 'delete'], $user);
        $warden->disallow($user)->to(['edit', 'delete'], $user);

        $this->assertTrue($warden->can('view', $user));
        $this->assertTrue($warden->cannot('edit', $user));
        $this->assertTrue($warden->cannot('delete', $user));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function disallowing_multiple_blanket_model_abilities($provider): void
    {
        [$warden, $user] = $provider();

        $warden->allow($user)->to(['edit', 'delete'], User::class);
        $warden->disallow($user)->to(['edit', 'delete'], User::class);

        $this->assertTrue($warden->cannot('edit', User::class));
        $this->assertTrue($warden->cannot('delete', User::class));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function disallowing_multiple_abilities_via_a_map($provider): void
    {
        [$warden, $user1, $user2] = $provider(2);

        $account1 = Account::query()->create();
        Account::query()->create();

        $warden->allow($user1)->to([
            'edit' => User::class,
            'delete' => $user1,
            'view' => Account::class,
            'update' => $account1,
        ]);

        $warden->disallow($user1)->to([
            'edit' => User::class,
            'update' => $account1,
        ]);

        $this->assertTrue($warden->cannot('edit', User::class));
        $this->assertTrue($warden->can('delete', $user1));
        $this->assertTrue($warden->can('view', $account1));
        $this->assertTrue($warden->cannot('update', $account1));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function forbidding_multiple_abilities($provider): void
    {
        [$warden, $user] = $provider();

        $warden->allow($user)->to(['edit', 'delete']);
        $warden->forbid($user)->to(['edit', 'delete']);

        $this->assertTrue($warden->cannot('edit'));
        $this->assertTrue($warden->cannot('delete'));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function forbidding_multiple_model_abilities($provider): void
    {
        [$warden, $user1, $user2] = $provider(2);

        $warden->allow($user1)->to(['view', 'edit', 'delete']);
        $warden->allow($user1)->to(['view', 'edit', 'delete'], $user1);
        $warden->allow($user1)->to(['view', 'edit', 'delete'], $user2);
        $warden->forbid($user1)->to(['edit', 'delete'], $user1);

        $this->assertTrue($warden->can('view'));
        $this->assertTrue($warden->can('edit'));

        $this->assertTrue($warden->can('view', $user1));
        $this->assertTrue($warden->cannot('edit', $user1));
        $this->assertTrue($warden->cannot('delete', $user1));
        $this->assertTrue($warden->can('edit', $user2));
        $this->assertTrue($warden->can('delete', $user2));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function forbidding_multiple_blanket_model_abilities($provider): void
    {
        [$warden, $user] = $provider();

        $warden->allow($user)->to(['edit', 'delete']);
        $warden->allow($user)->to(['edit', 'delete'], Account::class);
        $warden->allow($user)->to(['view', 'edit', 'delete'], User::class);
        $warden->forbid($user)->to(['edit', 'delete'], User::class);

        $this->assertTrue($warden->can('edit'));
        $this->assertTrue($warden->can('delete'));

        $this->assertTrue($warden->can('edit', Account::class));
        $this->assertTrue($warden->can('delete', Account::class));

        $this->assertTrue($warden->can('view', User::class));
        $this->assertTrue($warden->cannot('edit', User::class));
        $this->assertTrue($warden->cannot('delete', User::class));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function forbidding_multiple_abilities_via_a_map($provider): void
    {
        [$warden, $user1, $user2] = $provider(2);

        $account1 = Account::query()->create();
        Account::query()->create();

        $warden->allow($user1)->to([
            'edit' => User::class,
            'delete' => $user1,
            'view' => Account::class,
            'update' => $account1,
        ]);

        $warden->forbid($user1)->to([
            'edit' => User::class,
            'update' => $account1,
        ]);

        $this->assertTrue($warden->cannot('edit', User::class));
        $this->assertTrue($warden->can('delete', $user1));
        $this->assertTrue($warden->can('view', $account1));
        $this->assertTrue($warden->cannot('update', $account1));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function unforbidding_multiple_abilities($provider): void
    {
        [$warden, $user] = $provider();

        $warden->allow($user)->to(['view', 'edit', 'delete']);
        $warden->forbid($user)->to(['view', 'edit', 'delete']);
        $warden->unforbid($user)->to(['edit', 'delete']);

        $this->assertTrue($warden->cannot('view'));
        $this->assertTrue($warden->can('edit'));
        $this->assertTrue($warden->can('delete'));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function unforbidding_multiple_model_abilities($provider): void
    {
        [$warden, $user] = $provider();

        $warden->allow($user)->to(['view', 'edit', 'delete'], $user);
        $warden->forbid($user)->to(['view', 'edit', 'delete'], $user);
        $warden->unforbid($user)->to(['edit', 'delete'], $user);

        $this->assertTrue($warden->cannot('view', $user));
        $this->assertTrue($warden->can('edit', $user));
        $this->assertTrue($warden->can('delete', $user));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function unforbidding_multiple_blanket_model_abilities($provider): void
    {
        [$warden, $user] = $provider();

        $warden->allow($user)->to(['view', 'edit', 'delete'], User::class);
        $warden->forbid($user)->to(['view', 'edit', 'delete'], User::class);
        $warden->unforbid($user)->to(['edit', 'delete'], User::class);

        $this->assertTrue($warden->cannot('view', User::class));
        $this->assertTrue($warden->can('edit', User::class));
        $this->assertTrue($warden->can('delete', User::class));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function unforbidding_multiple_abilities_via_a_map($provider): void
    {
        [$warden, $user1, $user2] = $provider(2);

        $account1 = Account::query()->create();
        Account::query()->create();

        $warden->allow($user1)->to([
            'edit' => User::class,
            'delete' => $user1,
            'view' => Account::class,
            'update' => $account1,
        ]);

        $warden->forbid($user1)->to([
            'edit' => User::class,
            'delete' => $user1,
            'view' => Account::class,
            'update' => $account1,
        ]);

        $warden->unforbid($user1)->to([
            'edit' => User::class,
            'update' => $account1,
        ]);

        $this->assertTrue($warden->can('edit', User::class));
        $this->assertTrue($warden->cannot('delete', $user1));
        $this->assertTrue($warden->cannot('view', $account1));
        $this->assertTrue($warden->can('update', $account1));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function passthru_can_any_check($provider): void
    {
        [$warden, $user1] = $provider();

        $user1->allow('create', User::class);

        $this->assertTrue($warden->canAny(['create', 'delete'], User::class));
        $this->assertFalse($warden->canAny(['update', 'delete'], User::class));
    }
}
