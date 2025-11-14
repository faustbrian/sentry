<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Database\Concerns;

use Cline\Warden\Constraints\Constraint;
use Cline\Warden\Constraints\Group as ConstraintGroup;
use Cline\Warden\Database\Ability;
use Cline\Warden\Database\Models;
use Cline\Warden\Database\Role;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Fixtures\Models\Account;
use Tests\Fixtures\Models\User;
use Tests\TestCase;

use function array_key_exists;
use function config;
use function count;
use function explode;
use function mb_strtolower;

/**
 * Comprehensive test suite for the IsAbility trait.
 *
 * Tests ability lifecycle hooks, factory methods, constraint management,
 * polymorphic relationships, accessors/mutators, and query scopes.
 *
 * @internal
 *
 * @author Brian Faust <brian@cline.sh>
 */
#[Small()]
final class IsAbilityTest extends TestCase
{
    // =========================================================================
    // HAPPY PATH TESTS
    // =========================================================================

    /**
     * Test bootIsAbility() lifecycle - tenant scope application.
     */
    #[Test()]
    #[TestDox('Applies tenant scope during ability creation')]
    #[Group('happy-path')]
    public function applies_tenant_scope_during_creation(): void
    {
        // Arrange
        Models::scope()->to(42);

        // Act
        $ability = Ability::query()->create(['name' => 'test-ability']);

        // Assert
        $this->assertEquals(42, $ability->scope);
    }

    /**
     * Test bootIsAbility() lifecycle - automatic title generation.
     */
    #[Test()]
    #[TestDox('Generates automatic title when not provided')]
    #[Group('happy-path')]
    public function generates_automatic_title_when_not_provided(): void
    {
        // Arrange
        $attributes = ['name' => 'edit-posts'];

        // Act
        $ability = Ability::query()->create($attributes);

        // Assert
        $this->assertNotNull($ability->title);
        $this->assertIsString($ability->title);
    }

    /**
     * Test bootIsAbility() lifecycle - preserves explicit title.
     */
    #[Test()]
    #[TestDox('Preserves explicit title when provided')]
    #[Group('happy-path')]
    public function preserves_explicit_title_when_provided(): void
    {
        // Arrange
        $attributes = ['name' => 'edit-posts', 'title' => 'Custom Title'];

        // Act
        $ability = Ability::query()->create($attributes);

        // Assert
        $this->assertEquals('Custom Title', $ability->title);
    }

    /**
     * Test createForModel() with model instance.
     */
    #[Test()]
    #[TestDox('Creates and persists ability for model instance')]
    #[Group('happy-path')]
    public function creates_and_persists_ability_for_model_instance(): void
    {
        // Arrange
        $account = Account::query()->create(['name' => 'Test Account']);

        // Act
        $ability = Ability::createForModel($account, 'edit');

        // Assert
        $this->assertTrue($ability->exists);
        $this->assertEquals('edit', $ability->name);
        $this->assertEquals(Account::class, $ability->subject_type);
        $this->assertEquals($account->id, $ability->subject_id);
        $this->assertDatabaseHas(Models::table('abilities'), [
            'id' => $ability->id,
            'subject_type' => Account::class,
            'subject_id' => $account->id,
        ]);
    }

    /**
     * Test createForModel() with model class name.
     */
    #[Test()]
    #[TestDox('Creates and persists ability for model class')]
    #[Group('happy-path')]
    public function creates_and_persists_ability_for_model_class(): void
    {
        // Arrange
        $className = Account::class;

        // Act
        $ability = Ability::createForModel($className, 'create');

        // Assert
        $this->assertTrue($ability->exists);
        $this->assertEquals('create', $ability->name);
        $this->assertEquals(Account::class, $ability->subject_type);
        $this->assertNull($ability->subject_id);
        $this->assertDatabaseHas(Models::table('abilities'), [
            'id' => $ability->id,
            'subject_type' => Account::class,
        ]);
    }

