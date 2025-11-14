<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\QueryScopes;

use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Models\User;
use Tests\TestCase;

/**
 * @internal
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class UserIsScopesTest extends TestCase
{
    #[Test()]
    public function users_can_be_constrained_to_having_a_role(): void
    {
        $user1 = User::query()->create(['name' => 'Joseph']);
        $user2 = User::query()->create(['name' => 'Silber']);

        $user1->assign('reader');
        $user2->assign('subscriber');

        $users = User::whereIs('reader')->get();

        $this->assertCount(1, $users);
        $this->assertEquals('Joseph', $users->first()->name);
    }

    #[Test()]
    public function users_can_be_constrained_to_having_one_of_many_roles(): void
    {
        $user1 = User::query()->create(['name' => 'Joseph']);
        $user2 = User::query()->create(['name' => 'Silber']);

        $user1->assign('reader');
        $user2->assign('subscriber');

        $users = User::whereIs('admin', 'subscriber')->get();

        $this->assertCount(1, $users);
        $this->assertEquals('Silber', $users->first()->name);
    }

    #[Test()]
    public function users_can_be_constrained_to_having_all_provided_roles(): void
    {
        $user1 = User::query()->create(['name' => 'Joseph']);
        $user2 = User::query()->create(['name' => 'Silber']);

        $user1->assign('reader')->assign('subscriber');
        $user2->assign('subscriber');

        $users = User::whereIsAll('subscriber', 'reader')->get();

        $this->assertCount(1, $users);
        $this->assertEquals('Joseph', $users->first()->name);
    }

    #[Test()]
    public function users_can_be_constrained_to_not_having_a_role(): void
    {
        $user1 = User::query()->create();
        $user2 = User::query()->create();
        $user3 = User::query()->create();

        $user1->assign('admin');
        $user2->assign('editor');
        $user3->assign('subscriber');

        $users = User::whereIsNot('admin')->get();

        $this->assertCount(2, $users);
        $this->assertFalse($users->contains($user1));
        $this->assertTrue($users->contains($user2));
        $this->assertTrue($users->contains($user3));
    }

    #[Test()]
    public function users_can_be_constrained_to_not_having_any_of_the_given_roles(): void
    {
        $user1 = User::query()->create();
        $user2 = User::query()->create();
        $user3 = User::query()->create();

        $user1->assign('admin');
        $user2->assign('editor');
        $user3->assign('subscriber');

        $users = User::whereIsNot('superadmin', 'editor', 'subscriber')->get();

        $this->assertCount(1, $users);
        $this->assertTrue($users->contains($user1));
        $this->assertFalse($users->contains($user2));
        $this->assertFalse($users->contains($user3));
    }
}
