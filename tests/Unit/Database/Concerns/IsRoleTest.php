<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Database\Concerns;

use Cline\Warden\Database\Ability;
use Cline\Warden\Database\Models;
use Cline\Warden\Database\Role;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Fixtures\Models\User;
use Tests\TestCase;

use function config;

/**
 * @internal
 *
 * @author Brian Faust <brian@cline.sh>
 */
#[Small()]
final class IsRoleTest extends TestCase
{
    #[Test()]
    #[TestDox('Auto-generates title when creating role without title')]
    #[Group('happy-path')]
    public function auto_generates_title_when_creating_role(): void
    {
        // Arrange
        $roleName = 'super_admin';

        // Act
        $role = Role::query()->create(['name' => $roleName]);

        // Assert
        $this->assertNotNull($role->title);
        $this->assertEquals('Super admin', $role->title);
    }

    #[Test()]
    #[TestDox('Preserves explicit title when provided')]
    #[Group('edge-case')]
    public function preserves_explicit_title_when_provided(): void
    {
        // Arrange
        $roleName = 'admin';
        $explicitTitle = 'Custom Admin Title';

        // Act
        $role = Role::query()->create(['name' => $roleName, 'title' => $explicitTitle]);

        // Assert
        $this->assertEquals($explicitTitle, $role->title);
    }

    #[Test()]
    #[TestDox('Detaches abilities when role is deleted')]
    #[Group('happy-path')]
    public function detaches_abilities_when_role_deleted(): void
    {
        // Arrange
        $role = Role::query()->create(['name' => 'admin']);
        $ability = Ability::query()->create(['name' => 'edit-posts']);
        $role->abilities()->attach($ability);

        // Act
        $role->delete();

        // Assert
        $this->assertDatabaseMissing(Models::table('permissions'), [
            'actor_type' => $role->getMorphClass(),
            'actor_id' => $role->id,
        ]);
    }

    #[Test()]
    #[TestDox('Users relationship includes assigned users')]
    #[Group('happy-path')]
    public function users_relationship_includes_assigned_users(): void
    {
        // Arrange
        config()->set('warden.user_model', User::class);
        $role = Role::query()->create(['name' => 'admin']);
        $user1 = User::query()->create(['name' => 'Alice']);
        $user2 = User::query()->create(['name' => 'Bob']);
        $role->assignTo($user1);

        // Act
        $users = $role->users()->get();

        // Assert
        $this->assertCount(1, $users);
        $this->assertTrue($users->contains('id', $user1->id));
        $this->assertFalse($users->contains('id', $user2->id));
    }

    #[Test()]
    #[TestDox('Assigns role to single user instance')]
    #[Group('happy-path')]
    public function assigns_role_to_single_user_instance(): void
    {
        // Arrange
        $role = Role::query()->create(['name' => 'admin']);
        $user = User::query()->create(['name' => 'John Doe']);

        // Act
        $result = $role->assignTo($user);

        // Assert
        $this->assertSame($role, $result);
        $this->assertDatabaseHas(Models::table('assigned_roles'), [
            'role_id' => $role->id,
            'actor_type' => $user->getMorphClass(),
            'actor_id' => $user->id,
        ]);
    }

    #[Test()]
    #[TestDox('Assigns role to multiple users via class and keys')]
    #[Group('happy-path')]
    public function assigns_role_to_multiple_users_via_class_and_keys(): void
    {
        // Arrange
        $role = Role::query()->create(['name' => 'editor']);
        $user1 = User::query()->create(['name' => 'Alice']);
        $user2 = User::query()->create(['name' => 'Bob']);

        // Act
        $role->assignTo(User::class, [$user1->id, $user2->id]);

        // Assert
        $this->assertDatabaseHas(Models::table('assigned_roles'), [
            'role_id' => $role->id,
            'actor_id' => $user1->id,
        ]);
        $this->assertDatabaseHas(Models::table('assigned_roles'), [
            'role_id' => $role->id,
            'actor_id' => $user2->id,
        ]);
    }

