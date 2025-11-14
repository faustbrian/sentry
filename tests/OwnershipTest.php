<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use Cline\Warden\Database\Models;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\TestsClipboards;
use Tests\Fixtures\Models\Account;

/**
 * @internal
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class OwnershipTest extends TestCase
{
    use TestsClipboards;

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function can_own_a_model_class($provider): void
    {
        [$warden, $user] = $provider();

        $warden->allow($user)->toOwn(Account::class);

        $account = Account::query()->create();
        $this->setOwner($account, $user);

        $this->assertTrue($warden->cannot('update', Account::class));
        $this->assertTrue($warden->can('update', $account));

        $this->clearOwner($account);

        $this->assertTrue($warden->cannot('update', $account));

        $warden->allow($user)->to('update', $account);
        $warden->disallow($user)->toOwn(Account::class);

        $this->assertTrue($warden->can('update', $account));

        $warden->disallow($user)->to('update', $account);

        $this->assertTrue($warden->cannot('update', $account));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function can_own_a_model($provider): void
    {
        [$warden, $user] = $provider();

        $account1 = Account::query()->create();
        $this->setOwner($account1, $user);
        $account2 = Account::query()->create();
        $this->setOwner($account2, $user);

        $warden->allow($user)->toOwn($account1);

        $this->assertTrue($warden->cannot('update', Account::class));
        $this->assertTrue($warden->cannot('update', $account2));
        $this->assertTrue($warden->can('update', $account1));

        $this->clearOwner($account1);

        $this->assertTrue($warden->cannot('update', $account1));

        $warden->allow($user)->to('update', $account1);
        $warden->disallow($user)->toOwn($account1);

        $this->assertTrue($warden->can('update', $account1));

        $warden->disallow($user)->to('update', $account1);

        $this->assertTrue($warden->cannot('update', $account1));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function can_own_a_model_for_a_given_ability($provider): void
    {
        [$warden, $user] = $provider();

        $account1 = Account::query()->create();
        $this->setOwner($account1, $user);
        $account2 = Account::query()->create();
        $this->setOwner($account2, $user);

        $warden->allow($user)->toOwn($account1)->to('update');
        $warden->allow($user)->toOwn($account2)->to(['view', 'update']);

        $this->assertTrue($warden->cannot('update', Account::class));
        $this->assertTrue($warden->can('update', $account1));
        $this->assertTrue($warden->cannot('delete', $account1));
        $this->assertTrue($warden->can('view', $account2));
        $this->assertTrue($warden->can('update', $account2));
        $this->assertTrue($warden->cannot('delete', $account2));

        $this->clearOwner($account1);

        $this->assertTrue($warden->cannot('update', $account1));

        $warden->allow($user)->to('update', $account1);
        $warden->disallow($user)->toOwn($account1)->to('update');

        $this->assertTrue($warden->can('update', $account1));

        $warden->disallow($user)->to('update', $account1);

        $this->assertTrue($warden->cannot('update', $account1));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function can_own_everything($provider): void
    {
        [$warden, $user] = $provider();

        $warden->allow($user)->toOwnEverything();

        $account = Account::query()->create();
        $this->setOwner($account, $user);

        $this->assertTrue($warden->cannot('delete', Account::class));
        $this->assertTrue($warden->can('delete', $account));

        $this->clearOwner($account);

        $this->assertTrue($warden->cannot('delete', $account));

        $account->user_id = $user->id;

        $warden->disallow($user)->toOwnEverything();

        $this->assertTrue($warden->cannot('delete', $account));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function can_own_everything_for_a_given_ability($provider): void
    {
        [$warden, $user] = $provider();

        $warden->allow($user)->toOwnEverything()->to('view');

        $account = Account::query()->create();
        $this->setOwner($account, $user);

        $this->assertTrue($warden->cannot('delete', Account::class));
        $this->assertTrue($warden->cannot('delete', $account));
        $this->assertTrue($warden->can('view', $account));

        $this->clearOwner($account);

        $this->assertTrue($warden->cannot('view', $account));

        $account->user_id = $user->id;

        $warden->disallow($user)->toOwnEverything()->to('view');

        $this->assertTrue($warden->cannot('view', $account));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function can_use_custom_ownership_attribute($provider): void
    {
        [$warden, $user] = $provider();

        $warden->ownedVia('userId');

        $account = Account::query()->create()->fill(['userId' => $user->id]);

        $warden->allow($user)->toOwn(Account::class);

        $this->assertTrue($warden->can('view', $account));

        Models::reset();
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function can_use_custom_ownership_attribute_for_model_type($provider): void
    {
        [$warden, $user] = $provider();

        $warden->ownedVia(Account::class, 'userId');

        $account = Account::query()->create()->fill(['userId' => $user->id]);

        $warden->allow($user)->toOwn(Account::class);

        $this->assertTrue($warden->can('view', $account));

        Models::reset();
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function can_forbid_abilities_after_owning_a_model_class($provider): void
    {
        [$warden, $user] = $provider();

        $warden->allow($user)->toOwn(Account::class);
        $warden->forbid($user)->to('publish', Account::class);

        $account = Account::query()->create();
        $this->setOwner($account, $user);

        $this->assertTrue($warden->can('update', $account));
        $this->assertTrue($warden->cannot('publish', $account));
    }

    /**
     * Set the actor of a model using actor morph relationship.
     */
    private function setOwner(Model $model, Model $actor): void
    {
        $model->actor_id = $actor->getKey();
        $model->actor_type = $actor->getMorphClass();
    }

    /**
     * Clear the actor of a model using actor morph relationship.
     */
    private function clearOwner(Model $model): void
    {
        $model->actor_id = 99;
        $model->actor_type = 'App\\FakeModel';
    }
}
