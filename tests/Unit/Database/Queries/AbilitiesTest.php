<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Database\Queries;

use Cline\Warden\Database\Queries\Abilities;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Fixtures\Models\User;
use Tests\TestCase;

/**
 * @internal
 *
 * @author Brian Faust <brian@cline.sh>
 */
#[Small()]
final class AbilitiesTest extends TestCase
{
    #[Test()]
    #[TestDox('Returns forbidden abilities for authority')]
    #[Group('happy-path')]
    public function returns_forbidden_abilities_for_authority(): void
    {
        // Arrange
        $user = User::query()->create(['name' => 'Test User']);
        $warden = $this->bouncer($user);

        $warden->forbid($user)->to('delete-posts');
        $warden->allow($user)->to('create-posts');

        // Act
        $forbiddenAbilities = Abilities::forbiddenForAuthority($user)->get();

        // Assert
        $this->assertGreaterThan(0, $forbiddenAbilities->count());
        $this->assertTrue($forbiddenAbilities->contains('name', 'delete-posts'));
        $this->assertFalse($forbiddenAbilities->contains('name', 'create-posts'));
    }

    #[Test()]
    #[TestDox('Returns empty collection when no forbidden abilities exist')]
    #[Group('edge-case')]
    public function returns_empty_collection_when_no_forbidden_abilities_exist(): void
    {
        // Arrange
        $user = User::query()->create(['name' => 'Test User']);
        $warden = $this->bouncer($user);

        $warden->allow($user)->to('create-posts');

        // Act
        $forbiddenAbilities = Abilities::forbiddenForAuthority($user)->get();

        // Assert
        $this->assertCount(0, $forbiddenAbilities);
    }

    #[Test()]
    #[TestDox('Queries forbidden abilities through roles')]
    #[Group('happy-path')]
    public function queries_forbidden_abilities_through_roles(): void
    {
        // Arrange
        $user = User::query()->create(['name' => 'Test User']);
        $warden = $this->bouncer($user);

        $warden->forbid('editor')->to('delete-site');
        $warden->assign('editor')->to($user);

        // Act
        $forbiddenAbilities = Abilities::forbiddenForAuthority($user)->get();

        // Assert
        $this->assertGreaterThan(0, $forbiddenAbilities->count());
        $this->assertTrue($forbiddenAbilities->contains('name', 'delete-site'));
    }

    #[Test()]
    #[TestDox('Queries allowed abilities for authority')]
    #[Group('happy-path')]
    public function queries_allowed_abilities_for_authority(): void
    {
        // Arrange
        $user = User::query()->create(['name' => 'Test User']);
        $warden = $this->bouncer($user);

        $warden->allow($user)->to('edit-posts');
        $warden->forbid($user)->to('delete-posts');

        // Act
        $allowedAbilities = Abilities::forAuthority($user, true)->get();

        // Assert
        $this->assertGreaterThan(0, $allowedAbilities->count());
        $this->assertTrue($allowedAbilities->contains('name', 'edit-posts'));
        $this->assertFalse($allowedAbilities->contains('name', 'delete-posts'));
    }

    #[Test()]
    #[TestDox('Queries abilities granted to everyone')]
    #[Group('happy-path')]
    public function queries_abilities_granted_to_everyone(): void
    {
        // Arrange
        $user = User::query()->create(['name' => 'Test User']);
        $warden = $this->bouncer($user);

        $warden->allowEveryone()->to('view-public-posts');

        // Act
        $abilities = Abilities::forAuthority($user, true)->get();

        // Assert
        $this->assertGreaterThan(0, $abilities->count());
        $this->assertTrue($abilities->contains('name', 'view-public-posts'));
    }

    #[Test()]
    #[TestDox('Combines abilities from roles, direct, and everyone')]
    #[Group('happy-path')]
    public function combines_abilities_from_roles_direct_and_everyone(): void
    {
        // Arrange
        $user = User::query()->create(['name' => 'Test User']);
        $warden = $this->bouncer($user);

        $warden->allow('editor')->to('edit-posts');
        $warden->assign('editor')->to($user);
        $warden->allow($user)->to('create-posts');
        $warden->allowEveryone()->to('view-posts');

        // Act
        $abilities = Abilities::forAuthority($user, true)->get();

        // Assert
        $this->assertGreaterThan(0, $abilities->count());
        $this->assertTrue($abilities->contains('name', 'edit-posts'));
        $this->assertTrue($abilities->contains('name', 'create-posts'));
        $this->assertTrue($abilities->contains('name', 'view-posts'));
    }
}
