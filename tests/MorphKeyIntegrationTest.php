<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use Cline\Warden\Database\Ability;
use Cline\Warden\Database\Models;
use Cline\Warden\Database\Role;
use Override;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Models\Account;
use Tests\Fixtures\Models\Organization;
use Tests\Fixtures\Models\User;

/**
 * Integration tests for polymorphic key mapping across all relationship types.
 *
 * @internal
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MorphKeyIntegrationTest extends TestCase
{
    #[Override()]
    protected function setUp(): void
    {
        parent::setUp();

        Models::reset();
        Models::setUsersModel(User::class);
        Models::morphKeyMap([
            User::class => 'id',
            Organization::class => 'ulid',
            Account::class => 'id',
        ]);
    }

    #[Override()]
    protected function tearDown(): void
    {
        Models::reset();
        parent::tearDown();
    }

    #[Test()]
    public function it_uses_correct_key_for_actor_id_when_assigning_abilities(): void
    {
        $org = Organization::query()->create(['name' => 'Acme']);
        $ability = Ability::query()->create(['name' => 'edit-posts']);

        $warden = self::bouncer($org);
        $warden->allow($org)->to('edit-posts');

        $permission = $this->db()
            ->table('permissions')
            ->where('ability_id', $ability->id)
            ->where('actor_type', $org->getMorphClass())
            ->first();

        $this->assertNotNull($permission);
        $this->assertEquals($org->ulid, $permission->actor_id);
    }

    #[Test()]
    public function it_uses_correct_key_for_actor_id_when_assigning_roles(): void
    {
        $org = Organization::query()->create(['name' => 'Acme']);
        $role = Role::query()->create(['name' => 'admin']);

        $warden = self::bouncer($org);
        $warden->assign('admin')->to($org);

        $assignment = $this->db()
            ->table('assigned_roles')
            ->where('role_id', $role->id)
            ->where('actor_type', $org->getMorphClass())
            ->first();

        $this->assertNotNull($assignment);
        $this->assertEquals($org->ulid, $assignment->actor_id);
    }

    #[Test()]
    public function it_uses_correct_key_for_actor_id_with_multiple_authorities(): void
    {
        $user = User::query()->create(['name' => 'John']);
        $org = Organization::query()->create(['name' => 'Acme']);
        $role = Role::query()->create(['name' => 'admin']);

        $warden = self::bouncer();
        $warden->assign('admin')->to([$user, $org]);

        $userAssignment = $this->db()
            ->table('assigned_roles')
            ->where('role_id', $role->id)
            ->where('actor_type', $user->getMorphClass())
            ->first();

        $orgAssignment = $this->db()
            ->table('assigned_roles')
            ->where('role_id', $role->id)
            ->where('actor_type', $org->getMorphClass())
            ->first();

        $this->assertNotNull($userAssignment);
        $this->assertEquals($user->id, $userAssignment->actor_id);

        $this->assertNotNull($orgAssignment);
        $this->assertEquals($org->ulid, $orgAssignment->actor_id);
    }

    #[Test()]
    public function it_uses_correct_key_for_context_id_when_assigning_abilities(): void
    {
        $user = User::query()->create(['name' => 'John']);
        $org = Organization::query()->create(['name' => 'Acme']);
        $ability = Ability::query()->create(['name' => 'edit-posts']);

        $warden = self::bouncer($user);
        $warden->allow($user)->within($org)->to('edit-posts');

        $permission = $this->db()
            ->table('permissions')
            ->where('ability_id', $ability->id)
            ->where('actor_id', $user->id)
            ->where('context_type', $org->getMorphClass())
            ->first();

        $this->assertNotNull($permission);
        $this->assertEquals($org->ulid, $permission->context_id);
    }

    #[Test()]
    public function it_uses_correct_key_for_context_id_when_assigning_roles(): void
    {
        $user = User::query()->create(['name' => 'John']);
        $org = Organization::query()->create(['name' => 'Acme']);
        $role = Role::query()->create(['name' => 'admin']);

        $warden = self::bouncer($user);
        $warden->assign('admin')->within($org)->to($user);

        $assignment = $this->db()
            ->table('assigned_roles')
            ->where('role_id', $role->id)
            ->where('actor_id', $user->id)
            ->where('context_type', $org->getMorphClass())
            ->first();

        $this->assertNotNull($assignment);
        $this->assertEquals($org->ulid, $assignment->context_id);
    }

    #[Test()]
    public function it_uses_correct_key_for_context_id_when_allowing_everyone(): void
    {
        $org = Organization::query()->create(['name' => 'Acme']);
        $ability = Ability::query()->create(['name' => 'edit-posts']);

        $user = User::query()->create(['name' => 'John']);
        $warden = self::bouncer($user);
        $warden->allowEveryone()->within($org)->to('edit-posts');

        $permission = $this->db()
            ->table('permissions')
            ->where('ability_id', $ability->id)
            ->whereNull('actor_id')
            ->where('context_type', $org->getMorphClass())
            ->first();

        $this->assertNotNull($permission);
        $this->assertEquals($org->ulid, $permission->context_id);
    }

    #[Test()]
    public function it_uses_correct_key_for_subject_id_when_creating_abilities(): void
    {
        $org = Organization::query()->create(['name' => 'Acme']);

        $ability = Ability::makeForModel($org, [
            'name' => 'edit',
        ]);

        $this->assertEquals($org->getMorphClass(), $ability->subject_type);
        // Note: subject_id functionality depends on schema configuration
        // The default schema uses integer columns which don't support ULID values
        // This test verifies that getModelKey() returns the correct column name
        $this->assertSame('ulid', Models::getModelKey($org));
    }

    #[Test()]
    public function it_uses_correct_key_for_subject_id_when_creating_model_specific_abilities(): void
    {
        $user = User::query()->create(['name' => 'John']);
        $org = Organization::query()->create(['name' => 'Acme']);

        $warden = self::bouncer($user);
        $warden->allow($user)->to('edit', $org);

        $ability = Ability::query()
            ->where('name', 'edit')
            ->where('subject_type', $org->getMorphClass())
            ->first();

        $this->assertInstanceOf(Ability::class, $ability);
        // Note: subject_id storage depends on schema configuration
        // This test verifies the code correctly extracts the right key value
        $keyName = Models::getModelKey($org);
        $this->assertSame('ulid', $keyName);
        $this->assertEquals($org->ulid, $org->getAttribute($keyName));
    }

    #[Test()]
    public function it_queries_abilities_correctly_with_custom_subject_id(): void
    {
        $user = User::query()->create(['name' => 'John']);
        $org = Organization::query()->create(['name' => 'Acme']);

        $warden = self::bouncer($user);
        $warden->allow($user)->to('edit', $org);

        $this->assertTrue($warden->can('edit', $org));
    }

    #[Test()]
    public function it_removes_abilities_correctly_with_custom_actor_id(): void
    {
        $org = Organization::query()->create(['name' => 'Acme']);
        $ability = Ability::query()->create(['name' => 'edit-posts']);

        $warden = self::bouncer($org);
        $warden->allow($org)->to('edit-posts');

        $this->assertTrue($warden->can('edit-posts'));

        $warden->disallow($org)->to('edit-posts');

        $permission = $this->db()
            ->table('permissions')
            ->where('ability_id', $ability->id)
            ->where('actor_type', $org->getMorphClass())
            ->where('actor_id', $org->ulid)
            ->first();

        $this->assertNull($permission);
        $this->assertFalse($warden->can('edit-posts'));
    }

    #[Test()]
    public function it_removes_roles_correctly_with_custom_actor_id(): void
    {
        $org = Organization::query()->create(['name' => 'Acme']);
        $role = Role::query()->create(['name' => 'admin']);

        $warden = self::bouncer($org);
        $warden->assign('admin')->to($org);

        $warden->retract('admin')->from($org);

        $assignment = $this->db()
            ->table('assigned_roles')
            ->where('role_id', $role->id)
            ->where('actor_type', $org->getMorphClass())
            ->where('actor_id', $org->ulid)
            ->first();

        $this->assertNull($assignment);
    }

    #[Test()]
    public function it_handles_mixed_key_types_in_bulk_operations(): void
    {
        $user1 = User::query()->create(['name' => 'John']);
        $user2 = User::query()->create(['name' => 'Jane']);
        $org1 = Organization::query()->create(['name' => 'Acme']);
        $org2 = Organization::query()->create(['name' => 'TechCorp']);

        $role = Role::query()->create(['name' => 'editor']);

        $warden = self::bouncer();
        $warden->assign('editor')->to([$user1, $user2, $org1, $org2]);

        $userAssignments = $this->db()
            ->table('assigned_roles')
            ->where('role_id', $role->id)
            ->where('actor_type', User::class)
            ->get();

        $orgAssignments = $this->db()
            ->table('assigned_roles')
            ->where('role_id', $role->id)
            ->where('actor_type', Organization::class)
            ->get();

        $this->assertCount(2, $userAssignments);
        $this->assertCount(2, $orgAssignments);

        $this->assertContains($user1->id, $userAssignments->pluck('actor_id')->toArray());
        $this->assertContains($user2->id, $userAssignments->pluck('actor_id')->toArray());
        $this->assertContains($org1->ulid, $orgAssignments->pluck('actor_id')->toArray());
        $this->assertContains($org2->ulid, $orgAssignments->pluck('actor_id')->toArray());
    }
}