    /**
     * Test createForModel() with wildcard.
     */
    #[Test()]
    #[TestDox('Creates and persists global wildcard ability')]
    #[Group('happy-path')]
    public function creates_and_persists_global_wildcard_ability(): void
    {
        // Arrange
        $model = '*';

        // Act
        $ability = Ability::createForModel($model, 'admin');

        // Assert
        $this->assertTrue($ability->exists);
        $this->assertEquals('admin', $ability->name);
        $this->assertEquals('*', $ability->subject_type);
        $this->assertDatabaseHas(Models::table('abilities'), [
            'id' => $ability->id,
            'subject_type' => '*',
        ]);
    }

    /**
     * Test createForModel() with array attributes.
     */
    #[Test()]
    #[TestDox('Creates ability with full attribute array')]
    #[Group('happy-path')]
    public function creates_ability_with_full_attribute_array(): void
    {
        // Arrange
        $account = Account::query()->create(['name' => 'Test Account']);
        $attributes = [
            'name' => 'delete',
            'title' => 'Delete Account',
            'only_owned' => true,
        ];

        // Act
        $ability = Ability::createForModel($account, $attributes);

        // Assert
        $this->assertTrue($ability->exists);
        $this->assertEquals('delete', $ability->name);
        $this->assertEquals('Delete Account', $ability->title);
        $this->assertTrue($ability->only_owned);
    }

    /**
     * Test makeForModel() with model instance.
     */
    #[Test()]
    #[TestDox('Builds unpersisted ability for model instance')]
    #[Group('happy-path')]
    public function builds_unpersisted_ability_for_model_instance(): void
    {
        // Arrange
        $account = Account::query()->create(['name' => 'Test Account']);

        // Act
        $ability = Ability::makeForModel($account, 'edit');

        // Assert
        $this->assertFalse($ability->exists);
        $this->assertEquals('edit', $ability->name);
        $this->assertEquals(Account::class, $ability->subject_type);
        $this->assertEquals($account->id, $ability->subject_id);
    }

    /**
     * Test makeForModel() with model class name.
     */
    #[Test()]
    #[TestDox('Builds unpersisted ability for model class')]
    #[Group('happy-path')]
    public function builds_unpersisted_ability_for_model_class(): void
    {
        // Arrange
        $className = Account::class;

        // Act
        $ability = Ability::makeForModel($className, 'create');

        // Assert
        $this->assertFalse($ability->exists);
        $this->assertEquals('create', $ability->name);
        $this->assertEquals(Account::class, $ability->subject_type);
        $this->assertNull($ability->subject_id);
    }

    /**
     * Test makeForModel() with wildcard.
     */
    #[Test()]
    #[TestDox('Builds unpersisted global wildcard ability')]
    #[Group('happy-path')]
    public function builds_unpersisted_global_wildcard_ability(): void
    {
        // Arrange
        $model = '*';

        // Act
        $ability = Ability::makeForModel($model, 'admin');

        // Assert
        $this->assertFalse($ability->exists);
        $this->assertEquals('admin', $ability->name);
        $this->assertEquals('*', $ability->subject_type);
    }

    /**
     * Test makeForModel() with string attributes.
     */
    #[Test()]
    #[TestDox('Converts string attributes to name array')]
    #[Group('happy-path')]
    public function converts_string_attributes_to_name_array(): void
    {
        // Arrange
        $model = Account::class;
        $nameString = 'view';

        // Act
        $ability = Ability::makeForModel($model, $nameString);

        // Assert
        $this->assertEquals('view', $ability->name);
        $this->assertEquals(Account::class, $ability->subject_type);
    }

    /**
     * Test hasConstraints() returns false when empty.
     */
    #[Test()]
    #[TestDox('Returns false when no constraints defined')]
    #[Group('happy-path')]
    public function returns_false_when_no_constraints_defined(): void
    {
        // Arrange
        $ability = Ability::makeForModel(Account::class, 'edit');

        // Act
        $hasConstraints = $ability->hasConstraints();

        // Assert
        $this->assertFalse($hasConstraints);
    }

    /**
     * Test hasConstraints() returns true when defined.
     */
    #[Test()]
    #[TestDox('Returns true when constraints are defined')]
    #[Group('happy-path')]
    public function returns_true_when_constraints_are_defined(): void
    {
        // Arrange
        $ability = Ability::makeForModel(Account::class, 'edit');
        $ability->setConstraints(ConstraintGroup::withAnd()->add(Constraint::where('active', true)));

        // Act
        $hasConstraints = $ability->hasConstraints();

        // Assert
        $this->assertTrue($hasConstraints);
    }

