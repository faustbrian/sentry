<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use Cline\Warden\Constraints\Constraint;
use Cline\Warden\Constraints\Group;
use Cline\Warden\Database\Ability;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Models\Account;
use Tests\Fixtures\Models\User;

/**
 * @internal
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class AbilityConstraintsTest extends TestCase
{
    #[Test()]
    public function can_get_empty_constraints(): void
    {
        $group = Ability::createForModel(Account::class, '*')->getConstraints();

        $this->assertInstanceOf(Group::class, $group);
    }

    #[Test()]
    public function can_check_if_has_constraints(): void
    {
        $empty = Ability::makeForModel(Account::class, '*');

        $full = Ability::makeForModel(Account::class, '*')->setConstraints(
            Group::withAnd()->add(Constraint::where('active', true)),
        );

        $this->assertFalse($empty->hasConstraints());
        $this->assertTrue($full->hasConstraints());
    }

    #[Test()]
    public function can_set_and_get_constraints(): void
    {
        $ability = Ability::makeForModel(Account::class, '*')->setConstraints(
            new Group([
                Constraint::where('active', true),
            ]),
        );

        $ability->save();

        $constraints = Ability::query()->find($ability->id)->getConstraints();

        $this->assertInstanceOf(Group::class, $constraints);
        $this->assertTrue($constraints->check(
            new Account(['active' => true]),
            new User(),
        ));
        $this->assertFalse($constraints->check(
            new Account(['active' => false]),
            new User(),
        ));
    }
}
