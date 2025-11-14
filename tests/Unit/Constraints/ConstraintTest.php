<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Constraints;

use Cline\Warden\Constraints\ColumnConstraint;
use Cline\Warden\Constraints\Constraint;
use Cline\Warden\Constraints\ValueConstraint;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\Models\Account;
use Tests\Fixtures\Models\User;

use function json_decode;
use function json_encode;

/**
 * @internal
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ConstraintTest extends TestCase
{
    #[Test()]
    public function value_constraint_implicit_equals(): void
    {
        $authority = new User();
        $activeAccount = new Account(['active' => true]);
        $inactiveAccount = new Account(['active' => false]);

        $constraint = Constraint::where('active', true);

        $this->assertTrue($constraint->check($activeAccount, $authority));
        $this->assertFalse($constraint->check($inactiveAccount, $authority));
    }

    #[Test()]
    public function value_constraint_explicit_equals(): void
    {
        $authority = new User();
        $activeAccount = new Account(['active' => true]);
        $inactiveAccount = new Account(['active' => false]);

        $constraint = Constraint::where('active', '=', true);

        $this->assertTrue($constraint->check($activeAccount, $authority));
        $this->assertFalse($constraint->check($inactiveAccount, $authority));
    }

    #[Test()]
    public function value_constraint_explicit_double_equals(): void
    {
        $authority = new User();
        $activeAccount = new Account(['active' => true]);
        $inactiveAccount = new Account(['active' => false]);

        $constraint = Constraint::where('active', '==', true);

        $this->assertTrue($constraint->check($activeAccount, $authority));
        $this->assertFalse($constraint->check($inactiveAccount, $authority));
    }

    #[Test()]
    public function value_constraint_not_equals(): void
    {
        $authority = new User();
        $activeAccount = new Account(['active' => true]);
        $inactiveAccount = new Account(['active' => false]);

        $constraint = Constraint::where('active', '!=', false);

        $this->assertTrue($constraint->check($activeAccount, $authority));
        $this->assertFalse($constraint->check($inactiveAccount, $authority));
    }

    #[Test()]
    public function value_constraint_greater_than(): void
    {
        $authority = new User();
        $forty = new User(['age' => 40]);
        $fortyOne = new User(['age' => 41]);

        $constraint = Constraint::where('age', '>', 40);

        $this->assertTrue($constraint->check($fortyOne, $authority));
        $this->assertFalse($constraint->check($forty, $authority));
    }

    #[Test()]
    public function value_constraint_less_than(): void
    {
        $authority = new User();
        $thirtyNine = new User(['age' => 39]);
        $forty = new User(['age' => 40]);

        $constraint = Constraint::where('age', '<', 40);

        $this->assertTrue($constraint->check($thirtyNine, $authority));
        $this->assertFalse($constraint->check($forty, $authority));
    }

    #[Test()]
    public function value_constraint_greater_than_or_equal(): void
    {
        $authority = new User();
        $minor = new User(['age' => 17]);
        $adult = new User(['age' => 18]);
        $senior = new User(['age' => 80]);

        $constraint = Constraint::where('age', '>=', 18);

        $this->assertTrue($constraint->check($adult, $authority));
        $this->assertTrue($constraint->check($senior, $authority));
        $this->assertFalse($constraint->check($minor, $authority));
    }

    #[Test()]
    public function value_constraint_less_than_or_equal(): void
    {
        $authority = new User();
        $youngerTeen = new User(['age' => 18]);
        $olderTeen = new User(['age' => 19]);
        $adult = new User(['age' => 20]);

        $constraint = Constraint::where('age', '<=', 19);

        $this->assertTrue($constraint->check($youngerTeen, $authority));
        $this->assertTrue($constraint->check($olderTeen, $authority));
        $this->assertFalse($constraint->check($adult, $authority));
    }

    #[Test()]
    public function column_constraint_implicit_equals(): void
    {
        $authority = new User(['age' => 1]);
        $one = new User(['age' => 1]);
        $two = new User(['age' => 2]);

        $constraint = Constraint::whereColumn('age', 'age');

        $this->assertTrue($constraint->check($one, $authority));
        $this->assertFalse($constraint->check($two, $authority));
    }

    #[Test()]
    public function column_constraint_explicit_equals(): void
    {
        $authority = new User(['age' => 1]);
        $one = new User(['age' => 1]);
        $two = new User(['age' => 2]);

        $constraint = Constraint::whereColumn('age', '=', 'age');

        $this->assertTrue($constraint->check($one, $authority));
        $this->assertFalse($constraint->check($two, $authority));
    }

    #[Test()]
    public function column_constraint_explicit_double_equals(): void
    {
        $authority = new User(['age' => 1]);
        $one = new User(['age' => 1]);
        $two = new User(['age' => 2]);

        $constraint = Constraint::whereColumn('age', '=', 'age');

        $this->assertTrue($constraint->check($one, $authority));
        $this->assertFalse($constraint->check($two, $authority));
    }

    #[Test()]
    public function column_constraint_not_equals(): void
    {
        $authority = new User(['age' => 1]);
        $one = new User(['age' => 1]);
        $two = new User(['age' => 2]);

        $constraint = Constraint::whereColumn('age', '!=', 'age');

        $this->assertTrue($constraint->check($two, $authority));
        $this->assertFalse($constraint->check($one, $authority));
    }

    #[Test()]
    public function column_constraint_greater_than(): void
    {
        $authority = new User(['age' => 18]);

        $younger = new User(['age' => 17]);
        $same = new User(['age' => 18]);
        $older = new User(['age' => 19]);

        $constraint = Constraint::whereColumn('age', '>', 'age');

        $this->assertTrue($constraint->check($older, $authority));
        $this->assertFalse($constraint->check($younger, $authority));
        $this->assertFalse($constraint->check($same, $authority));
    }

    #[Test()]
    public function column_constraint_less_than(): void
    {
        $authority = new User(['age' => 18]);

        $younger = new User(['age' => 17]);
        $same = new User(['age' => 18]);
        $older = new User(['age' => 19]);

        $constraint = Constraint::whereColumn('age', '<', 'age');

        $this->assertTrue($constraint->check($younger, $authority));
        $this->assertFalse($constraint->check($older, $authority));
        $this->assertFalse($constraint->check($same, $authority));
    }

    #[Test()]
    public function column_constraint_greater_than_or_equal(): void
    {
        $authority = new User(['age' => 18]);

        $younger = new User(['age' => 17]);
        $same = new User(['age' => 18]);
        $older = new User(['age' => 19]);

        $constraint = Constraint::whereColumn('age', '>=', 'age');

        $this->assertTrue($constraint->check($same, $authority));
        $this->assertTrue($constraint->check($older, $authority));
        $this->assertFalse($constraint->check($younger, $authority));
    }

    #[Test()]
    public function column_constraint_less_than_or_equal(): void
    {
        $authority = new User(['age' => 18]);

        $younger = new User(['age' => 17]);
        $same = new User(['age' => 18]);
        $older = new User(['age' => 19]);

        $constraint = Constraint::whereColumn('age', '<=', 'age');

        $this->assertTrue($constraint->check($younger, $authority));
        $this->assertTrue($constraint->check($same, $authority));
        $this->assertFalse($constraint->check($older, $authority));
    }

    #[Test()]
    public function value_constraint_can_be_properly_serialized_and_deserialized(): void
    {
        $authority = new User();
        $activeAccount = new Account(['active' => true]);
        $inactiveAccount = new Account(['active' => false]);

        $constraint = $this->serializeAndDeserializeConstraint(
            Constraint::where('active', true),
        );

        $this->assertInstanceOf(ValueConstraint::class, $constraint);
        $this->assertTrue($constraint->check($activeAccount, $authority));
        $this->assertFalse($constraint->check($inactiveAccount, $authority));
    }

    #[Test()]
    public function column_constraint_can_be_properly_serialized_and_deserialized(): void
    {
        $authority = new User(['age' => 1]);
        $one = new User(['age' => 1]);
        $two = new User(['age' => 2]);

        $constraint = $this->serializeAndDeserializeConstraint(
            Constraint::whereColumn('age', 'age'),
        );

        $this->assertInstanceOf(ColumnConstraint::class, $constraint);
        $this->assertTrue($constraint->check($one, $authority));
        $this->assertFalse($constraint->check($two, $authority));
    }

    #[Test()]
    public function or_where_column_creates_or_constraint(): void
    {
        $authority = new User(['age' => 1]);
        $one = new User(['age' => 1]);
        $two = new User(['age' => 2]);

        $constraint = Constraint::orWhereColumn('age', '=', 'age');

        $this->assertInstanceOf(ColumnConstraint::class, $constraint);
        $this->assertSame('or', $constraint->logicalOperator());
        $this->assertTrue($constraint->check($one, $authority));
        $this->assertFalse($constraint->check($two, $authority));
    }

    #[Test()]
    public function or_where_column_with_operator_creates_or_constraint(): void
    {
        $authority = new User(['age' => 18]);
        $younger = new User(['age' => 17]);
        $older = new User(['age' => 19]);

        $constraint = Constraint::orWhereColumn('age', '>', 'age');

        $this->assertInstanceOf(ColumnConstraint::class, $constraint);
        $this->assertSame('or', $constraint->logicalOperator());
        $this->assertTrue($constraint->check($older, $authority));
        $this->assertFalse($constraint->check($younger, $authority));
    }

    #[Test()]
    public function logical_operator_throws_exception_when_integer_provided(): void
    {
        $constraint = Constraint::where('active', true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Logical operator must be a string, int given');

        $constraint->logicalOperator(123);
    }

    #[Test()]
    public function logical_operator_throws_exception_when_array_provided(): void
    {
        $constraint = Constraint::where('active', true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Logical operator must be a string, array given');

        $constraint->logicalOperator(['and']);
    }

    #[Test()]
    public function logical_operator_throws_exception_when_object_provided(): void
    {
        $constraint = Constraint::where('active', true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Logical operator must be a string, stdClass given');

        $constraint->logicalOperator((object) ['operator' => 'and']);
    }

    /**
     * Convert the given object to JSON, then back.
     *
     * @return Constraint
     */
    private function serializeAndDeserializeConstraint(Constraint $constraint)
    {
        $data = json_decode(json_encode($constraint->data()), true);

        return $data['class']::fromData($data['params']);
    }
}