    /**
     * Test getConstraints() returns empty Group when none defined.
     */
    #[Test()]
    #[TestDox('Returns empty Group when no constraints defined')]
    #[Group('happy-path')]
    public function returns_empty_group_when_no_constraints_defined(): void
    {
        // Arrange
        $ability = Ability::makeForModel(Account::class, 'edit');

        // Act
        $constraints = $ability->getConstraints();

        // Assert
        $this->assertInstanceOf(ConstraintGroup::class, $constraints);
    }

    /**
     * Test getConstraints() returns configured constrainer.
     */
    #[Test()]
    #[TestDox('Returns configured constrainer with proper data')]
    #[Group('happy-path')]
    public function returns_configured_constrainer_with_proper_data(): void
    {
        // Arrange
        $ability = Ability::makeForModel(Account::class, 'edit');
        $group = ConstraintGroup::withAnd()->add(Constraint::where('active', true));
        $ability->setConstraints($group);
        $ability->save();

        // Act
        $ability->refresh();

        $constraints = $ability->getConstraints();

        // Assert
        $this->assertInstanceOf(ConstraintGroup::class, $constraints);
        $this->assertTrue($constraints->check(
            new Account(['active' => true]),
            new User(),
        ));
        $this->assertFalse($constraints->check(
            new Account(['active' => false]),
            new User(),
        ));
    }

    /**
     * Test setConstraints() stores constrainer in options.
     */
    #[Test()]
    #[TestDox('Stores constrainer in options JSON column')]
    #[Group('happy-path')]
    public function stores_constrainer_in_options_json_column(): void
    {
        // Arrange
        $ability = Ability::makeForModel(Account::class, 'edit');
        $group = ConstraintGroup::withAnd()->add(Constraint::where('active', true));

        // Act
        $result = $ability->setConstraints($group);

        // Assert
        $this->assertSame($ability, $result);
        $this->assertTrue($ability->hasConstraints());
        $this->assertArrayHasKey('constraints', $ability->options);
    }

    /**
     * Test setConstraints() merges with existing options.
     */
    #[Test()]
    #[TestDox('Merges constraints with existing options')]
    #[Group('happy-path')]
    public function merges_constraints_with_existing_options(): void
    {
        // Arrange
        $ability = Ability::makeForModel(Account::class, 'edit');
        $ability->options = ['custom_key' => 'custom_value'];

        $group = ConstraintGroup::withAnd()->add(Constraint::where('active', true));

        // Act
        $ability->setConstraints($group);

        // Assert
        $this->assertArrayHasKey('constraints', $ability->options);
        $this->assertArrayHasKey('custom_key', $ability->options);
        $this->assertEquals('custom_value', $ability->options['custom_key']);
    }

    /**
     * Test roles() relationship returns MorphToMany.
     */
    #[Test()]
    #[TestDox('Returns polymorphic many-to-many roles relationship')]
    #[Group('happy-path')]
    public function returns_polymorphic_many_to_many_roles_relationship(): void
    {
        // Arrange
        $ability = Ability::createForModel(Account::class, 'edit');

        // Act
        $relation = $ability->roles();

        // Assert
        $this->assertInstanceOf(MorphToMany::class, $relation);
        $this->assertSame($relation->getRelated()::class, Models::classname(Role::class));
    }

    /**
     * Test roles() relationship includes pivot columns.
     */
    #[Test()]
    #[TestDox('Roles relationship includes forbidden and scope pivot columns')]
    #[Group('happy-path')]
    public function roles_relationship_includes_forbidden_and_scope_pivot_columns(): void
    {
        // Arrange
        $ability = Ability::createForModel(Account::class, 'edit');
        $relation = $ability->roles();

        // Act
        $pivotColumns = $relation->getPivotColumns();

        // Assert
        $this->assertContains('forbidden', $pivotColumns);
        $this->assertContains('scope', $pivotColumns);
        $this->assertContains('context_id', $pivotColumns);
        $this->assertContains('context_type', $pivotColumns);
    }