    #[Test()]
    #[TestDox('Assigns role to class name with array of IDs')]
    #[Group('happy-path')]
    public function assigns_role_to_class_name_with_array_of_ids(): void
    {
        // Arrange
        $role = Role::query()->create(['name' => 'moderator']);
        $user1 = User::query()->create(['name' => 'Charlie']);
        $user2 = User::query()->create(['name' => 'Diana']);

        // Act
        $role->assignTo(User::class, [$user1->id, $user2->id]);

        // Assert
        $this->assertDatabaseHas(Models::table('assigned_roles'), [
            'role_id' => $role->id,
            'actor_id' => $user1->id,
        ]);
        $this->assertDatabaseHas(Models::table('assigned_roles'), [
            'role_id' => $role->id,
            'actor_id' => $user2->id,
        ]);
    }

    #[Test()]
    #[TestDox('Finds existing roles by ID')]
    #[Group('happy-path')]
    public function finds_existing_roles_by_ids(): void
    {
        // Arrange
        $role1 = Role::query()->create(['name' => 'admin']);
        $role2 = Role::query()->create(['name' => 'editor']);
        $roleInstance = new Role();

        // Act
        $roles = $roleInstance->findOrCreateRoles([$role1->id, $role2->id]);

        // Assert
        $this->assertCount(2, $roles);
        $this->assertTrue($roles->contains('id', $role1->id));
        $this->assertTrue($roles->contains('id', $role2->id));
    }

    #[Test()]
    #[TestDox('Finds existing roles by name')]
    #[Group('happy-path')]
    public function finds_existing_roles_by_name(): void
    {
        // Arrange
        Role::query()->create(['name' => 'viewer']);
        Role::query()->create(['name' => 'contributor']);
        $roleInstance = new Role();

        // Act
        $roles = $roleInstance->findOrCreateRoles(['viewer', 'contributor']);

        // Assert
        $this->assertCount(2, $roles);
        $this->assertTrue($roles->contains('name', 'viewer'));
        $this->assertTrue($roles->contains('name', 'contributor'));
    }

    #[Test()]
    #[TestDox('Creates missing roles by name')]
    #[Group('happy-path')]
    public function creates_missing_roles_by_name(): void
    {
        // Arrange
        Role::query()->create(['name' => 'existing-role']);
        $roleInstance = new Role();

        // Act
        $roles = $roleInstance->findOrCreateRoles(['existing-role', 'new-role']);

        // Assert
        $this->assertCount(2, $roles);
        $this->assertTrue($roles->contains('name', 'existing-role'));
        $this->assertTrue($roles->contains('name', 'new-role'));
        $this->assertDatabaseHas(Models::table('roles'), ['name' => 'new-role']);
    }

    #[Test()]
    #[TestDox('Returns collection of found and created roles')]
    #[Group('happy-path')]
    public function returns_collection_of_found_and_created_roles(): void
    {
        // Arrange
        $existingRole = Role::query()->create(['name' => 'admin']);
        $roleInstance = new Role();

        // Act
        $roles = $roleInstance->findOrCreateRoles([
            $existingRole->id,
            'new-role-1',
            'new-role-2',
        ]);

        // Assert
        $this->assertCount(3, $roles);
        $this->assertInstanceOf(Collection::class, $roles);
    }

    #[Test()]
    #[TestDox('Extracts keys from role IDs')]
    #[Group('happy-path')]
    public function extracts_keys_from_role_ids(): void
    {
        // Arrange
        $role1 = Role::query()->create(['name' => 'role1']);
        $role2 = Role::query()->create(['name' => 'role2']);
        $roleInstance = new Role();

        // Act
        $keys = $roleInstance->getRoleKeys([$role1->id, $role2->id]);

        // Assert
        $this->assertCount(2, $keys);
        $this->assertContains($role1->id, $keys);
        $this->assertContains($role2->id, $keys);
    }

    #[Test()]
    #[TestDox('Extracts keys from role names')]
    #[Group('happy-path')]
    public function extracts_keys_from_role_names(): void
    {
        // Arrange
        $role1 = Role::query()->create(['name' => 'manager']);
        $role2 = Role::query()->create(['name' => 'supervisor']);
        $roleInstance = new Role();

        // Act
        $keys = $roleInstance->getRoleKeys(['manager', 'supervisor']);

        // Assert
        $this->assertCount(2, $keys);
        $this->assertContains($role1->id, $keys);
        $this->assertContains($role2->id, $keys);
    }

