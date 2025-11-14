<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Database;

use Cline\Warden\Database\Ability;
use Cline\Warden\Database\Models;
use Cline\Warden\Database\Role;
use Cline\Warden\Exceptions\MorphKeyViolationException;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Fixtures\Models\Account;
use Tests\Fixtures\Models\User;
use Tests\TestCase;

/**
 * @internal
 *
 * @author Brian Faust <brian@cline.sh>
 */
#[Small()]
final class ModelsTest extends TestCase
{
    #[Override()]
    protected function setUp(): void
    {
        parent::setUp();
        Models::reset();
    }

    #[Override()]
    protected function tearDown(): void
    {
        Models::reset();
        parent::tearDown();
    }

    #[Test()]
    #[TestDox('Sets custom abilities model')]
    #[Group('happy-path')]
    public function sets_custom_abilities_model(): void
    {
        // Arrange
        $customModel = Ability::class;

        // Act
        Models::setAbilitiesModel($customModel);
        $result = Models::classname(Ability::class);

        // Assert
        $this->assertSame($customModel, $result);
    }

    #[Test()]
    #[TestDox('Sets custom roles model')]
    #[Group('happy-path')]
    public function sets_custom_roles_model(): void
    {
        // Arrange
        $customModel = Role::class;

        // Act
        Models::setRolesModel($customModel);
        $result = Models::classname(Role::class);

        // Assert
        $this->assertSame($customModel, $result);
    }

    #[Test()]
    #[TestDox('Sets custom table names')]
    #[Group('happy-path')]
    public function sets_custom_table_names(): void
    {
        // Arrange
        $customTables = [
            'abilities' => 'custom_abilities',
            'roles' => 'custom_roles',
        ];

        // Act
        Models::setTables($customTables);

        // Assert
        $this->assertSame('custom_abilities', Models::table('abilities'));
        $this->assertSame('custom_roles', Models::table('roles'));
    }

    #[Test()]
    #[TestDox('Returns default table name when not customized')]
    #[Group('happy-path')]
    public function returns_default_table_name_when_not_customized(): void
    {
        // Arrange
        $defaultTable = 'some_table';

        // Act
        $result = Models::table($defaultTable);

        // Assert
        $this->assertSame($defaultTable, $result);
    }

    #[Test()]
    #[TestDox('Checks ownership via closure strategy')]
    #[Group('happy-path')]
    public function checks_ownership_via_closure_strategy(): void
    {
        // Arrange
        $user = User::query()->create(['name' => 'Owner']);
        $account = Account::query()->create(['name' => 'Account']);
        $account->actor_id = $user->id;
        $account->actor_type = $user->getMorphClass();
        $account->save();

        Models::ownedVia(Account::class, fn ($model, $authority): bool => $model->actor_id === $authority->id);

        // Act
        $result = Models::isOwnedBy($user, $account);

        // Assert
        $this->assertTrue($result);
    }

    #[Test()]
    #[TestDox('Checks ownership via string attribute strategy')]
    #[Group('happy-path')]
    public function checks_ownership_via_string_attribute_strategy(): void
    {
        // Arrange
        $user = User::query()->create(['name' => 'Owner']);
        $account = Account::query()->create(['name' => 'Account']);
        $account->actor_id = $user->id;
        $account->save();

        Models::ownedVia(Account::class, 'actor_id');

        // Act
        $result = Models::isOwnedBy($user, $account);

        // Assert
        $this->assertTrue($result);
    }

    #[Test()]
    #[TestDox('Returns false when ownership check fails')]
    #[Group('sad-path')]
    public function returns_false_when_ownership_check_fails(): void
    {
        // Arrange
        $user1 = User::query()->create(['name' => 'User 1']);
        $user2 = User::query()->create(['name' => 'User 2']);
        $account = Account::query()->create(['name' => 'Account']);
        $account->actor_id = $user2->id;
        $account->save();

        Models::ownedVia(Account::class, 'actor_id');

        // Act
        $result = Models::isOwnedBy($user1, $account);

        // Assert
        $this->assertFalse($result);
    }