    /**
     * Test users() relationship returns MorphToMany.
     */
    #[Test()]
    #[TestDox('Returns polymorphic many-to-many users relationship')]
    #[Group('happy-path')]
    public function returns_polymorphic_many_to_many_users_relationship(): void
    {
        // Arrange
        config(['warden.user_model' => User::class]);
        $ability = Ability::createForModel(Account::class, 'edit');

        // Act
        $relation = $ability->users();

        // Assert
        $this->assertInstanceOf(MorphToMany::class, $relation);
        $this->assertSame($relation->getRelated()::class, Models::classname(User::class));
    }

    /**
     * Test users() relationship includes pivot columns.
     */
    #[Test()]
    #[TestDox('Users relationship includes forbidden and scope pivot columns')]
    #[Group('happy-path')]
    public function users_relationship_includes_forbidden_and_scope_pivot_columns(): void
    {
        // Arrange
        config(['warden.user_model' => User::class]);
        $ability = Ability::createForModel(Account::class, 'edit');
        $relation = $ability->users();

        // Act
        $pivotColumns = $relation->getPivotColumns();

        // Assert
        $this->assertContains('forbidden', $pivotColumns);
        $this->assertContains('scope', $pivotColumns);
        $this->assertContains('context_id', $pivotColumns);
        $this->assertContains('context_type', $pivotColumns);
    }

    /**
     * Test subject() relationship returns MorphTo.
     */
    #[Test()]
    #[TestDox('Returns polymorphic subject relationship')]
    #[Group('happy-path')]
    public function returns_polymorphic_subject_relationship(): void
    {
        // Arrange
        $account = Account::query()->create(['name' => 'Test Account']);
        $ability = Ability::createForModel($account, 'edit');

        // Act
        $relation = $ability->subject();
        $subject = $ability->subject;

        // Assert
        $this->assertInstanceOf(MorphTo::class, $relation);
        $this->assertInstanceOf(Account::class, $subject);
        $this->assertEquals($account->id, $subject->id);
    }

    /**
     * Test context() relationship returns MorphTo.
     */
    #[Test()]
    #[TestDox('Returns polymorphic context relationship')]
    #[Group('happy-path')]
    public function returns_polymorphic_context_relationship(): void
    {
        // Arrange
        $account = Account::query()->create(['name' => 'Context Account']);
        $ability = new Ability([
            'name' => 'edit',
        ]);
        $ability->context_type = Account::class;
        $ability->context_id = $account->id;
        $ability->save();

        // Act
        $ability->refresh();

        $relation = $ability->context();
        $context = $ability->context;

        // Assert
        $this->assertInstanceOf(MorphTo::class, $relation);
        $this->assertInstanceOf(Account::class, $context);
        $this->assertEquals($account->id, $context->id);
    }

    /**
     * Test getOptionsAttribute() decodes JSON to array.
     */
    #[Test()]
    #[TestDox('Decodes JSON options to array')]
    #[Group('happy-path')]
    public function decodes_json_options_to_array(): void
    {
        // Arrange
        $ability = Ability::makeForModel(Account::class, 'edit');
        $ability->options = ['key' => 'value', 'nested' => ['data' => 123]];
        $ability->save();

        // Act
        $ability->refresh();

        $options = $ability->options;

        // Assert
        $this->assertIsArray($options);
        $this->assertArrayHasKey('key', $options);
        $this->assertEquals('value', $options['key']);
        $this->assertIsArray($options['nested']);
        $this->assertEquals(123, $options['nested']['data']);
    }

    /**
     * Test getOptionsAttribute() returns empty array when null.
     */
    #[Test()]
    #[TestDox('Returns empty array when options is null')]
    #[Group('happy-path')]
    public function returns_empty_array_when_options_is_null(): void
    {
        // Arrange
        $ability = Ability::makeForModel(Account::class, 'edit');

        // Act
        $options = $ability->options;

        // Assert
        $this->assertIsArray($options);
        $this->assertEmpty($options);
    }

    /**
     * Test getIdentifierAttribute() with all attributes.
     */
    #[Test()]
    #[TestDox('Generates identifier with name, type, id, and owned flag')]
    #[Group('happy-path')]
    public function generates_identifier_with_name_type_id_and_owned_flag(): void
    {
        // Arrange
        $account = Account::query()->create(['name' => 'Test Account']);
        $ability = Ability::makeForModel($account, [
            'name' => 'edit',
            'only_owned' => true,
        ]);
        $ability->save();

        // Act
        $identifier = $ability->identifier;

        // Assert
        $this->assertIsString($identifier);
        $this->assertStringContainsString('edit', $identifier);
        $this->assertStringContainsString(mb_strtolower(Account::class), $identifier);
        $this->assertStringContainsString((string) $account->id, $identifier);
        $this->assertStringContainsString('owned', $identifier);
    }