    #[Test()]
    #[TestDox('Extracts keys from role instances')]
    #[Group('happy-path')]
    public function extracts_keys_from_role_instances(): void
    {
        // Arrange
        $role1 = Role::query()->create(['name' => 'analyst']);
        $role2 = Role::query()->create(['name' => 'developer']);
        $roleInstance = new Role();

        // Act
        $keys = $roleInstance->getRoleKeys([$role1, $role2]);

        // Assert
        $this->assertCount(2, $keys);
        $this->assertContains($role1->id, $keys);
        $this->assertContains($role2->id, $keys);
    }

    #[Test()]
    #[TestDox('Handles mixed identifier types for keys extraction')]
    #[Group('edge-case')]
    public function handles_mixed_identifier_types_for_keys_extraction(): void
    {
        // Arrange
        $role1 = Role::query()->create(['name' => 'tester']);
        $role2 = Role::query()->create(['name' => 'architect']);
        $role3 = Role::query()->create(['name' => 'consultant']);
        $roleInstance = new Role();

        // Act
        $keys = $roleInstance->getRoleKeys([$role1->id, 'architect', $role3]);

        // Assert
        $this->assertCount(3, $keys);
        $this->assertContains($role1->id, $keys);
        $this->assertContains($role2->id, $keys);
        $this->assertContains($role3->id, $keys);
    }

    #[Test()]
    #[TestDox('Extracts names from role IDs')]
    #[Group('happy-path')]
    public function extracts_names_from_role_ids(): void
    {
        // Arrange
        $role1 = Role::query()->create(['name' => 'publisher']);
        $role2 = Role::query()->create(['name' => 'subscriber']);
        $roleInstance = new Role();

        // Act
        $names = $roleInstance->getRoleNames([$role1->id, $role2->id]);

        // Assert
        $this->assertCount(2, $names);
        $this->assertContains('publisher', $names);
        $this->assertContains('subscriber', $names);
    }

    #[Test()]
    #[TestDox('Extracts names from role names (passthrough)')]
    #[Group('happy-path')]
    public function extracts_names_from_role_names(): void
    {
        // Arrange
        Role::query()->create(['name' => 'guest']);
        Role::query()->create(['name' => 'member']);
        $roleInstance = new Role();

        // Act
        $names = $roleInstance->getRoleNames(['guest', 'member']);

        // Assert
        $this->assertCount(2, $names);
        $this->assertContains('guest', $names);
        $this->assertContains('member', $names);
    }

    #[Test()]
    #[TestDox('Extracts names from role instances')]
    #[Group('happy-path')]
    public function extracts_names_from_role_instances(): void
    {
        // Arrange
        $role1 = Role::query()->create(['name' => 'coordinator']);
        $role2 = Role::query()->create(['name' => 'facilitator']);
        $roleInstance = new Role();

        // Act
        $names = $roleInstance->getRoleNames([$role1, $role2]);

        // Assert
        $this->assertCount(2, $names);
        $this->assertContains('coordinator', $names);
        $this->assertContains('facilitator', $names);
    }

    #[Test()]
    #[TestDox('Handles mixed identifier types for names extraction')]
    #[Group('edge-case')]
    public function handles_mixed_identifier_types_for_names_extraction(): void
    {
        // Arrange
        $role1 = Role::query()->create(['name' => 'auditor']);
        Role::query()->create(['name' => 'reviewer']);
        $role3 = Role::query()->create(['name' => 'approver']);
        $roleInstance = new Role();

        // Act
        $names = $roleInstance->getRoleNames([$role1->id, 'reviewer', $role3]);

        // Assert
        $this->assertCount(3, $names);
        $this->assertContains('auditor', $names);
        $this->assertContains('reviewer', $names);
        $this->assertContains('approver', $names);
    }

