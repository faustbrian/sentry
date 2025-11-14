<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Constraints;

use Cline\Warden\Constraints\ColumnConstraint;
use Cline\Warden\Constraints\Constrainer;
use Cline\Warden\Constraints\ValueConstraint;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\Models\Account;
use Tests\Fixtures\Models\User;

/**
 * Tests the Constrainer interface contract using concrete implementations.
 *
 * This test class validates that all Constrainer implementations properly
 * fulfill the interface contract, including serialization, deserialization,
 * logical operator management, equality comparison, and constraint evaluation.
 *
 * @internal
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ConstrainerTest extends TestCase
{
    // ========================================
    // fromData() Serialization Tests
    // ========================================

    #[Test()]
    #[TestDox('ValueConstraint fromData() reconstitutes constraint from array data')]
    #[Group('happy-path')]
    public function value_constraint_from_data_reconstitutes_instance(): void
    {
        // Arrange
        $data = [
            'column' => 'status',
            'operator' => '=',
            'value' => 'published',
            'logicalOperator' => 'and',
        ];

        // Act
        $constraint = ValueConstraint::fromData($data);

        // Assert
        $this->assertInstanceOf(ValueConstraint::class, $constraint);
        $this->assertInstanceOf(Constrainer::class, $constraint);
    }

    #[Test()]
    #[TestDox('ValueConstraint fromData() preserves constraint parameters correctly')]
    #[Group('happy-path')]
    public function value_constraint_from_data_preserves_parameters(): void
    {
        // Arrange
        $entity = new Account(['status' => 'active']);
        $data = [
            'column' => 'status',
            'operator' => '=',
            'value' => 'active',
            'logicalOperator' => 'or',
        ];

        // Act
        $constraint = ValueConstraint::fromData($data);

        // Assert
        $this->assertTrue($constraint->check($entity));
        $this->assertTrue($constraint->isOr());
        $this->assertFalse($constraint->isAnd());
    }

    #[Test()]
    #[TestDox('ValueConstraint fromData() defaults to AND when logicalOperator is null')]
    #[Group('happy-path')]
    public function value_constraint_from_data_defaults_to_and_operator(): void
    {
        // Arrange
        $data = [
            'column' => 'active',
            'operator' => '=',
            'value' => true,
            'logicalOperator' => null,
        ];

        // Act
        $constraint = ValueConstraint::fromData($data);

        // Assert
        $this->assertTrue($constraint->isAnd());
        $this->assertFalse($constraint->isOr());
    }

    #[Test()]
    #[TestDox('ValueConstraint fromData() throws exception for invalid column type')]
    #[Group('sad-path')]
    public function value_constraint_from_data_throws_for_invalid_column(): void
    {
        // Arrange
        $data = [
            'column' => 123, // Invalid: should be string
            'operator' => '=',
            'value' => 'test',
        ];

        // Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Column and operator must be strings');

        // Act
        ValueConstraint::fromData($data);
    }

    #[Test()]
    #[TestDox('ValueConstraint fromData() throws exception for invalid operator type')]
    #[Group('sad-path')]
    public function value_constraint_from_data_throws_for_invalid_operator(): void
    {
        // Arrange
        $data = [
            'column' => 'status',
            'operator' => 999, // Invalid: should be string
            'value' => 'test',
        ];

        // Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Column and operator must be strings');

        // Act
        ValueConstraint::fromData($data);
    }

    #[Test()]
    #[TestDox('ColumnConstraint fromData() reconstitutes constraint from array data')]
    #[Group('happy-path')]
    public function column_constraint_from_data_reconstitutes_instance(): void
    {
        // Arrange
        $data = [
            'a' => 'user_id',
            'operator' => '=',
            'b' => 'id',
            'logicalOperator' => 'and',
        ];

        // Act
        $constraint = ColumnConstraint::fromData($data);

        // Assert
        $this->assertInstanceOf(ColumnConstraint::class, $constraint);
        $this->assertInstanceOf(Constrainer::class, $constraint);
    }

    #[Test()]
    #[TestDox('ColumnConstraint fromData() preserves constraint parameters correctly')]
    #[Group('happy-path')]
    public function column_constraint_from_data_preserves_parameters(): void
    {
        // Arrange
        $entity = new User(['id' => 5, 'age' => 30]);
        $authority = new User(['id' => 5, 'age' => 30]);
        $data = [
            'a' => 'id',
            'operator' => '=',
            'b' => 'id',
            'logicalOperator' => 'or',
        ];

        // Act
        $constraint = ColumnConstraint::fromData($data);

        // Assert
        $this->assertTrue($constraint->check($entity, $authority));
        $this->assertTrue($constraint->isOr());
        $this->assertFalse($constraint->isAnd());
    }

    #[Test()]
    #[TestDox('ColumnConstraint fromData() throws exception for invalid column types')]
    #[Group('sad-path')]
    public function column_constraint_from_data_throws_for_invalid_columns(): void
    {
        // Arrange
        $data = [
            'a' => 'user_id',
            'operator' => '=',
            'b' => 123, // Invalid: should be string
        ];

        // Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Columns and operator must be strings');

        // Act
        ColumnConstraint::fromData($data);
    }

    // ========================================
    // data() Method Output Tests
    // ========================================

    #[Test()]
    #[TestDox('ValueConstraint data() returns array with class and params keys')]
    #[Group('happy-path')]
    public function value_constraint_data_returns_correct_structure(): void
    {
        // Arrange
        $constraint = new ValueConstraint('status', '=', 'published');

        // Act
        $data = $constraint->data();

        // Assert
        $this->assertIsArray($data);
        $this->assertArrayHasKey('class', $data);
        $this->assertArrayHasKey('params', $data);
        $this->assertSame(ValueConstraint::class, $data['class']);
    }

    #[Test()]
    #[TestDox('ValueConstraint data() includes all constraint parameters')]
    #[Group('happy-path')]
    public function value_constraint_data_includes_all_parameters(): void
    {
        // Arrange
        $constraint = new ValueConstraint('active', '!=', false);
        $constraint->logicalOperator('or');

        // Act
        $data = $constraint->data();

        // Assert
        $this->assertArrayHasKey('column', $data['params']);
        $this->assertArrayHasKey('operator', $data['params']);
        $this->assertArrayHasKey('value', $data['params']);
        $this->assertArrayHasKey('logicalOperator', $data['params']);
        $this->assertSame('active', $data['params']['column']);
        $this->assertSame('!=', $data['params']['operator']);
        $this->assertFalse($data['params']['value']);
        $this->assertSame('or', $data['params']['logicalOperator']);
    }

    #[Test()]
    #[TestDox('ColumnConstraint data() returns array with class and params keys')]
    #[Group('happy-path')]
    public function column_constraint_data_returns_correct_structure(): void
    {
        // Arrange
        $constraint = new ColumnConstraint('user_id', '=', 'id');

        // Act
        $data = $constraint->data();

        // Assert
        $this->assertIsArray($data);
        $this->assertArrayHasKey('class', $data);
        $this->assertArrayHasKey('params', $data);
        $this->assertSame(ColumnConstraint::class, $data['class']);
    }

    #[Test()]
    #[TestDox('ColumnConstraint data() includes all constraint parameters')]
    #[Group('happy-path')]
    public function column_constraint_data_includes_all_parameters(): void
    {
        // Arrange
        $constraint = new ColumnConstraint('age', '>', 'min_age');
        $constraint->logicalOperator('and');

        // Act
        $data = $constraint->data();

        // Assert
        $this->assertArrayHasKey('a', $data['params']);
        $this->assertArrayHasKey('operator', $data['params']);
        $this->assertArrayHasKey('b', $data['params']);
        $this->assertArrayHasKey('logicalOperator', $data['params']);
        $this->assertSame('age', $data['params']['a']);
        $this->assertSame('>', $data['params']['operator']);
        $this->assertSame('min_age', $data['params']['b']);
        $this->assertSame('and', $data['params']['logicalOperator']);
    }

    // ========================================
    // check() Method Tests
    // ========================================

    #[Test()]
    #[TestDox('ValueConstraint check() evaluates constraint against entity')]
    #[Group('happy-path')]
    public function value_constraint_check_evaluates_entity(): void
    {
        // Arrange
        $publishedPost = new Account(['status' => 'published']);
        $draftPost = new Account(['status' => 'draft']);
        $constraint = new ValueConstraint('status', '=', 'published');

        // Act & Assert
        $this->assertTrue($constraint->check($publishedPost));
        $this->assertFalse($constraint->check($draftPost));
    }

    #[Test()]
    #[TestDox('ValueConstraint check() works without authority parameter')]
    #[Group('happy-path')]
    public function value_constraint_check_works_without_authority(): void
    {
        // Arrange
        $activeAccount = new Account(['active' => true]);
        $constraint = new ValueConstraint('active', '=', true);

        // Act
        $result = $constraint->check($activeAccount);

        // Assert
        $this->assertTrue($result);
    }

    #[Test()]
    #[TestDox('ColumnConstraint check() compares entity and authority attributes')]
    #[Group('happy-path')]
    public function column_constraint_check_compares_entity_and_authority(): void
    {
        // Arrange
        $entity = new User(['user_id' => 42]);
        $authority = new User(['id' => 42]);
        $otherAuthority = new User(['id' => 99]);
        $constraint = new ColumnConstraint('user_id', '=', 'id');

        // Act & Assert
        $this->assertTrue($constraint->check($entity, $authority));
        $this->assertFalse($constraint->check($entity, $otherAuthority));
    }

    #[Test()]
    #[TestDox('ColumnConstraint check() returns false when authority is null')]
    #[Group('sad-path')]
    public function column_constraint_check_returns_false_without_authority(): void
    {
        // Arrange
        $entity = new User(['user_id' => 42]);
        $constraint = new ColumnConstraint('user_id', '=', 'id');

        // Act
        $result = $constraint->check($entity);

        // Assert
        $this->assertFalse($result);
    }

    #[Test()]
    #[TestDox('check() supports all comparison operators')]
    #[Group('edge-case')]
    public function check_supports_all_operators(): void
    {
        // Arrange
        $entity = new User(['age' => 25]);

        // Act & Assert
        $this->assertTrue(
            new ValueConstraint('age', '=', 25)->check($entity),
        );
        $this->assertTrue(
            new ValueConstraint('age', '==', 25)->check($entity),
        );
        $this->assertTrue(
            new ValueConstraint('age', '!=', 30)->check($entity),
        );
        $this->assertTrue(
            new ValueConstraint('age', '>', 20)->check($entity),
        );
        $this->assertTrue(
            new ValueConstraint('age', '<', 30)->check($entity),
        );
        $this->assertTrue(
            new ValueConstraint('age', '>=', 25)->check($entity),
        );
        $this->assertTrue(
            new ValueConstraint('age', '<=', 25)->check($entity),
        );
    }

    // ========================================
    // logicalOperator() Tests
    // ========================================

    #[Test()]
    #[TestDox('logicalOperator() returns current operator when called without arguments')]
    #[Group('happy-path')]
    public function logical_operator_getter_returns_current_value(): void
    {
        // Arrange
        $constraint = new ValueConstraint('status', '=', 'active');

        // Act
        $operator = $constraint->logicalOperator();

        // Assert
        $this->assertSame('and', $operator);
    }

    #[Test()]
    #[TestDox('logicalOperator() sets operator and returns instance for chaining')]
    #[Group('happy-path')]
    public function logical_operator_setter_enables_fluent_interface(): void
    {
        // Arrange
        $constraint = new ValueConstraint('status', '=', 'active');

        // Act
        $result = $constraint->logicalOperator('or');

        // Assert
        $this->assertSame($constraint, $result);
        $this->assertSame('or', $constraint->logicalOperator());
    }

    #[Test()]
    #[TestDox('logicalOperator() accepts AND operator')]
    #[Group('happy-path')]
    public function logical_operator_accepts_and(): void
    {
        // Arrange
        $constraint = new ValueConstraint('status', '=', 'active');
        $constraint->logicalOperator('or');

        // Act
        $constraint->logicalOperator('and');

        // Assert
        $this->assertSame('and', $constraint->logicalOperator());
    }

    #[Test()]
    #[TestDox('logicalOperator() accepts OR operator')]
    #[Group('happy-path')]
    public function logical_operator_accepts_or(): void
    {
        // Arrange
        $constraint = new ValueConstraint('status', '=', 'active');

        // Act
        $constraint->logicalOperator('or');

        // Assert
        $this->assertSame('or', $constraint->logicalOperator());
    }

    #[Test()]
    #[TestDox('logicalOperator() throws exception for invalid operator')]
    #[Group('sad-path')]
    public function logical_operator_throws_for_invalid_operator(): void
    {
        // Arrange
        $constraint = new ValueConstraint('status', '=', 'active');

        // Assert
        $this->expectException(InvalidArgumentException::class);

        // Act
        $constraint->logicalOperator('xor');
    }

    // ========================================
    // isAnd() and isOr() Tests
    // ========================================

    #[Test()]
    #[TestDox('isAnd() returns true when logical operator is AND')]
    #[Group('happy-path')]
    public function is_and_returns_true_for_and_operator(): void
    {
        // Arrange
        $constraint = new ValueConstraint('status', '=', 'active');
        $constraint->logicalOperator('and');

        // Act
        $result = $constraint->isAnd();

        // Assert
        $this->assertTrue($result);
        $this->assertFalse($constraint->isOr());
    }

    #[Test()]
    #[TestDox('isAnd() returns false when logical operator is OR')]
    #[Group('happy-path')]
    public function is_and_returns_false_for_or_operator(): void
    {
        // Arrange
        $constraint = new ValueConstraint('status', '=', 'active');
        $constraint->logicalOperator('or');

        // Act
        $result = $constraint->isAnd();

        // Assert
        $this->assertFalse($result);
    }

    #[Test()]
    #[TestDox('isOr() returns true when logical operator is OR')]
    #[Group('happy-path')]
    public function is_or_returns_true_for_or_operator(): void
    {
        // Arrange
        $constraint = new ValueConstraint('status', '=', 'active');
        $constraint->logicalOperator('or');

        // Act
        $result = $constraint->isOr();

        // Assert
        $this->assertTrue($result);
        $this->assertFalse($constraint->isAnd());
    }

    #[Test()]
    #[TestDox('isOr() returns false when logical operator is AND')]
    #[Group('happy-path')]
    public function is_or_returns_false_for_and_operator(): void
    {
        // Arrange
        $constraint = new ValueConstraint('status', '=', 'active');
        $constraint->logicalOperator('and');

        // Act
        $result = $constraint->isOr();

        // Assert
        $this->assertFalse($result);
    }

    #[Test()]
    #[TestDox('isAnd() defaults to true for new constraints')]
    #[Group('edge-case')]
    public function is_and_defaults_to_true(): void
    {
        // Arrange
        $constraint = new ValueConstraint('status', '=', 'active');

        // Act & Assert
        $this->assertTrue($constraint->isAnd());
        $this->assertFalse($constraint->isOr());
    }

    // ========================================
    // equals() Comparison Tests
    // ========================================

    #[Test()]
    #[TestDox('ValueConstraint equals() returns true for identical constraints')]
    #[Group('happy-path')]
    public function value_constraint_equals_returns_true_for_identical(): void
    {
        // Arrange
        $constraint1 = new ValueConstraint('status', '=', 'published');
        $constraint1->logicalOperator('and');

        $constraint2 = new ValueConstraint('status', '=', 'published');
        $constraint2->logicalOperator('and');

        // Act
        $result = $constraint1->equals($constraint2);

        // Assert
        $this->assertTrue($result);
    }

    #[Test()]
    #[TestDox('ValueConstraint equals() returns false for different column names')]
    #[Group('happy-path')]
    public function value_constraint_equals_returns_false_for_different_columns(): void
    {
        // Arrange
        $constraint1 = new ValueConstraint('status', '=', 'published');
        $constraint2 = new ValueConstraint('state', '=', 'published');

        // Act
        $result = $constraint1->equals($constraint2);

        // Assert
        $this->assertFalse($result);
    }

    #[Test()]
    #[TestDox('ValueConstraint equals() returns false for different operators')]
    #[Group('happy-path')]
    public function value_constraint_equals_returns_false_for_different_operators(): void
    {
        // Arrange
        $constraint1 = new ValueConstraint('age', '>', 18);
        $constraint2 = new ValueConstraint('age', '<', 18);

        // Act
        $result = $constraint1->equals($constraint2);

        // Assert
        $this->assertFalse($result);
    }

    #[Test()]
    #[TestDox('ValueConstraint equals() returns false for different values')]
    #[Group('happy-path')]
    public function value_constraint_equals_returns_false_for_different_values(): void
    {
        // Arrange
        $constraint1 = new ValueConstraint('status', '=', 'published');
        $constraint2 = new ValueConstraint('status', '=', 'draft');

        // Act
        $result = $constraint1->equals($constraint2);

        // Assert
        $this->assertFalse($result);
    }

    #[Test()]
    #[TestDox('ValueConstraint equals() returns false for different logical operators')]
    #[Group('happy-path')]
    public function value_constraint_equals_returns_false_for_different_logical_operators(): void
    {
        // Arrange
        $constraint1 = new ValueConstraint('status', '=', 'published');
        $constraint1->logicalOperator('and');

        $constraint2 = new ValueConstraint('status', '=', 'published');
        $constraint2->logicalOperator('or');

        // Act
        $result = $constraint1->equals($constraint2);

        // Assert
        $this->assertFalse($result);
    }

    #[Test()]
    #[TestDox('ColumnConstraint equals() returns true for identical constraints')]
    #[Group('happy-path')]
    public function column_constraint_equals_returns_true_for_identical(): void
    {
        // Arrange
        $constraint1 = new ColumnConstraint('user_id', '=', 'id');
        $constraint1->logicalOperator('and');

        $constraint2 = new ColumnConstraint('user_id', '=', 'id');
        $constraint2->logicalOperator('and');

        // Act
        $result = $constraint1->equals($constraint2);

        // Assert
        $this->assertTrue($result);
    }

    #[Test()]
    #[TestDox('ColumnConstraint equals() returns false for different column A')]
    #[Group('happy-path')]
    public function column_constraint_equals_returns_false_for_different_column_a(): void
    {
        // Arrange
        $constraint1 = new ColumnConstraint('user_id', '=', 'id');
        $constraint2 = new ColumnConstraint('owner_id', '=', 'id');

        // Act
        $result = $constraint1->equals($constraint2);

        // Assert
        $this->assertFalse($result);
    }

    #[Test()]
    #[TestDox('ColumnConstraint equals() returns false for different column B')]
    #[Group('happy-path')]
    public function column_constraint_equals_returns_false_for_different_column_b(): void
    {
        // Arrange
        $constraint1 = new ColumnConstraint('age', '=', 'min_age');
        $constraint2 = new ColumnConstraint('age', '=', 'max_age');

        // Act
        $result = $constraint1->equals($constraint2);

        // Assert
        $this->assertFalse($result);
    }

    #[Test()]
    #[TestDox('equals() returns false when comparing different constraint types')]
    #[Group('edge-case')]
    public function equals_returns_false_for_different_constraint_types(): void
    {
        // Arrange
        $valueConstraint = new ValueConstraint('status', '=', 'active');
        $columnConstraint = new ColumnConstraint('status', '=', 'state');

        // Act
        $result1 = $valueConstraint->equals($columnConstraint);
        $result2 = $columnConstraint->equals($valueConstraint);

        // Assert
        $this->assertFalse($result1);
        $this->assertFalse($result2);
    }

    // ========================================
    // Round-trip Serialization Tests
    // ========================================

    #[Test()]
    #[TestDox('ValueConstraint survives round-trip serialization/deserialization')]
    #[Group('edge-case')]
    public function value_constraint_survives_round_trip_serialization(): void
    {
        // Arrange
        $original = new ValueConstraint('active', '!=', false);
        $original->logicalOperator('or');

        $entity = new Account(['active' => true]);

        // Act
        $data = $original->data();
        $reconstituted = ValueConstraint::fromData($data['params']);

        // Assert
        $this->assertTrue($original->equals($reconstituted));
        $this->assertSame($original->check($entity), $reconstituted->check($entity));
        $this->assertSame($original->logicalOperator(), $reconstituted->logicalOperator());
    }

    #[Test()]
    #[TestDox('ColumnConstraint survives round-trip serialization/deserialization')]
    #[Group('edge-case')]
    public function column_constraint_survives_round_trip_serialization(): void
    {
        // Arrange
        $original = new ColumnConstraint('age', '>=', 'min_age');
        $original->logicalOperator('and');

        $entity = new User(['age' => 30]);
        $authority = new User(['min_age' => 25]);

        // Act
        $data = $original->data();
        $reconstituted = ColumnConstraint::fromData($data['params']);

        // Assert
        $this->assertTrue($original->equals($reconstituted));
        $this->assertSame($original->check($entity, $authority), $reconstituted->check($entity, $authority));
        $this->assertSame($original->logicalOperator(), $reconstituted->logicalOperator());
    }

    #[Test()]
    #[TestDox('Constrainer implementations handle complex value types')]
    #[Group('edge-case')]
    public function constrainer_handles_complex_value_types(): void
    {
        // Arrange
        $entity1 = new Account(['tags' => ['php', 'laravel']]);
        $entity2 = new Account(['tags' => ['javascript', 'vue']]);

        // Act
        $constraint = new ValueConstraint('tags', '=', ['php', 'laravel']);

        // Assert
        $this->assertTrue($constraint->check($entity1));
        $this->assertFalse($constraint->check($entity2));
    }

    #[Test()]
    #[TestDox('Constrainer implementations handle null values correctly')]
    #[Group('edge-case')]
    public function constrainer_handles_null_values(): void
    {
        // Arrange
        $entityWithNull = new Account(['description' => null]);
        $entityWithValue = new Account(['description' => 'Some text']);

        // Act
        $constraint = new ValueConstraint('description', '=', null);

        // Assert
        $this->assertTrue($constraint->check($entityWithNull));
        $this->assertFalse($constraint->check($entityWithValue));
    }

    #[Test()]
    #[TestDox('Constrainer implementations enforce strict type comparison')]
    #[Group('edge-case')]
    public function constrainer_enforces_strict_type_comparison(): void
    {
        // Arrange
        $entityWithString = new Account(['count' => '10']);
        $entityWithInt = new Account(['count' => 10]);

        // Act
        $stringConstraint = new ValueConstraint('count', '=', '10');
        $intConstraint = new ValueConstraint('count', '=', 10);

        // Assert
        $this->assertTrue($stringConstraint->check($entityWithString));
        $this->assertFalse($stringConstraint->check($entityWithInt));
        $this->assertFalse($intConstraint->check($entityWithString));
        $this->assertTrue($intConstraint->check($entityWithInt));
    }
}