    /**
     * Test getIdentifierAttribute() with name only.
     */
    #[Test()]
    #[TestDox('Generates identifier with name only')]
    #[Group('happy-path')]
    public function generates_identifier_with_name_only(): void
    {
        // Arrange
        $ability = new Ability(['name' => 'simple-ability']);
        $ability->subject_type = null;
        $ability->subject_id = null;
        $ability->save();

        // Act
        $ability->refresh();

        $identifier = $ability->identifier;

        // Assert
        $this->assertEquals('simple-ability', $identifier);
    }

    /**
     * Test getIdentifierAttribute() with name and type.
     */
    #[Test()]
    #[TestDox('Generates identifier with name and type')]
    #[Group('happy-path')]
    public function generates_identifier_with_name_and_type(): void
    {
        // Arrange
        $ability = Ability::createForModel(Account::class, 'create');

        // Act
        $identifier = $ability->identifier;

        // Assert
        $this->assertStringStartsWith('create-', $identifier);
        $this->assertStringContainsString(mb_strtolower(Account::class), (string) $identifier);
        $this->assertStringNotContainsString('owned', (string) $identifier);
    }

    /**
     * Test getSlugAttribute() returns identifier.
     */
    #[Test()]
    #[TestDox('Slug attribute returns same as identifier')]
    #[Group('happy-path')]
    public function slug_attribute_returns_same_as_identifier(): void
    {
        // Arrange
        $ability = Ability::createForModel(Account::class, 'view');

        // Act
        $slug = $ability->slug;
        $identifier = $ability->identifier;

        // Assert
        $this->assertEquals($identifier, $slug);
    }

    /**
     * Test scopeByName() with single name.
     */
    #[Test()]
    #[TestDox('Filters abilities by single name')]
    #[Group('happy-path')]
    public function filters_abilities_by_single_name(): void
    {
        // Arrange
        Ability::query()->create(['name' => 'edit']);
        Ability::query()->create(['name' => 'view']);
        Ability::query()->create(['name' => 'delete']);

        // Act
        $results = Ability::query()->byName('edit')->get();

        // Assert
        $this->assertGreaterThanOrEqual(1, $results->count());
        $this->assertTrue($results->contains('name', 'edit'));
    }

    /**
     * Test scopeByName() with array of names.
     */
    #[Test()]
    #[TestDox('Filters abilities by array of names')]
    #[Group('happy-path')]
    public function filters_abilities_by_array_of_names(): void
    {
        // Arrange
        Ability::query()->create(['name' => 'edit']);
        Ability::query()->create(['name' => 'view']);
        Ability::query()->create(['name' => 'delete']);

        // Act
        $results = Ability::query()->byName(['edit', 'view'])->get();

        // Assert
        $this->assertGreaterThanOrEqual(2, $results->count());
        $this->assertTrue($results->contains('name', 'edit'));
        $this->assertTrue($results->contains('name', 'view'));
    }

    /**
     * Test scopeByName() includes wildcard in non-strict mode.
     */
    #[Test()]
    #[TestDox('Includes wildcard abilities in non-strict mode')]
    #[Group('happy-path')]
    public function includes_wildcard_abilities_in_non_strict_mode(): void
    {
        // Arrange
        Ability::query()->create(['name' => 'edit']);
        Ability::query()->create(['name' => '*']);

        // Act
        $results = Ability::query()->byName('edit', false)->get();

        // Assert
        $this->assertCount(2, $results);
        $this->assertTrue($results->contains('name', 'edit'));
        $this->assertTrue($results->contains('name', '*'));
    }