    #[Test()]
    #[TestDox('Queries role IDs by their names')]
    #[Group('happy-path')]
    public function queries_role_ids_by_names(): void
    {
        // Arrange
        $role1 = Role::query()->create(['name' => 'operator']);
        $role2 = Role::query()->create(['name' => 'technician']);
        $roleInstance = new Role();

        // Act
        $keys = $roleInstance->getKeysByName(['operator', 'technician']);

        // Assert
        $this->assertCount(2, $keys);
        $this->assertContains($role1->id, $keys);
        $this->assertContains($role2->id, $keys);
    }

    #[Test()]
    #[TestDox('Returns empty array when getKeysByName receives empty input')]
    #[Group('sad-path')]
    public function returns_empty_array_when_get_keys_by_name_receives_empty_input(): void
    {
        // Arrange
        $roleInstance = new Role();

        // Act
        $keys = $roleInstance->getKeysByName([]);

        // Assert
        $this->assertEmpty($keys);
        $this->assertIsArray($keys);
    }

    #[Test()]
    #[TestDox('Queries role names by their IDs')]
    #[Group('happy-path')]
    public function queries_role_names_by_ids(): void
    {
        // Arrange
        $role1 = Role::query()->create(['name' => 'specialist']);
        $role2 = Role::query()->create(['name' => 'expert']);
        $roleInstance = new Role();

        // Act
        $names = $roleInstance->getNamesByKey([$role1->id, $role2->id]);

        // Assert
        $this->assertCount(2, $names);
        $this->assertContains('specialist', $names);
        $this->assertContains('expert', $names);
    }

    #[Test()]
    #[TestDox('Returns empty array when getNamesByKey receives empty input')]
    #[Group('sad-path')]
    public function returns_empty_array_when_get_names_by_key_receives_empty_input(): void
    {
        // Arrange
        $roleInstance = new Role();

        // Act
        $names = $roleInstance->getNamesByKey([]);

        // Assert
        $this->assertEmpty($names);
        $this->assertIsArray($names);
    }

    #[Test()]
    #[TestDox('Removes role from single user instance')]
    #[Group('happy-path')]
    public function removes_role_from_single_user_instance(): void
    {
        // Arrange
        $role = Role::query()->create(['name' => 'admin']);
        $user = User::query()->create(['name' => 'Jane Smith']);
        $role->assignTo($user);

        // Act
        $result = $role->retractFrom($user);

        // Assert
        $this->assertSame($role, $result);
        $this->assertDatabaseMissing(Models::table('assigned_roles'), [
            'role_id' => $role->id,
            'actor_id' => $user->id,
        ]);
    }

    #[Test()]
    #[TestDox('Removes role from multiple users via class and keys')]
    #[Group('happy-path')]
    public function removes_role_from_multiple_users_via_class_and_keys(): void
    {
        // Arrange
        $role = Role::query()->create(['name' => 'editor']);
        $user1 = User::query()->create(['name' => 'Emma']);
        $user2 = User::query()->create(['name' => 'Frank']);
        $role->assignTo(User::class, [$user1->id, $user2->id]);

        // Act
        $role->retractFrom(User::class, [$user1->id, $user2->id]);

        // Assert
        $this->assertDatabaseMissing(Models::table('assigned_roles'), [
            'role_id' => $role->id,
            'actor_id' => $user1->id,
        ]);
        $this->assertDatabaseMissing(Models::table('assigned_roles'), [
            'role_id' => $role->id,
            'actor_id' => $user2->id,
        ]);
    }

    #[Test()]
    #[TestDox('Removes role from class name with array of IDs')]
    #[Group('happy-path')]
    public function removes_role_from_class_name_with_array_of_ids(): void
    {
        // Arrange
        $role = Role::query()->create(['name' => 'moderator']);
        $user1 = User::query()->create(['name' => 'George']);
        $user2 = User::query()->create(['name' => 'Hannah']);
        $role->assignTo(User::class, [$user1->id, $user2->id]);

        // Act
        $role->retractFrom(User::class, [$user1->id, $user2->id]);

        // Assert
        $this->assertDatabaseMissing(Models::table('assigned_roles'), [
            'role_id' => $role->id,
            'actor_id' => $user1->id,
        ]);
        $this->assertDatabaseMissing(Models::table('assigned_roles'), [
            'role_id' => $role->id,
            'actor_id' => $user2->id,
        ]);
    }