    #[Test()]
    #[TestDox('Uses global ownership strategy as fallback')]
    #[Group('happy-path')]
    public function uses_global_ownership_strategy_as_fallback(): void
    {
        // Arrange
        $user = User::query()->create(['name' => 'Owner']);
        $account = Account::query()->create(['name' => 'Account']);

        // Use existing actor_id column
        $account->actor_id = $user->id;
        $account->save();

        // Set global strategy to use actor_id
        Models::ownedVia('actor_id');

        // Act
        $result = Models::isOwnedBy($user, $account);

        // Assert
        $this->assertTrue($result);
    }

    #[Test()]
    #[TestDox('Uses default actor morph ownership check')]
    #[Group('happy-path')]
    public function uses_default_actor_morph_ownership_check(): void
    {
        // Arrange
        $user = User::query()->create(['name' => 'Owner']);
        $account = Account::query()->create(['name' => 'Account']);
        $account->actor_id = $user->id;
        $account->actor_type = $user->getMorphClass();
        $account->save();

        // Act (no ownership strategy configured)
        $result = Models::isOwnedBy($user, $account);

        // Assert
        $this->assertTrue($result);
    }

    #[Test()]
    #[TestDox('Returns model key from key map')]
    #[Group('happy-path')]
    public function returns_model_key_from_key_map(): void
    {
        // Arrange
        $user = User::query()->create(['name' => 'Test User']);
        Models::morphKeyMap([User::class => 'uuid']);

        // Act
        $result = Models::getModelKey($user);

        // Assert
        $this->assertSame('uuid', $result);
    }

    #[Test()]
    #[TestDox('Returns default key name when not in key map')]
    #[Group('happy-path')]
    public function returns_default_key_name_when_not_in_key_map(): void
    {
        // Arrange
        $user = User::query()->create(['name' => 'Test User']);

        // Act
        $result = Models::getModelKey($user);

        // Assert
        $this->assertEquals($user->getKeyName(), $result);
    }

    #[Test()]
    #[TestDox('Throws exception when enforcement enabled and model not in key map')]
    #[Group('sad-path')]
    public function throws_exception_when_enforcement_enabled_and_model_not_in_key_map(): void
    {
        // Arrange
        $user = User::query()->create(['name' => 'Test User']);
        // Enforce key map with only Account, but not User
        Models::enforceMorphKeyMap([Account::class => 'id']);

        // Act & Assert
        $this->expectException(MorphKeyViolationException::class);
        $this->expectExceptionMessage(User::class);
        Models::getModelKey($user);
    }

    #[Test()]
    #[TestDox('Enforces morph key map requirement')]
    #[Group('happy-path')]
    public function enforces_morph_key_map_requirement(): void
    {
        // Arrange
        $account = Account::query()->create(['name' => 'Test Account']);
        Models::enforceMorphKeyMap([Account::class => 'id']);

        // Act
        $result = Models::getModelKey($account);

        // Assert
        $this->assertSame('id', $result);
    }

    #[Test()]
    #[TestDox('Registers morph key map without enforcement')]
    #[Group('happy-path')]
    public function registers_morph_key_map_without_enforcement(): void
    {
        // Arrange
        $user = User::query()->create(['name' => 'Test User']);
        Models::morphKeyMap([User::class => 'custom_id']);

        // Act
        $result = Models::getModelKey($user);

        // Assert
        $this->assertSame('custom_id', $result);
    }

    #[Test()]
    #[TestDox('Requires key map without providing map')]
    #[Group('edge-case')]
    public function requires_key_map_without_providing_map(): void
    {
        // Arrange
        $user = User::query()->create(['name' => 'Test User']);
        Models::requireKeyMap();

        // Act & Assert
        $this->expectException(MorphKeyViolationException::class);
        Models::getModelKey($user);
    }
}