    /**
     * Test scopeByName() excludes wildcard in strict mode.
     */
    #[Test()]
    #[TestDox('Excludes wildcard abilities in strict mode')]
    #[Group('happy-path')]
    public function excludes_wildcard_abilities_in_strict_mode(): void
    {
        // Arrange
        Ability::query()->create(['name' => 'edit']);
        Ability::query()->create(['name' => '*']);

        // Act
        $results = Ability::query()->byName('edit', true)->get();

        // Assert
        $this->assertCount(1, $results);
        $this->assertEquals('edit', $results->first()->name);
    }

    /**
     * Test scopeSimpleAbility() filters null subject_type.
     */
    #[Test()]
    #[TestDox('Filters abilities with null subject_type')]
    #[Group('happy-path')]
    public function filters_abilities_with_null_subject_type(): void
    {
        // Arrange
        Ability::query()->create(['name' => 'simple']);
        Ability::createForModel(Account::class, 'scoped');

        // Act
        $results = Ability::query()->simpleAbility()->get();

        // Assert
        $this->assertCount(1, $results);
        $this->assertEquals('simple', $results->first()->name);
        $this->assertNull($results->first()->subject_type);
    }

    /**
     * Test scopeForModel() with model instance.
     */
    #[Test()]
    #[TestDox('Filters abilities for specific model instance')]
    #[Group('happy-path')]
    public function filters_abilities_for_specific_model_instance(): void
    {
        // Arrange
        $account = Account::query()->create(['name' => 'Test Account']);
        Ability::createForModel($account, 'edit');
        Ability::createForModel(Account::class, 'create');

        // Act
        $results = Ability::query()->forModel($account)->get();

        // Assert
        $this->assertGreaterThanOrEqual(1, $results->count());
    }

    /**
     * Test scopeForModel() with model class.
     */
    #[Test()]
    #[TestDox('Filters abilities for model class')]
    #[Group('happy-path')]
    public function filters_abilities_for_model_class(): void
    {
        // Arrange
        Ability::createForModel(Account::class, 'create');
        Ability::createForModel(User::class, 'view');

        // Act
        $results = Ability::query()->forModel(Account::class)->get();

        // Assert
        $this->assertGreaterThanOrEqual(1, $results->count());
        $this->assertTrue($results->contains(fn ($ability): bool => $ability->subject_type === Account::class));
    }

    // =========================================================================
    // SAD PATH TESTS
    // =========================================================================

    /**
     * Test makeForModel() with non-existent model instance.
     */
    #[Test()]
    #[TestDox('Creates ability with null subject_id for non-existent model')]
    #[Group('sad-path')]
    public function creates_ability_with_null_subject_id_for_non_existent_model(): void
    {
        // Arrange
        $account = new Account(['name' => 'Not Saved']);

        // Act
        $ability = Ability::makeForModel($account, 'edit');

        // Assert
        $this->assertEquals(Account::class, $ability->subject_type);
        $this->assertNull($ability->subject_id);
    }

    /**
     * Test getConstraints() with corrupted options data.
     */
    #[Test()]
    #[TestDox('Returns empty Group when constraints data is invalid')]
    #[Group('sad-path')]
    public function returns_empty_group_when_constraints_data_is_invalid(): void
    {
        // Arrange
        $ability = Ability::makeForModel(Account::class, 'edit');
        $ability->options = ['constraints' => null];

        // Act
        $constraints = $ability->getConstraints();

        // Assert
        $this->assertInstanceOf(ConstraintGroup::class, $constraints);
    }

    // =========================================================================
    // EDGE CASE TESTS
    // =========================================================================

    /**
     * Test getIdentifierAttribute() with null subject_type.
     */
    #[Test()]
    #[TestDox('Generates identifier without type when subject_type is null')]
    #[Group('edge-case')]
    public function generates_identifier_without_type_when_subject_type_is_null(): void
    {
        // Arrange
        $ability = Ability::query()->create(['name' => 'test']);
        $ability->subject_type = null;
        $ability->save();

        // Act
        $ability->refresh();

        $identifier = $ability->identifier;

        // Assert
        $this->assertEquals('test', $identifier);
    }

