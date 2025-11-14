<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Database\Queries;

use Cline\Warden\Database\Ability;
use Cline\Warden\Database\Queries\AbilitiesForModel;
use Illuminate\Support\Facades\DB;
use Override;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Models\Account;
use Tests\Fixtures\Models\User;
use Tests\TestCase;

/**
 * Tests for AbilitiesForModel query builder.
 *
 * Verifies model-scoped ability query constraints including wildcard filtering,
 * strict vs non-strict mode behavior, blanket abilities, and instance-specific
 * ability filtering.
 *
 * @internal
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class AbilitiesForModelTest extends TestCase
{
    private AbilitiesForModel $query;

    #[Override()]
    protected function setUp(): void
    {
        parent::setUp();

        $this->query = new AbilitiesForModel();
    }

    #[Test()]
    public function it_constrains_query_to_wildcard_abilities_when_model_is_asterisk(): void
    {
        // Arrange
        Ability::query()->forceCreate(['name' => 'global-ability', 'subject_type' => '*']);
        Ability::query()->forceCreate(['name' => 'user-ability', 'subject_type' => User::class]);
        Ability::query()->forceCreate(['name' => 'account-ability', 'subject_type' => Account::class]);

        $query = Ability::query();

        // Act
        $this->query->constrain($query, '*');

        $results = $query->get();

        // Assert
        $this->assertCount(1, $results);
        $this->assertEquals('global-ability', $results->first()->name);
        $this->assertEquals('*', $results->first()->subject_type);
    }

    #[Test()]
    public function it_constrains_query_to_model_class_abilities_in_non_strict_mode(): void
    {
        // Arrange
        Ability::query()->forceCreate(['name' => 'global', 'subject_type' => '*', 'subject_id' => null]);
        Ability::query()->forceCreate(['name' => 'user-blanket', 'subject_type' => User::class, 'subject_id' => null]);
        Ability::query()->forceCreate(['name' => 'account-blanket', 'subject_type' => Account::class, 'subject_id' => null]);

        $query = Ability::query();

        // Act
        $this->query->constrain($query, User::class);

        $results = $query->get();

        // Assert - Should include wildcard (*) AND User class blanket abilities
        $this->assertCount(2, $results);
        $this->assertTrue($results->contains('name', 'global'));
        $this->assertTrue($results->contains('name', 'user-blanket'));
    }

    #[Test()]
    public function it_constrains_query_to_model_class_abilities_in_strict_mode(): void
    {
        // Arrange
        Ability::query()->forceCreate(['name' => 'global', 'subject_type' => '*', 'subject_id' => null]);
        Ability::query()->forceCreate(['name' => 'user-blanket', 'subject_type' => User::class, 'subject_id' => null]);
        Ability::query()->forceCreate(['name' => 'account-blanket', 'subject_type' => Account::class, 'subject_id' => null]);

        $query = Ability::query();

        // Act
        $this->query->constrain($query, User::class, strict: true);

        $results = $query->get();

        // Assert - Should exclude wildcard (*) abilities in strict mode
        $this->assertCount(1, $results);
        $this->assertEquals('user-blanket', $results->first()->name);
        $this->assertEquals(User::class, $results->first()->subject_type);
    }

    #[Test()]
    public function it_includes_blanket_abilities_for_non_existing_model_instances(): void
    {
        // Arrange
        $user = new User(); // Non-existing model (exists = false)

        Ability::query()->forceCreate(['name' => 'user-blanket', 'subject_type' => User::class, 'subject_id' => null]);
        Ability::query()->forceCreate(['name' => 'user-specific', 'subject_type' => User::class, 'subject_id' => 999]);

        $query = Ability::query();

        // Act
        $this->query->constrain($query, $user);

        $results = $query->get();

        // Assert - Non-existing models should only get blanket + wildcard abilities
        $this->assertTrue($results->contains('name', 'user-blanket'));
        $this->assertFalse($results->contains('name', 'user-specific'));
    }

    #[Test()]
    public function it_includes_instance_specific_abilities_for_existing_models_in_non_strict_mode(): void
    {
        // Arrange
        $user = User::query()->create();

        Ability::query()->forceCreate(['name' => 'global', 'subject_type' => '*', 'subject_id' => null]);
        Ability::query()->forceCreate(['name' => 'user-blanket', 'subject_type' => User::class, 'subject_id' => null]);
        Ability::query()->forceCreate(['name' => 'user-specific', 'subject_type' => User::class, 'subject_id' => $user->id]);
        Ability::query()->forceCreate(['name' => 'other-user-specific', 'subject_type' => User::class, 'subject_id' => 999]);

        $query = Ability::query();

        // Act
        $this->query->constrain($query, $user);

        $results = $query->get();

        // Assert - Should include: wildcard, blanket, and instance-specific
        $this->assertCount(3, $results);
        $this->assertTrue($results->contains('name', 'global'));
        $this->assertTrue($results->contains('name', 'user-blanket'));
        $this->assertTrue($results->contains('name', 'user-specific'));
        $this->assertFalse($results->contains('name', 'other-user-specific'));
    }

    #[Test()]
    public function it_excludes_blanket_abilities_for_existing_models_in_strict_mode(): void
    {
        // Arrange
        $user = User::query()->create();

        Ability::query()->forceCreate(['name' => 'global', 'subject_type' => '*', 'subject_id' => null]);
        Ability::query()->forceCreate(['name' => 'user-blanket', 'subject_type' => User::class, 'subject_id' => null]);
        Ability::query()->forceCreate(['name' => 'user-specific', 'subject_type' => User::class, 'subject_id' => $user->id]);

        $query = Ability::query();

        // Act
        $this->query->constrain($query, $user, strict: true);

        $results = $query->get();

        // Assert - Strict mode should exclude wildcards AND blanket abilities
        $this->assertCount(1, $results);
        $this->assertEquals('user-specific', $results->first()->name);
        $this->assertEquals($user->id, $results->first()->subject_id);
    }

    #[Test()]
    public function it_handles_multiple_model_types_correctly(): void
    {
        // Arrange
        $user = User::query()->create();
        $account = Account::query()->create();

        Ability::query()->forceCreate(['name' => 'user-ability', 'subject_type' => User::class, 'subject_id' => $user->id]);
        Ability::query()->forceCreate(['name' => 'account-ability', 'subject_type' => Account::class, 'subject_id' => $account->id]);

        $userQuery = Ability::query();
        $accountQuery = Ability::query();

        // Act
        $this->query->constrain($userQuery, $user, strict: true);
        $this->query->constrain($accountQuery, $account, strict: true);

        // Assert
        $this->assertEquals('user-ability', $userQuery->first()->name);
        $this->assertEquals('account-ability', $accountQuery->first()->name);
    }

    #[Test()]
    public function it_returns_empty_result_when_no_abilities_match(): void
    {
        // Arrange
        $user = User::query()->create();
        Ability::query()->forceCreate(['name' => 'account-ability', 'subject_type' => Account::class, 'subject_id' => 1]);

        $query = Ability::query();

        // Act
        $this->query->constrain($query, $user, strict: true);

        $results = $query->get();

        // Assert
        $this->assertCount(0, $results);
    }

    #[Test()]
    public function it_handles_model_with_custom_morph_class(): void
    {
        // Arrange - User models use default morph class (FQCN)
        $user = User::query()->create();
        $morphClass = $user->getMorphClass();

        Ability::query()->forceCreate(['name' => 'user-ability', 'subject_type' => $morphClass, 'subject_id' => $user->id]);

        $query = Ability::query();

        // Act
        $this->query->constrain($query, $user, strict: true);

        $results = $query->get();

        // Assert
        $this->assertCount(1, $results);
        $this->assertEquals('user-ability', $results->first()->name);
    }

    #[Test()]
    public function it_works_with_base_query_builder_not_just_eloquent(): void
    {
        // Arrange
        Ability::query()->forceCreate(['name' => 'global', 'subject_type' => '*', 'subject_id' => null]);
        Ability::query()->forceCreate(['name' => 'user-ability', 'subject_type' => User::class, 'subject_id' => null]);

        $query = DB::table('abilities');

        // Act
        $this->query->constrain($query, '*');

        $results = $query->get();

        // Assert
        $this->assertCount(1, $results);
        $this->assertEquals('global', $results[0]->name);
    }

    #[Test()]
    public function it_generates_correct_sql_for_wildcard_constraint(): void
    {
        // Arrange
        $query = Ability::query();

        // Act
        $this->query->constrain($query, '*');

        $sql = $query->toSql();

        // Assert
        $this->assertStringContainsString('where "abilities"."subject_type" = ?', $sql);
    }

    #[Test()]
    public function it_generates_correct_sql_for_non_strict_model_constraint(): void
    {
        // Arrange
        $user = User::query()->create();
        $query = Ability::query();

        // Act
        $this->query->constrain($query, $user);

        $sql = $query->toSql();

        // Assert - Should have OR condition for wildcard
        $this->assertStringContainsString('where ("abilities"."subject_type" = ? or', $sql);
        $this->assertStringContainsString('("abilities"."subject_type" = ?', $sql);
    }

    #[Test()]
    public function it_generates_correct_sql_for_strict_model_constraint(): void
    {
        // Arrange
        $user = User::query()->create();
        $query = Ability::query();

        // Act
        $this->query->constrain($query, $user, strict: true);

        $sql = $query->toSql();

        // Assert - Should NOT have OR condition for wildcard in strict mode
        $this->assertStringNotContainsString('"abilities"."subject_type" = ? or', $sql);
        $this->assertStringContainsString('where ("abilities"."subject_type" = ?', $sql);
    }

    #[Test()]
    public function it_includes_only_blanket_abilities_for_new_model_instance(): void
    {
        // Arrange - Create a fresh model instance that hasn't been saved
        $newUser = new User(['name' => 'Test User']);

        Ability::query()->forceCreate(['name' => 'user-blanket', 'subject_type' => User::class, 'subject_id' => null]);
        Ability::query()->forceCreate(['name' => 'user-specific-1', 'subject_type' => User::class, 'subject_id' => 1]);
        Ability::query()->forceCreate(['name' => 'user-specific-2', 'subject_type' => User::class, 'subject_id' => 2]);

        $query = Ability::query();

        // Act
        $this->query->constrain($query, $newUser, strict: true);

        $results = $query->get();

        // Assert - New models (exists=false) should only match blanket abilities
        $this->assertCount(1, $results);
        $this->assertEquals('user-blanket', $results->first()->name);
        $this->assertNull($results->first()->subject_id);
    }

    #[Test()]
    public function it_handles_empty_abilities_table_gracefully(): void
    {
        // Arrange - No abilities in database
        $user = User::query()->create();
        $query = Ability::query();

        // Act
        $this->query->constrain($query, $user);

        $results = $query->get();

        // Assert
        $this->assertCount(0, $results);
    }

    #[Test()]
    public function it_distinguishes_between_class_string_and_instance_correctly(): void
    {
        // Arrange - Same model type, one as class string, one as instance
        $user = User::query()->create();

        Ability::query()->forceCreate(['name' => 'blanket', 'subject_type' => User::class, 'subject_id' => null]);
        Ability::query()->forceCreate(['name' => 'specific', 'subject_type' => User::class, 'subject_id' => $user->id]);

        $classQuery = Ability::query();
        $instanceQuery = Ability::query();

        // Act
        $this->query->constrain($classQuery, User::class); // Class string = new model
        $this->query->constrain($instanceQuery, $user);    // Instance = existing model

        $classResults = $classQuery->get();
        $instanceResults = $instanceQuery->get();

        // Assert - Class string should only get blanket (like new model)
        $this->assertCount(1, $classResults); // Only blanket in non-strict mode for class string
        $this->assertTrue($classResults->contains('name', 'blanket'));
        $this->assertFalse($classResults->contains('name', 'specific'));

        // Instance should get both blanket and specific
        $this->assertCount(2, $instanceResults);
        $this->assertTrue($instanceResults->contains('name', 'blanket'));
        $this->assertTrue($instanceResults->contains('name', 'specific'));
    }

    #[Test()]
    public function it_correctly_filters_by_subject_id_using_model_key(): void
    {
        // Arrange - Test that it uses Models::getModelKey() correctly
        $user1 = User::query()->create();
        $user2 = User::query()->create();

        Ability::query()->forceCreate([
            'name' => 'edit-user-1',
            'subject_type' => User::class,
            'subject_id' => $user1->id,
        ]);

        Ability::query()->forceCreate([
            'name' => 'edit-user-2',
            'subject_type' => User::class,
            'subject_id' => $user2->id,
        ]);

        $query1 = Ability::query();
        $query2 = Ability::query();

        // Act
        $this->query->constrain($query1, $user1, strict: true);
        $this->query->constrain($query2, $user2, strict: true);

        // Assert - Each should only get their own instance-specific ability
        $this->assertEquals('edit-user-1', $query1->first()->name);
        $this->assertEquals($user1->id, $query1->first()->subject_id);

        $this->assertEquals('edit-user-2', $query2->first()->name);
        $this->assertEquals($user2->id, $query2->first()->subject_id);
    }

    #[Test()]
    public function it_applies_or_where_logic_correctly_for_existing_model_in_non_strict_mode(): void
    {
        // Arrange - Verify the OR condition works for (blanket OR specific)
        $user = User::query()->create();

        // Only blanket ability exists
        Ability::query()->forceCreate(['name' => 'blanket-only', 'subject_type' => User::class, 'subject_id' => null]);

        $query1 = Ability::query();
        $this->query->constrain($query1, $user);

        // Assert - Should find blanket ability via OR clause
        $this->assertCount(1, $query1->get());
        $this->assertEquals('blanket-only', $query1->first()->name);

        // Now add specific ability
        Ability::query()->forceCreate(['name' => 'specific-only', 'subject_type' => User::class, 'subject_id' => $user->id]);

        $query2 = Ability::query();
        $this->query->constrain($query2, $user);

        // Assert - Should find both via OR clause
        $results = $query2->get();
        $this->assertCount(2, $results);
        $this->assertTrue($results->contains('name', 'blanket-only'));
        $this->assertTrue($results->contains('name', 'specific-only'));
    }

    #[Test()]
    public function it_excludes_other_model_types_even_with_matching_ids(): void
    {
        // Arrange - Same ID for different model types
        $user = User::query()->create(['id' => 1]);
        $account = Account::query()->create(['id' => 1]);

        Ability::query()->forceCreate(['name' => 'user-1', 'subject_type' => User::class, 'subject_id' => 1]);
        Ability::query()->forceCreate(['name' => 'account-1', 'subject_type' => Account::class, 'subject_id' => 1]);

        $userQuery = Ability::query();
        $accountQuery = Ability::query();

        // Act
        $this->query->constrain($userQuery, $user, strict: true);
        $this->query->constrain($accountQuery, $account, strict: true);

        // Assert - Each should only match their own type
        $this->assertEquals('user-1', $userQuery->first()->name);
        $this->assertEquals(User::class, $userQuery->first()->subject_type);

        $this->assertEquals('account-1', $accountQuery->first()->name);
        $this->assertEquals(Account::class, $accountQuery->first()->subject_type);
    }
}
