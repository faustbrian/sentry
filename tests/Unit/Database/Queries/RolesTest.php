<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Database\Queries;

use Cline\Warden\Database\Queries\Roles;
use Cline\Warden\Database\Role;
use Illuminate\Database\Eloquent\Collection;
use Override;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Models\User;
use Tests\TestCase;

/**
 * @internal
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class RolesTest extends TestCase
{
    private Roles $rolesQuery;

    #[Override()]
    protected function setUp(): void
    {
        parent::setUp();

        $this->rolesQuery = new Roles();
    }

    #[Test()]
    public function constrain_where_is_filters_by_single_role(): void
    {
        // Arrange
        Role::query()->create(['name' => 'admin']);
        Role::query()->create(['name' => 'editor']);

        $user1 = User::query()->create(['name' => 'Admin User']);
        $user2 = User::query()->create(['name' => 'Editor User']);
        $user3 = User::query()->create(['name' => 'Regular User']);

        $warden = $this->bouncer($user1);
        $warden->assign('admin')->to($user1);
        $warden->assign('editor')->to($user2);

        // Act
        $query = User::query();
        $this->rolesQuery->constrainWhereIs($query, 'admin');
        $users = $query->get();

        // Assert
        $this->assertCount(1, $users);
        $this->assertTrue($users->contains($user1));
        $this->assertFalse($users->contains($user2));
        $this->assertFalse($users->contains($user3));
    }

    #[Test()]
    public function constrain_where_is_filters_by_multiple_roles_with_or_logic(): void
    {
        // Arrange
        Role::query()->create(['name' => 'admin']);
        Role::query()->create(['name' => 'editor']);
        Role::query()->create(['name' => 'subscriber']);

        $user1 = User::query()->create(['name' => 'Admin User']);
        $user2 = User::query()->create(['name' => 'Editor User']);
        $user3 = User::query()->create(['name' => 'Subscriber User']);
        $user4 = User::query()->create(['name' => 'Regular User']);

        $warden = $this->bouncer($user1);
        $warden->assign('admin')->to($user1);
        $warden->assign('editor')->to($user2);
        $warden->assign('subscriber')->to($user3);

        // Act
        $query = User::query();
        $this->rolesQuery->constrainWhereIs($query, 'admin', 'editor');
        $users = $query->get();

        // Assert
        $this->assertCount(2, $users);
        $this->assertTrue($users->contains($user1));
        $this->assertTrue($users->contains($user2));
        $this->assertFalse($users->contains($user3));
        $this->assertFalse($users->contains($user4));
    }

    #[Test()]
    public function constrain_where_is_returns_empty_when_no_users_have_role(): void
    {
        // Arrange
        Role::query()->create(['name' => 'admin']);
        User::query()->create(['name' => 'Regular User']);

        // Act
        $query = User::query();
        $this->rolesQuery->constrainWhereIs($query, 'admin');
        $users = $query->get();

        // Assert
        $this->assertCount(0, $users);
    }

    #[Test()]
    public function constrain_where_is_all_requires_exact_role_count_match(): void
    {
        // Arrange
        Role::query()->create(['name' => 'admin']);
        Role::query()->create(['name' => 'editor']);
        Role::query()->create(['name' => 'manager']);

        $user1 = User::query()->create(['name' => 'Admin Editor']);
        $user2 = User::query()->create(['name' => 'Admin Only']);
        $user3 = User::query()->create(['name' => 'Admin Editor Manager']);

        $warden = $this->bouncer($user1);
        $warden->assign('admin')->to($user1);
        $warden->assign('editor')->to($user1);

        $warden->assign('admin')->to($user2);

        $warden->assign('admin')->to($user3);
        $warden->assign('editor')->to($user3);
        $warden->assign('manager')->to($user3);

        // Act
        $query = User::query();
        $this->rolesQuery->constrainWhereIsAll($query, 'admin', 'editor');
        $users = $query->get();

        // Assert
        // Note: constrainWhereIsAll uses '=' count operator, so it requires EXACTLY 2 matching roles
        // user1 has exactly admin and editor (2 matches)
        // user3 also has admin and editor (2 matches), plus manager (but that doesn't affect the whereIn count)
        $this->assertCount(2, $users);
        $this->assertTrue($users->contains($user1));
        $this->assertFalse($users->contains($user2)); // Only has admin (1 match)
        $this->assertTrue($users->contains($user3)); // Has admin and editor (2 matches)
    }

    #[Test()]
    public function constrain_where_is_all_with_single_role(): void
    {
        // Arrange
        Role::query()->create(['name' => 'admin']);
        Role::query()->create(['name' => 'editor']);

        $user1 = User::query()->create(['name' => 'Admin Only']);
        $user2 = User::query()->create(['name' => 'Admin Editor']);

        $warden = $this->bouncer($user1);
        $warden->assign('admin')->to($user1);

        $warden->assign('admin')->to($user2);
        $warden->assign('editor')->to($user2);

        // Act
        $query = User::query();
        $this->rolesQuery->constrainWhereIsAll($query, 'admin');
        $users = $query->get();

        // Assert
        // Note: With single role, both users have at least 1 matching role
        $this->assertCount(2, $users);
        $this->assertTrue($users->contains($user1));
        $this->assertTrue($users->contains($user2));
    }

    #[Test()]
    public function constrain_where_is_all_returns_empty_when_no_match(): void
    {
        // Arrange
        Role::query()->create(['name' => 'admin']);
        Role::query()->create(['name' => 'editor']);

        $user1 = User::query()->create(['name' => 'Admin Only']);

        $warden = $this->bouncer($user1);
        $warden->assign('admin')->to($user1);

        // Act
        $query = User::query();
        $this->rolesQuery->constrainWhereIsAll($query, 'admin', 'editor');
        $users = $query->get();

        // Assert
        $this->assertCount(0, $users);
    }

    #[Test()]
    public function constrain_where_is_not_excludes_single_role(): void
    {
        // Arrange
        Role::query()->create(['name' => 'admin']);
        Role::query()->create(['name' => 'editor']);

        $user1 = User::query()->create(['name' => 'Admin User']);
        $user2 = User::query()->create(['name' => 'Editor User']);
        $user3 = User::query()->create(['name' => 'Regular User']);

        $warden = $this->bouncer($user1);
        $warden->assign('admin')->to($user1);
        $warden->assign('editor')->to($user2);

        // Act
        $query = User::query();
        $this->rolesQuery->constrainWhereIsNot($query, 'admin');
        $users = $query->get();

        // Assert
        $this->assertCount(2, $users);
        $this->assertFalse($users->contains($user1));
        $this->assertTrue($users->contains($user2));
        $this->assertTrue($users->contains($user3));
    }

    #[Test()]
    public function constrain_where_is_not_excludes_multiple_roles(): void
    {
        // Arrange
        Role::query()->create(['name' => 'admin']);
        Role::query()->create(['name' => 'editor']);
        Role::query()->create(['name' => 'subscriber']);

        $user1 = User::query()->create(['name' => 'Admin User']);
        $user2 = User::query()->create(['name' => 'Editor User']);
        $user3 = User::query()->create(['name' => 'Subscriber User']);
        $user4 = User::query()->create(['name' => 'Regular User']);

        $warden = $this->bouncer($user1);
        $warden->assign('admin')->to($user1);
        $warden->assign('editor')->to($user2);
        $warden->assign('subscriber')->to($user3);

        // Act
        $query = User::query();
        $this->rolesQuery->constrainWhereIsNot($query, 'admin', 'editor');
        $users = $query->get();

        // Assert
        $this->assertCount(2, $users);
        $this->assertFalse($users->contains($user1));
        $this->assertFalse($users->contains($user2));
        $this->assertTrue($users->contains($user3));
        $this->assertTrue($users->contains($user4));
    }

    #[Test()]
    public function constrain_where_is_not_returns_all_when_none_have_role(): void
    {
        // Arrange
        Role::query()->create(['name' => 'admin']);
        $user1 = User::query()->create(['name' => 'User 1']);
        $user2 = User::query()->create(['name' => 'User 2']);

        // Act
        $query = User::query();
        $this->rolesQuery->constrainWhereIsNot($query, 'admin');
        $users = $query->get();

        // Assert
        $this->assertCount(2, $users);
        $this->assertTrue($users->contains($user1));
        $this->assertTrue($users->contains($user2));
    }

    #[Test()]
    public function constrain_where_assigned_to_filters_by_single_model_instance(): void
    {
        // Arrange
        Role::query()->create(['name' => 'admin']);
        Role::query()->create(['name' => 'editor']);
        Role::query()->create(['name' => 'subscriber']);

        $user1 = User::query()->create(['name' => 'User 1']);
        $user2 = User::query()->create(['name' => 'User 2']);

        $warden = $this->bouncer($user1);
        $warden->assign('admin')->to($user1);
        $warden->assign('editor')->to($user1);
        $warden->assign('subscriber')->to($user2);

        // Act
        $query = Role::query();
        $this->rolesQuery->constrainWhereAssignedTo($query, $user1);
        $roles = $query->get();

        // Assert
        $this->assertCount(2, $roles);
        $this->assertTrue($roles->contains('name', 'admin'));
        $this->assertTrue($roles->contains('name', 'editor'));
        $this->assertFalse($roles->contains('name', 'subscriber'));
    }

    #[Test()]
    public function constrain_where_assigned_to_filters_by_collection_of_models(): void
    {
        // Arrange
        Role::query()->create(['name' => 'admin']);
        Role::query()->create(['name' => 'editor']);
        Role::query()->create(['name' => 'subscriber']);
        Role::query()->create(['name' => 'manager']);

        $user1 = User::query()->create(['name' => 'User 1']);
        $user2 = User::query()->create(['name' => 'User 2']);
        User::query()->create(['name' => 'User 3']);

        $warden = $this->bouncer($user1);
        $warden->assign('admin')->to($user1);
        $warden->assign('editor')->to($user1);
        $warden->assign('subscriber')->to($user2);
        $warden->assign('editor')->to($user2);
        // user3 has no roles, manager has no users

        // Act
        $users = new Collection([$user1, $user2]);
        $query = Role::query();
        $this->rolesQuery->constrainWhereAssignedTo($query, $users);
        $roles = $query->get();

        // Assert
        $this->assertCount(3, $roles);
        $this->assertTrue($roles->contains('name', 'admin'));
        $this->assertTrue($roles->contains('name', 'editor'));
        $this->assertTrue($roles->contains('name', 'subscriber'));
        $this->assertFalse($roles->contains('name', 'manager'));
    }

    #[Test()]
    public function constrain_where_assigned_to_filters_by_class_name_and_keys(): void
    {
        // Arrange
        Role::query()->create(['name' => 'admin']);
        Role::query()->create(['name' => 'editor']);
        Role::query()->create(['name' => 'subscriber']);

        $user1 = User::query()->create(['name' => 'User 1']);
        $user2 = User::query()->create(['name' => 'User 2']);
        $user3 = User::query()->create(['name' => 'User 3']);

        $warden = $this->bouncer($user1);
        $warden->assign('admin')->to($user1);
        $warden->assign('editor')->to($user2);
        $warden->assign('subscriber')->to($user3);

        // Act
        $query = Role::query();
        $this->rolesQuery->constrainWhereAssignedTo($query, User::class, [$user1->id, $user2->id]);
        $roles = $query->get();

        // Assert
        $this->assertCount(2, $roles);
        $this->assertTrue($roles->contains('name', 'admin'));
        $this->assertTrue($roles->contains('name', 'editor'));
        $this->assertFalse($roles->contains('name', 'subscriber'));
    }

    #[Test()]
    public function constrain_where_assigned_to_returns_empty_for_model_with_no_roles(): void
    {
        // Arrange
        Role::query()->create(['name' => 'admin']);
        $user1 = User::query()->create(['name' => 'User 1']);

        // Act
        $query = Role::query();
        $this->rolesQuery->constrainWhereAssignedTo($query, $user1);
        $roles = $query->get();

        // Assert
        $this->assertCount(0, $roles);
    }

    #[Test()]
    public function constrain_where_assigned_to_handles_collection_with_unassigned_users(): void
    {
        // Arrange
        Role::query()->create(['name' => 'admin']);
        Role::query()->create(['name' => 'editor']);

        $user1 = User::query()->create(['name' => 'User 1']);
        $user2 = User::query()->create(['name' => 'User 2']);

        $warden = $this->bouncer($user1);
        $warden->assign('admin')->to($user1);
        // user2 has no roles

        // Act
        $users = new Collection([$user1, $user2]);
        $query = Role::query();
        $this->rolesQuery->constrainWhereAssignedTo($query, $users);
        $roles = $query->get();

        // Assert
        $this->assertCount(1, $roles);
        $this->assertTrue($roles->contains('name', 'admin'));
        $this->assertFalse($roles->contains('name', 'editor'));
    }

    #[Test()]
    public function constrain_where_assigned_to_returns_empty_for_empty_keys_array(): void
    {
        // Arrange
        Role::query()->create(['name' => 'admin']);

        // Act
        $query = Role::query();
        $this->rolesQuery->constrainWhereAssignedTo($query, User::class, []);
        $roles = $query->get();

        // Assert
        $this->assertCount(0, $roles);
    }

    #[Test()]
    public function constrain_where_is_generates_correct_sql_structure(): void
    {
        // Arrange
        Role::query()->create(['name' => 'admin']);
        Role::query()->create(['name' => 'editor']);

        // Act
        $query = User::query();
        $this->rolesQuery->constrainWhereIs($query, 'admin', 'editor');

        // Assert
        $sql = $query->toSql();
        $this->assertStringContainsString('exists', $sql);
        $this->assertStringContainsString('roles', $sql);
    }

    #[Test()]
    public function constrain_where_is_all_generates_correct_sql_with_count(): void
    {
        // Arrange
        Role::query()->create(['name' => 'admin']);
        Role::query()->create(['name' => 'editor']);

        // Act
        $query = User::query();
        $this->rolesQuery->constrainWhereIsAll($query, 'admin', 'editor');

        // Assert
        $sql = $query->toSql();
        $this->assertStringContainsString('count', $sql);
        $this->assertStringContainsString('roles', $sql);
        $this->assertStringContainsString('= 2', $sql); // Exact count match
    }

    #[Test()]
    public function constrain_where_is_not_generates_correct_sql_structure(): void
    {
        // Arrange
        Role::query()->create(['name' => 'admin']);

        // Act
        $query = User::query();
        $this->rolesQuery->constrainWhereIsNot($query, 'admin');

        // Assert
        $sql = $query->toSql();
        $this->assertStringContainsString('not exists', $sql);
        $this->assertStringContainsString('roles', $sql);
    }

    #[Test()]
    public function constrain_where_assigned_to_generates_correct_sql_with_joins(): void
    {
        // Arrange
        Role::query()->create(['name' => 'admin']);
        $user = User::query()->create(['name' => 'User']);

        // Act
        $query = Role::query();
        $this->rolesQuery->constrainWhereAssignedTo($query, $user);

        // Assert
        $sql = $query->toSql();
        $this->assertStringContainsString('exists', $sql);
        $this->assertStringContainsString('assigned_roles', $sql);
    }

    #[Test()]
    public function multiple_constraints_can_be_chained(): void
    {
        // Arrange
        Role::query()->create(['name' => 'admin']);
        Role::query()->create(['name' => 'editor']);
        Role::query()->create(['name' => 'subscriber']);

        $user1 = User::query()->create(['name' => 'Admin User']);
        $user2 = User::query()->create(['name' => 'Editor Subscriber']);
        $user3 = User::query()->create(['name' => 'Subscriber Only']);

        $warden = $this->bouncer($user1);
        $warden->assign('admin')->to($user1);
        $warden->assign('editor')->to($user2);
        $warden->assign('subscriber')->to($user2);
        $warden->assign('subscriber')->to($user3);

        // Act - Find users who have admin OR (editor AND subscriber)
        $query = User::query();
        $this->rolesQuery->constrainWhereIs($query, 'admin');
        $adminUsers = $query->get();

        $query2 = User::query();
        $this->rolesQuery->constrainWhereIsAll($query2, 'editor', 'subscriber');
        $editorSubscriberUsers = $query2->get();

        // Assert
        $this->assertCount(1, $adminUsers);
        $this->assertTrue($adminUsers->contains($user1));

        $this->assertCount(1, $editorSubscriberUsers);
        $this->assertTrue($editorSubscriberUsers->contains($user2));
    }
}