    /**
     * Test getIdentifierAttribute() with null subject_id.
     */
    #[Test()]
    #[TestDox('Generates identifier without id when subject_id is null')]
    #[Group('edge-case')]
    public function generates_identifier_without_id_when_subject_id_is_null(): void
    {
        // Arrange
        $ability = Ability::createForModel(Account::class, 'create');

        // Act
        $identifier = $ability->identifier;

        // Assert
        $this->assertStringStartsWith('create-', $identifier);
        $this->assertStringContainsString(mb_strtolower(Account::class), (string) $identifier);
        // Identifier should only have create-classname, not create-classname-number
        $parts = explode('-', (string) $identifier);
        $this->assertLessThanOrEqual(2, count($parts)); // create and classname parts only
    }

    /**
     * Test getIdentifierAttribute() with only_owned false.
     */
    #[Test()]
    #[TestDox('Generates identifier without owned flag when only_owned is false')]
    #[Group('edge-case')]
    public function generates_identifier_without_owned_flag_when_only_owned_is_false(): void
    {
        // Arrange
        $ability = Ability::createForModel(Account::class, [
            'name' => 'edit',
            'only_owned' => false,
        ]);

        // Act
        $identifier = $ability->identifier;

        // Assert
        $this->assertStringNotContainsString('owned', (string) $identifier);
    }

    /**
     * Test getIdentifierAttribute() with only_owned null.
     */
    #[Test()]
    #[TestDox('Generates identifier without owned flag when only_owned is null')]
    #[Group('edge-case')]
    public function generates_identifier_without_owned_flag_when_only_owned_is_null(): void
    {
        // Arrange
        $ability = Ability::createForModel(Account::class, 'edit');
        $ability->only_owned = false;
        $ability->save();

        // Act
        $ability->refresh();

        $identifier = $ability->identifier;

        // Assert
        $this->assertStringNotContainsString('owned', (string) $identifier);
    }

    /**
     * Test getIdentifierAttribute() returns lowercase.
     */
    #[Test()]
    #[TestDox('Generates identifier in lowercase format')]
    #[Group('edge-case')]
    public function generates_identifier_in_lowercase_format(): void
    {
        // Arrange
        $ability = new Ability(['name' => 'UPPERCASE-NAME']);
        $ability->subject_type = null;
        $ability->subject_id = null;
        $ability->save();

        // Act
        $ability->refresh();

        $identifier = $ability->identifier;

        // Assert
        $this->assertEquals(mb_strtolower($identifier), $identifier);
    }

    /**
     * Test scopeByName() with wildcard name excludes extra wildcards.
     */
    #[Test()]
    #[TestDox('Does not add duplicate wildcard when name is already wildcard')]
    #[Group('edge-case')]
    public function does_not_add_duplicate_wildcard_when_name_is_already_wildcard(): void
    {
        // Arrange
        Ability::query()->create(['name' => '*']);

        // Act
        $results = Ability::query()->byName('*', false)->get();

        // Assert
        $this->assertCount(1, $results);
        $this->assertEquals('*', $results->first()->name);
    }

    /**
     * Test subject() relationship with null subject_id.
     */
    #[Test()]
    #[TestDox('Subject relationship returns null when subject_id is null')]
    #[Group('edge-case')]
    public function subject_relationship_returns_null_when_subject_id_is_null(): void
    {
        // Arrange
        $ability = Ability::createForModel(Account::class, 'create');

        // Act
        $subject = $ability->subject;

        // Assert
        $this->assertNull($subject);
    }

    /**
     * Test context() relationship with null context_id.
     */
    #[Test()]
    #[TestDox('Context relationship returns null when context_id is null')]
    #[Group('edge-case')]
    public function context_relationship_returns_null_when_context_id_is_null(): void
    {
        // Arrange
        $ability = Ability::query()->create(['name' => 'test']);

        // Act
        $context = $ability->context;

        // Assert
        $this->assertNull($context);
    }

    /**
     * Test getOptionsAttribute() with empty string.
     */
    #[Test()]
    #[TestDox('Returns empty array when options is empty string')]
    #[Group('edge-case')]
    public function returns_empty_array_when_options_is_empty_string(): void
    {
        // Arrange
        $ability = Ability::query()->create(['name' => 'test']);
        // Directly set empty options in database
        $ability->saveQuietly(['options' => '']);

        // Act
        $ability->refresh();

        $options = $ability->options;

        // Assert
        $this->assertIsArray($options);
        $this->assertEmpty($options);
    }