    #[Test()]
    #[TestDox('Handles retraction from non-assigned users gracefully')]
    #[Group('edge-case')]
    public function handles_retraction_from_non_assigned_users_gracefully(): void
    {
        // Arrange
        $role = Role::query()->create(['name' => 'viewer']);
        $user = User::query()->create(['name' => 'Isaac']);

        // Act
        $result = $role->retractFrom($user);

        // Assert
        $this->assertSame($role, $result);
        $this->assertDatabaseMissing(Models::table('assigned_roles'), [
            'role_id' => $role->id,
            'actor_id' => $user->id,
        ]);
    }

    #[Test()]
    #[TestDox('Constrains roles query by assigned user')]
    #[Group('happy-path')]
    public function constrains_roles_query_by_assigned_user(): void
    {
        // Arrange
        $user = User::query()->create(['name' => 'Jack']);
        $role1 = Role::query()->create(['name' => 'admin']);
        $role2 = Role::query()->create(['name' => 'editor']);
        Role::query()->create(['name' => 'viewer']);
        $role1->assignTo($user);
        $role2->assignTo($user);

        // Act
        $roles = Role::whereAssignedTo($user)->get();

        // Assert
        $this->assertCount(2, $roles);
        $this->assertTrue($roles->contains('name', 'admin'));
        $this->assertTrue($roles->contains('name', 'editor'));
        $this->assertFalse($roles->contains('name', 'viewer'));
    }

    #[Test()]
    #[TestDox('Constrains roles query by class name and multiple IDs')]
    #[Group('happy-path')]
    public function constrains_roles_query_by_class_name_and_multiple_ids(): void
    {
        // Arrange
        $user1 = User::query()->create(['name' => 'Kate']);
        $user2 = User::query()->create(['name' => 'Liam']);
        $role1 = Role::query()->create(['name' => 'manager']);
        $role2 = Role::query()->create(['name' => 'staff']);
        Role::query()->create(['name' => 'guest']);
        $role1->assignTo($user1);
        $role2->assignTo($user2);

        // Act
        $roles = Role::whereAssignedTo(User::class, [$user1->id, $user2->id])->get();

        // Assert
        $this->assertCount(2, $roles);
        $this->assertTrue($roles->contains('name', 'manager'));
        $this->assertTrue($roles->contains('name', 'staff'));
        $this->assertFalse($roles->contains('name', 'guest'));
    }

    #[Test()]
    #[TestDox('Constrains roles query by class name and IDs')]
    #[Group('happy-path')]
    public function constrains_roles_query_by_class_name_and_ids(): void
    {
        // Arrange
        $user1 = User::query()->create(['name' => 'Mike']);
        $user2 = User::query()->create(['name' => 'Nancy']);
        $role1 = Role::query()->create(['name' => 'owner']);
        $role2 = Role::query()->create(['name' => 'collaborator']);
        Role::query()->create(['name' => 'reader']);
        $role1->assignTo($user1);
        $role2->assignTo($user2);

        // Act
        $roles = Role::whereAssignedTo(User::class, [$user1->id, $user2->id])->get();

        // Assert
        $this->assertCount(2, $roles);
        $this->assertTrue($roles->contains('name', 'owner'));
        $this->assertTrue($roles->contains('name', 'collaborator'));
        $this->assertFalse($roles->contains('name', 'reader'));
    }

    #[Test()]
    #[TestDox('Users relationship is properly configured')]
    #[Group('happy-path')]
    public function users_relationship_is_properly_configured(): void
    {
        // Arrange
        config()->set('warden.user_model', User::class);
        $role = Role::query()->create(['name' => 'admin']);
        $user = User::query()->create(['name' => 'Test User']);
        $role->assignTo($user);

        // Act
        $relation = $role->users();
        $users = $relation->get();

        // Assert
        $this->assertInstanceOf(MorphToMany::class, $relation);
        $this->assertCount(1, $users);
        $this->assertEquals($user->id, $users->first()->id);
    }

