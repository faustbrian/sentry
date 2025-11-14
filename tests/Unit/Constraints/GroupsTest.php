<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Constraints;

use Cline\Warden\Constraints\Constraint;
use Cline\Warden\Constraints\Group;
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
final class GroupsTest extends TestCase
{
    #[Test()]
    public function named_and_constructor(): void
    {
        $group = Group::withAnd();

        $this->assertInstanceOf(Group::class, $group);
        $this->assertSame('and', $group->logicalOperator());
    }

    #[Test()]
    public function named_or_constructor(): void
    {
        $group = Group::withOr();

        $this->assertInstanceOf(Group::class, $group);
        $this->assertSame('or', $group->logicalOperator());
    }

    #[Test()]
    public function group_of_constraints_only_passes_if_all_constraints_pass_the_check(): void
    {
        $account = new Account([
            'name' => 'the-account',
            'active' => false,
        ]);

        $groupA = new Group([
            Constraint::where('name', 'the-account'),
            Constraint::where('active', false),
        ]);

        $groupB = new Group([
            Constraint::where('name', 'the-account'),
            Constraint::where('active', true),
        ]);

        $this->assertTrue($groupA->check($account, new User()));
        $this->assertFalse($groupB->check($account, new User()));
    }

    #[Test()]
    public function group_of_ors_passes_if_any_constraint_passes_the_check(): void
    {
        $account = new Account([
            'name' => 'the-account',
            'active' => false,
        ]);

        $groupA = new Group([
            Constraint::orWhere('name', '=', 'the-account'),
            Constraint::orWhere('active', '=', true),
        ]);

        $groupB = new Group([
            Constraint::orWhere('name', '=', 'a-different-account'),
            Constraint::orWhere('active', '=', true),
        ]);

        $this->assertTrue($groupA->check($account, new User()));
        $this->assertFalse($groupB->check($account, new User()));
    }

    #[Test()]
    public function group_can_be_serialized_and_deserialized(): void
    {
        $activeAccount = new Account([
            'name' => 'the-account',
            'active' => true,
        ]);

        $inactiveAccount = new Account([
            'name' => 'the-account',
            'active' => false,
        ]);

        $group = $this->serializeAndDeserializeGroup(
            new Group([
                Constraint::where('name', 'the-account'),
                Constraint::where('active', true),
            ]),
        );

        $this->assertInstanceOf(Group::class, $group);
        $this->assertTrue($group->check($activeAccount, new User()));
        $this->assertFalse($group->check($inactiveAccount, new User()));
    }

    #[Test()]
    public function group_can_be_added_to(): void
    {
        $activeAccount = new Account([
            'name' => 'account',
            'active' => true,
        ]);

        $inactiveAccount = new Account([
            'name' => 'account',
            'active' => false,
        ]);

        $group = new Group()
            ->add(Constraint::where('name', 'account'))
            ->add(Constraint::where('active', true));

        $this->assertTrue($group->check($activeAccount, new User()));
        $this->assertFalse($group->check($inactiveAccount, new User()));
    }

    #[Test()]
    public function group_checks_if_it_is_and(): void
    {
        $group = Group::withAnd();

        $this->assertTrue($group->isAnd());
        $this->assertFalse($group->isOr());
    }

    #[Test()]
    public function group_checks_if_it_is_or(): void
    {
        $group = Group::withOr();

        $this->assertTrue($group->isOr());
        $this->assertFalse($group->isAnd());
    }

    #[Test()]
    public function check_with_empty_group_returns_true(): void
    {
        $account = new Account([
            'name' => 'test-account',
            'active' => false,
        ]);

        $emptyGroup = new Group();

        $this->assertTrue($emptyGroup->check($account, new User()));
    }

    #[Test()]
    public function logical_operator_with_non_string_type_throws_exception(): void
    {
        $group = new Group();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Logical operator must be a string, got integer');

        $group->logicalOperator(123);
    }

    #[Test()]
    public function equals_with_non_group_constrainer_returns_false(): void
    {
        $group = new Group([
            Constraint::where('name', 'test'),
        ]);

        $constraint = Constraint::where('name', 'test');

        $this->assertFalse($group->equals($constraint));
    }

    #[Test()]
    public function equals_with_different_constraint_count_returns_false(): void
    {
        $groupA = new Group([
            Constraint::where('name', 'test'),
            Constraint::where('active', true),
        ]);

        $groupB = new Group([
            Constraint::where('name', 'test'),
        ]);

        $this->assertFalse($groupA->equals($groupB));
        $this->assertFalse($groupB->equals($groupA));
    }

    #[Test()]
    public function equals_with_mismatched_constraints_returns_false(): void
    {
        $groupA = new Group([
            Constraint::where('name', 'test-a'),
            Constraint::where('active', true),
        ]);

        $groupB = new Group([
            Constraint::where('name', 'test-b'),
            Constraint::where('active', true),
        ]);

        $this->assertFalse($groupA->equals($groupB));
    }

    /**
     * Convert the given object to JSON, then back.
     *
     * @return Group
     */
    private function serializeAndDeserializeGroup(Group $group)
    {
        $data = json_decode(json_encode($group->data()), true);

        return $data['class']::fromData($data['params']);
    }
}