    /**
     * Test setConstraints() with null existing options.
     */
    #[Test()]
    #[TestDox('Sets constraints when options is initially null')]
    #[Group('edge-case')]
    public function sets_constraints_when_options_is_initially_null(): void
    {
        // Arrange
        $ability = Ability::makeForModel(Account::class, 'edit');
        // Options accessor returns array, so it will never be null after access
        $group = ConstraintGroup::withAnd()->add(Constraint::where('active', true));

        // Act
        $ability->setConstraints($group);

        // Assert
        $this->assertIsArray($ability->options);
        $this->assertArrayHasKey('constraints', $ability->options);
    }

    /**
     * Test scopeForModel() with strict mode.
     */
    #[Test()]
    #[TestDox('Excludes wildcard abilities in strict mode')]
    #[Group('edge-case')]
    public function excludes_wildcard_abilities_in_strict_mode_for_model(): void
    {
        // Arrange
        Ability::createForModel('*', 'admin');
        Ability::createForModel(Account::class, 'edit');

        // Act
        $results = Ability::query()->forModel(Account::class, true)->get();

        // Assert
        $this->assertGreaterThanOrEqual(1, $results->count());
        $this->assertFalse($results->contains('subject_type', '*'));
    }

    /**
     * Test createForModel() with existing model and non-existent model class.
     */
    #[Test()]
    #[TestDox('Handles transition from instance to class correctly')]
    #[Group('edge-case')]
    public function handles_transition_from_instance_to_class_correctly(): void
    {
        // Arrange
        $account = Account::query()->create(['name' => 'Test Account']);

        // Act
        $instanceAbility = Ability::createForModel($account, 'instance-ability');
        $classAbility = Ability::createForModel(Account::class, 'class-ability');

        // Assert
        $this->assertNotNull($instanceAbility->subject_id);
        $this->assertNull($classAbility->subject_id);
        $this->assertEquals($account->id, $instanceAbility->subject_id);
        $this->assertEquals(Account::class, $classAbility->subject_type);
    }

    /**
     * Test getIdentifierAttribute() with various attribute combinations.
     */
    #[Test()]
    #[TestDox('Generates correct identifier for various attribute combinations')]
    #[DataProvider('provideGenerates_correct_identifier_for_various_attribute_combinationsCases')]
    #[Group('edge-case')]
    public function generates_correct_identifier_for_various_attribute_combinations(array $data): void
    {
        // Arrange
        $ability = new Ability();

        foreach ($data['attributes'] as $key => $value) {
            $ability->setAttribute($key, $value);
        }

        // Act
        $identifier = $ability->identifier;

        // Assert
        if (array_key_exists('expected', $data)) {
            $this->assertEquals($data['expected'], $identifier);
        }

        if (array_key_exists('contains', $data)) {
            foreach ($data['contains'] as $substring) {
                $this->assertStringContainsString($substring, (string) $identifier);
            }
        }

        if (array_key_exists('not_contains', $data)) {
            foreach ($data['not_contains'] as $substring) {
                $this->assertStringNotContainsString($substring, (string) $identifier);
            }
        }
    }

    /**
     * Data provider for identifier attribute combinations.
     */
    public static function provideGenerates_correct_identifier_for_various_attribute_combinationsCases(): iterable
    {
        yield 'name only' => [[
            'attributes' => ['name' => 'test', 'subject_type' => null, 'subject_id' => null, 'only_owned' => false],
            'expected' => 'test',
        ]];

        yield 'name and type' => [[
            'attributes' => ['name' => 'edit', 'subject_type' => Account::class, 'subject_id' => null, 'only_owned' => false],
            'contains' => ['edit', mb_strtolower(Account::class)],
            'not_contains' => ['owned'],
        ]];

        yield 'name, type, and id' => [[
            'attributes' => ['name' => 'view', 'subject_type' => Account::class, 'subject_id' => 123, 'only_owned' => false],
            'contains' => ['view', mb_strtolower(Account::class), '123'],
            'not_contains' => ['owned'],
        ]];

        yield 'name, type, id, and owned' => [[
            'attributes' => ['name' => 'delete', 'subject_type' => Account::class, 'subject_id' => 456, 'only_owned' => true],
            'contains' => ['delete', mb_strtolower(Account::class), '456', 'owned'],
        ]];
    }
}
