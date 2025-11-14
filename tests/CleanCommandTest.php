<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use Cline\Warden\Console\CleanCommand;
use Cline\Warden\Database\Ability;
use Illuminate\Console\Application as Artisan;
use Override;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Models\Account;
use Tests\Fixtures\Models\User;

/**
 * @internal
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class CleanCommandTest extends TestCase
{
    /**
     * Setup the world for the tests.
     */
    #[Override()]
    protected function setUp(): void
    {
        Artisan::starting(
            fn ($artisan) => $artisan->resolveCommands(CleanCommand::class),
        );

        parent::setUp();
    }

    #[Test()]
    public function the_orphaned_flag(): void
    {
        $warden = $this->bouncer($user = User::query()->create())->dontCache();

        $warden->allow($user)->to(['access-dashboard', 'ban-users', 'throw-dishes']);
        $warden->disallow($user)->to(['access-dashboard', 'ban-users']);

        $this->assertEquals(3, Ability::query()->count());

        $this
            ->artisan('warden:clean --unassigned')
            ->expectsOutput('Deleted 2 unassigned abilities.');

        $this->assertEquals(1, Ability::query()->count());
        $this->assertTrue($warden->can('throw-dishes'));
    }

    #[Test()]
    public function the_unassigned_flag_with_no_unassigned_abilities(): void
    {
        $warden = $this->bouncer($user = User::query()->create())->dontCache();

        $warden->allow($user)->to(['access-dashboard', 'ban-users', 'throw-dishes']);

        $this->assertEquals(3, Ability::query()->count());

        $this
            ->artisan('warden:clean --unassigned')
            ->expectsOutput('No unassigned abilities.');

        $this->assertEquals(3, Ability::query()->count());
    }

    #[Test()]
    public function the_missing_flag(): void
    {
        $warden = $this->bouncer($user1 = User::query()->create())->dontCache();

        $account1 = Account::query()->create();
        $account2 = Account::query()->create();
        $user2 = User::query()->create();

        $warden->allow($user1)->to('create', Account::class);
        $warden->allow($user1)->to('create', User::class);
        $warden->allow($user1)->to('update', $user1);
        $warden->allow($user1)->to('update', $user2);
        $warden->allow($user1)->to('update', $account1);
        $warden->allow($user1)->to('update', $account2);

        $account1->delete();
        $user2->delete();

        $this->assertEquals(6, Ability::query()->count());

        $this
            ->artisan('warden:clean --orphaned')
            ->expectsOutput('Deleted 2 orphaned abilities.');

        $this->assertEquals(4, Ability::query()->count());
        $this->assertTrue($warden->can('create', Account::class));
        $this->assertTrue($warden->can('create', User::class));
        $this->assertTrue($warden->can('update', $user1));
        $this->assertTrue($warden->can('update', $account2));
    }

    #[Test()]
    public function the_orphaned_flag_with_no_orphaned_abilities(): void
    {
        $warden = $this->bouncer($user = User::query()->create())->dontCache();

        $account = Account::query()->create();

        $warden->allow($user)->to('update', $user);
        $warden->allow($user)->to('update', $account);

        $this->assertEquals(2, Ability::query()->count());

        $this
            ->artisan('warden:clean --orphaned')
            ->expectsOutput('No orphaned abilities.');

        $this->assertEquals(2, Ability::query()->count());
    }

    #[Test()]
    public function no_flags(): void
    {
        $warden = $this->bouncer($user1 = User::query()->create())->dontCache();

        $account1 = Account::query()->create();
        $account2 = Account::query()->create();
        $user2 = User::query()->create();

        $warden->allow($user1)->to('update', $user1);
        $warden->allow($user1)->to('update', $user2);
        $warden->allow($user1)->to('update', $account1);
        $warden->allow($user1)->to('update', $account2);

        $warden->disallow($user1)->to('update', $user1);
        $account1->delete();

        $this->assertEquals(4, Ability::query()->count());

        $this
            ->artisan('warden:clean')
            ->expectsOutput('Deleted 1 unassigned ability.')
            ->expectsOutput('Deleted 1 orphaned ability.');

        $this->assertEquals(2, Ability::query()->count());
    }
}
