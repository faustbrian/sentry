<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use Cline\Warden\Database\Ability;
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
final class AbilitiesForModelsTest extends TestCase
{
    use TestsClipboards;

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function model_blanket_ability($provider): void
    {
        [$warden, $user1, $user2] = $provider(2);

        $warden->allow($user1)->to('edit', User::class);

        $this->assertTrue($warden->cannot('edit'));
        $this->assertTrue($warden->can('edit', User::class));
        $this->assertTrue($warden->can('edit', $user2));

        $warden->disallow($user1)->to('edit', User::class);

        $this->assertTrue($warden->cannot('edit'));
        $this->assertTrue($warden->cannot('edit', User::class));
        $this->assertTrue($warden->cannot('edit', $user2));

        $warden->disallow($user1)->to('edit');

        $this->assertTrue($warden->cannot('edit'));
        $this->assertTrue($warden->cannot('edit', User::class));
        $this->assertTrue($warden->cannot('edit', $user2));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function individual_model_ability($provider): void
    {
        [$warden, $user1, $user2] = $provider(2);

        $warden->allow($user1)->to('edit', $user2);

        $this->assertTrue($warden->cannot('edit'));
        $this->assertTrue($warden->cannot('edit', User::class));
        $this->assertTrue($warden->cannot('edit', $user1));
        $this->assertTrue($warden->can('edit', $user2));

        $warden->disallow($user1)->to('edit', User::class);

        $this->assertTrue($warden->can('edit', $user2));

        $warden->disallow($user1)->to('edit', $user1);

        $this->assertTrue($warden->can('edit', $user2));

        $warden->disallow($user1)->to('edit', $user2);

        $this->assertTrue($warden->cannot('edit'));
        $this->assertTrue($warden->cannot('edit', User::class));
        $this->assertTrue($warden->cannot('edit', $user1));
        $this->assertTrue($warden->cannot('edit', $user2));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function blanket_ability_and_individual_model_ability_are_kept_separate($provider): void
    {
        [$warden, $user1, $user2] = $provider(2);

        $warden->allow($user1)->to('edit', User::class);
        $warden->allow($user1)->to('edit', $user2);

        $this->assertTrue($warden->can('edit', User::class));
        $this->assertTrue($warden->can('edit', $user2));

        $warden->disallow($user1)->to('edit', User::class);

        $this->assertTrue($warden->cannot('edit', User::class));
        $this->assertTrue($warden->can('edit', $user2));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function allowing_on_non_existent_model_throws($provider): void
    {
        $this->expectException('InvalidArgumentException');

        [$warden, $user] = $provider();

        $warden->allow($user)->to('delete', new User());
    }

    #[Test()]
    public function can_create_an_ability_for_a_model(): void
    {
        $ability = Ability::createForModel(Account::class, 'delete');

        $this->assertEquals(Account::class, $ability->subject_type);
        $this->assertEquals('delete', $ability->name);
        $this->assertNull($ability->subject_id);
    }

    #[Test()]
    public function can_create_an_ability_for_a_model_plus_extra_attributes(): void
    {
        $ability = Ability::createForModel(Account::class, [
            'name' => 'delete',
            'title' => 'Delete Accounts',
        ]);

        $this->assertEquals('Delete Accounts', $ability->title);
        $this->assertEquals(Account::class, $ability->subject_type);
        $this->assertEquals('delete', $ability->name);
        $this->assertNull($ability->subject_id);
    }

    #[Test()]
    public function can_create_an_ability_for_a_model_instance(): void
    {
        $user = User::query()->create();

        $ability = Ability::createForModel($user, 'delete');

        $this->assertEquals($user->id, $ability->subject_id);
        $this->assertEquals(User::class, $ability->subject_type);
        $this->assertEquals('delete', $ability->name);
    }

    #[Test()]
    public function can_create_an_ability_for_a_model_instance_plus_extra_attributes(): void
    {
        $user = User::query()->create();

        $ability = Ability::createForModel($user, [
            'name' => 'delete',
            'title' => 'Delete this user',
        ]);

        $this->assertEquals('Delete this user', $ability->title);
        $this->assertEquals($user->id, $ability->subject_id);
        $this->assertEquals(User::class, $ability->subject_type);
        $this->assertEquals('delete', $ability->name);
    }

    #[Test()]
    public function can_create_an_ability_for_all_models(): void
    {
        $ability = Ability::createForModel('*', 'delete');

        $this->assertEquals('*', $ability->subject_type);
        $this->assertEquals('delete', $ability->name);
        $this->assertNull($ability->subject_id);
    }

    #[Test()]
    public function can_create_an_ability_for_all_models_plus_extra_attributes(): void
    {
        $ability = Ability::createForModel('*', [
            'name' => 'delete',
            'title' => 'Delete everything',
        ]);

        $this->assertEquals('Delete everything', $ability->title);
        $this->assertEquals('*', $ability->subject_type);
        $this->assertEquals('delete', $ability->name);
        $this->assertNull($ability->subject_id);
    }
}