    #[Test()]
    #[TestDox('Assigns role to multiple users via array')]
    #[Group('happy-path')]
    public function assigns_role_to_multiple_users_via_array(): void
    {
        // Arrange
        $role = Role::query()->create(['name' => 'admin']);
        $user1 = User::query()->create(['name' => 'User 1']);
        $user2 = User::query()->create(['name' => 'User 2']);

        // Act
        $role->assignTo($user1->getMorphClass(), [$user1->id, $user2->id]);

        // Assert
        $this->assertDatabaseHas(Models::table('assigned_roles'), [
            'role_id' => $role->id,
            'actor_id' => $user1->id,
        ]);
        $this->assertDatabaseHas(Models::table('assigned_roles'), [
            'role_id' => $role->id,
            'actor_id' => $user2->id,
        ]);
    }

    #[Test()]
    #[TestDox('Retrieves role names from mixed identifiers')]
    #[Group('happy-path')]
    public function retrieves_role_names_from_mixed_identifiers(): void
    {
        // Arrange
        $role1 = Role::query()->create(['name' => 'manager']);
        $role2 = Role::query()->create(['name' => 'employee']);
        $roleInstance = new Role();

        // Act
        $names = $roleInstance->getRoleNames([$role1->id, $role2]);

        // Assert
        $this->assertCount(2, $names);
        $this->assertContains('manager', $names);
        $this->assertContains('employee', $names);
    }

    #[Test()]
    #[TestDox('Queries names by keys with empty array returns empty')]
    #[Group('edge-case')]
    public function queries_names_by_keys_with_empty_array(): void
    {
        // Arrange
        $roleInstance = new Role();

        // Act
        $names = $roleInstance->getNamesByKey([]);

        // Assert
        $this->assertEmpty($names);
    }

    #[Test()]
    #[TestDox('Retrieves role names from role IDs')]
    #[Group('happy-path')]
    public function retrieves_role_names_from_role_ids(): void
    {
        // Arrange
        $role1 = Role::query()->create(['name' => 'supervisor']);
        $role2 = Role::query()->create(['name' => 'worker']);
        $roleInstance = new Role();

        // Act
        $names = $roleInstance->getRoleNames([$role1->id, $role2->id]);

        // Assert
        $this->assertCount(2, $names);
        $this->assertContains('supervisor', $names);
        $this->assertContains('worker', $names);
    }

    #[Test()]
    #[TestDox('Retracts role from multiple users via array')]
    #[Group('happy-path')]
    public function retracts_role_from_multiple_users_via_array(): void
    {
        // Arrange
        $role = Role::query()->create(['name' => 'editor']);
        $user1 = User::query()->create(['name' => 'User 1']);
        $user2 = User::query()->create(['name' => 'User 2']);
        $role->assignTo($user1->getMorphClass(), [$user1->id, $user2->id]);

        // Act
        $role->retractFrom($user1->getMorphClass(), [$user1->id, $user2->id]);

        // Assert
        $this->assertDatabaseMissing(Models::table('assigned_roles'), [
            'role_id' => $role->id,
            'actor_id' => $user1->id,
        ]);
        $this->assertDatabaseMissing(Models::table('assigned_roles'), [
            'role_id' => $role->id,
            'actor_id' => $user2->id,
        ]);
    }

    #[Test()]
    #[TestDox('Creates assign records with scope attributes')]
    #[Group('happy-path')]
    public function creates_assign_records_with_scope_attributes(): void
    {
        // Arrange
        Models::scope()->to(42);
        $role = Role::query()->create(['name' => 'admin']);
        $user1 = User::query()->create(['name' => 'User 1']);
        $user2 = User::query()->create(['name' => 'User 2']);

        // Act
        $role->assignTo(User::class, [$user1->id, $user2->id]);

        // Assert
        $this->assertDatabaseHas(Models::table('assigned_roles'), [
            'role_id' => $role->id,
            'actor_id' => $user1->id,
            'scope' => 42,
        ]);
        $this->assertDatabaseHas(Models::table('assigned_roles'), [
            'role_id' => $role->id,
            'actor_id' => $user2->id,
            'scope' => 42,
        ]);

        // Cleanup
        Models::scope()->to(null);
    }
}
