<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Models\Team;
use Tests\Fixtures\Models\User;

/**
 * @internal
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ContextAwarePermissionsTest extends TestCase
{
    #[Test()]
    public function can_grant_context_aware_ability(): void
    {
        $warden = $this->bouncer();
        $user = User::query()->create();
        $team = Team::query()->create(['name' => 'Test Team']);

        $warden->allow($user)->within($team)->to('view-invoices');

        $abilities = $user->abilities()->get();

        $this->assertCount(1, $abilities);
        $this->assertEquals('view-invoices', $abilities->first()->name);
        $this->assertEquals($team->getKey(), $abilities->first()->pivot->context_id);
        $this->assertEquals($team->getMorphClass(), $abilities->first()->pivot->context_type);
    }

    #[Test()]
    public function can_grant_context_aware_role(): void
    {
        $warden = $this->bouncer();
        $user = User::query()->create();
        $team = Team::query()->create(['name' => 'Test Team']);

        $warden->assign('admin')->within($team)->to($user);

        $roles = $user->roles()->get();

        $this->assertCount(1, $roles);
        $this->assertEquals($team->getKey(), $roles->first()->pivot->context_id);
        $this->assertEquals($team->getMorphClass(), $roles->first()->pivot->context_type);
    }

    #[Test()]
    public function can_forbid_context_aware_ability(): void
    {
        $warden = $this->bouncer();
        $user = User::query()->create();
        $team = Team::query()->create(['name' => 'Test Team']);

        $warden->forbid($user)->within($team)->to('delete-invoices');

        $abilities = $user->abilities()->wherePivot('forbidden', true)->get();

        $this->assertCount(1, $abilities);
        $this->assertEquals('delete-invoices', $abilities->first()->name);
        $this->assertEquals($team->getKey(), $abilities->first()->pivot->context_id);
        $this->assertEquals($team->getMorphClass(), $abilities->first()->pivot->context_type);
    }

    #[Test()]
    public function context_aware_permissions_are_independent_from_global(): void
    {
        $warden = $this->bouncer();
        $user = User::query()->create();
        $team1 = Team::query()->create(['name' => 'Team 1']);
        $team2 = Team::query()->create(['name' => 'Team 2']);

        $warden->allow($user)->to('view-invoices');
        $warden->allow($user)->within($team1)->to('edit-invoices');
        $warden->allow($user)->within($team2)->to('delete-invoices');

        $abilities = $user->abilities()->get();

        $this->assertCount(3, $abilities);

        $globalAbility = $abilities->firstWhere('pivot.context_id', null);
        $team1Ability = $abilities->firstWhere('pivot.context_id', $team1->getKey());
        $team2Ability = $abilities->firstWhere('pivot.context_id', $team2->getKey());

        $this->assertInstanceOf(Model::class, $globalAbility);
        $this->assertInstanceOf(Model::class, $team1Ability);
        $this->assertInstanceOf(Model::class, $team2Ability);

        $this->assertEquals('view-invoices', $globalAbility->name);
        $this->assertEquals('edit-invoices', $team1Ability->name);
        $this->assertEquals('delete-invoices', $team2Ability->name);
    }

    #[Test()]
    public function can_grant_context_aware_ability_with_model_class(): void
    {
        $warden = $this->bouncer();
        $user = User::query()->create();
        $team = Team::query()->create(['name' => 'Test Team']);

        $warden->allow($user)->within($team)->to('edit', User::class);

        $abilities = $user->abilities()->get();

        $this->assertCount(1, $abilities);
        $this->assertEquals($team->getKey(), $abilities->first()->pivot->context_id);
        $this->assertEquals($team->getMorphClass(), $abilities->first()->pivot->context_type);
        $this->assertEquals('edit', $abilities->first()->name);
        $this->assertEquals(User::class, $abilities->first()->subject_type);
        $this->assertNull($abilities->first()->subject_id);
    }

    #[Test()]
    public function can_grant_context_aware_ability_with_model_instance(): void
    {
        $warden = $this->bouncer();
        $user = User::query()->create();
        $team = Team::query()->create(['name' => 'Test Team']);
        $invoice = User::query()->create(['name' => 'Invoice Model']);

        $warden->allow($user)->within($team)->to('view', $invoice);

        $abilities = $user->abilities()->get();

        $this->assertCount(1, $abilities);
        $this->assertEquals($team->getKey(), $abilities->first()->pivot->context_id);
        $this->assertEquals($team->getMorphClass(), $abilities->first()->pivot->context_type);
        $this->assertEquals($invoice->getKey(), $abilities->first()->subject_id);
    }
}
