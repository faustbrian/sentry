<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Conductors;

use Cline\Warden\Conductors\RemovesRoles;
use Cline\Warden\Database\Role;
use PHPUnit\Framework\Attributes\CoversClass;
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
#[CoversClass(RemovesRoles::class)]
#[Small()]
final class RemovesRolesTest extends TestCase
{
    #[Test()]
    #[TestDox('Returns early when removing non-existent role names')]
    #[Group('edge-case')]
    public function returns_early_when_removing_non_existent_role_names(): void
    {
        // Arrange
        $user = User::query()->create(['name' => 'John Doe']);
        $conductor = new RemovesRoles(['non-existent-role', 'another-fake-role']);

        // Act
        $conductor->from($user);

        // Assert - Verify no roles were removed (user has no roles to begin with)
        $this->assertCount(0, $user->fresh()->roles);

        // Verify no database records exist for these role names
        $this->assertDatabaseMissing('roles', ['name' => 'non-existent-role']);
        $this->assertDatabaseMissing('roles', ['name' => 'another-fake-role']);

        // Verify no role assignments exist
        $this->assertDatabaseMissing('assigned_roles', ['actor_id' => $user->id]);
    }

    #[Test()]
    #[TestDox('Returns early when removing empty array of roles')]
    #[Group('edge-case')]
    public function returns_early_when_removing_empty_array_of_roles(): void
    {
        // Arrange
        $user = User::query()->create(['name' => 'Jane Doe']);
        $conductor = new RemovesRoles([]);

        // Act
        $conductor->from($user);

        // Assert - Verify no roles were removed
        $this->assertCount(0, $user->fresh()->roles);

        // Verify no role assignments exist
        $this->assertDatabaseMissing('assigned_roles', ['actor_id' => $user->id]);
    }

    #[Test()]
    #[TestDox('Returns early when removing non-existent role with existing roles assigned')]
    #[Group('edge-case')]
    public function returns_early_when_removing_non_existent_role_with_existing_roles_assigned(): void
    {
        // Arrange
        $user = User::query()->create(['name' => 'Bob Smith']);
        $existingRole = Role::query()->create(['name' => 'admin']);

        // Assign the existing role to the user
        $this->db()
            ->table('assigned_roles')
            ->insert([
                'role_id' => $existingRole->id,
                'actor_id' => $user->id,
                'actor_type' => $user->getMorphClass(),
            ]);

        $conductor = new RemovesRoles(['non-existent-role']);

        // Act
        $conductor->from($user);

        // Assert - Verify the existing role was NOT removed
        $this->assertDatabaseHas('assigned_roles', [
            'role_id' => $existingRole->id,
            'actor_id' => $user->id,
            'actor_type' => $user->getMorphClass(),
        ]);

        // Verify user still has 1 role
        $this->assertCount(1, $user->fresh()->roles);
    }

    #[Test()]
    #[TestDox('Removes valid roles but skips non-existent roles in mixed array')]
    #[Group('happy-path')]
    public function removes_valid_roles_but_skips_non_existent_roles_in_mixed_array(): void
    {
        // Arrange
        $user = User::query()->create(['name' => 'Alice Johnson']);
        $adminRole = Role::query()->create(['name' => 'admin']);
        $editorRole = Role::query()->create(['name' => 'editor']);

        // Assign both roles to the user
        $this->db()
            ->table('assigned_roles')
            ->insert([
                [
                    'role_id' => $adminRole->id,
                    'actor_id' => $user->id,
                    'actor_type' => $user->getMorphClass(),
                ],
                [
                    'role_id' => $editorRole->id,
                    'actor_id' => $user->id,
                    'actor_type' => $user->getMorphClass(),
                ],
            ]);

        // Remove admin role and a non-existent role
        $conductor = new RemovesRoles(['admin', 'non-existent-role']);

        // Act
        $conductor->from($user);

        // Assert - Verify admin role was removed
        $this->assertDatabaseMissing('assigned_roles', [
            'role_id' => $adminRole->id,
            'actor_id' => $user->id,
        ]);

        // Verify editor role still exists
        $this->assertDatabaseHas('assigned_roles', [
            'role_id' => $editorRole->id,
            'actor_id' => $user->id,
            'actor_type' => $user->getMorphClass(),
        ]);

        // Verify user has exactly 1 role remaining
        $this->assertCount(1, $user->fresh()->roles);
    }
}
