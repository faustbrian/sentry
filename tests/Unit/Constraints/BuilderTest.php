<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Constraints;

use Cline\Warden\Constraints\Builder;
use Cline\Warden\Constraints\Constraint;
use Cline\Warden\Constraints\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class BuilderTest extends TestCase
{
    #[Test()]
    public function building_without_constraints_returns_empty_group(): void
    {
        $actual = new Builder()->build();

        $expected = new Group();

        $this->assertTrue($expected->equals($actual));
    }

    #[Test()]
    public function a_single_where_returns_a_single_constraint(): void
    {
        $constraint = Builder::make()->where('active', false)->build();

        $this->assertTrue($constraint->equals(Constraint::where('active', false)));
    }

    #[Test()]
    public function a_single_where_column_returns_a_single_column_constraint(): void
    {
        $builder = Builder::make()->whereColumn('team_id', 'team_id');

        $expected = Constraint::whereColumn('team_id', 'team_id');

        $this->assertTrue($expected->equals($builder->build()));
    }

    #[Test()]
    public function a_single_or_where_returns_a_single_or_constraint(): void
    {
        $actual = Builder::make()->orWhere('active', '=', false)->build();

        $expected = Constraint::orWhere('active', '=', false);

        $this->assertTrue($expected->equals($actual));
    }

    #[Test()]
    public function two_wheres_return_a_group(): void
    {
        $builder = Builder::make()
            ->where('active', false)
            ->where('age', '>=', 18);

        $expected = new Group()
            ->add(Constraint::where('active', false))
            ->add(Constraint::where('age', '>=', 18));

        $this->assertTrue($expected->equals($builder->build()));
    }

    #[Test()]
    public function two_where_columns_return_a_group(): void
    {
        $builder = Builder::make()
            ->whereColumn('active', 'other_active')
            ->whereColumn('age', '>=', 'min_age');

        $expected = new Group()
            ->add(Constraint::whereColumn('active', 'other_active'))
            ->add(Constraint::whereColumn('age', '>=', 'min_age'));

        $this->assertTrue($expected->equals($builder->build()));
    }

    #[Test()]
    public function or_wheres_return_a_group(): void
    {
        $builder = Builder::make()
            ->where('active', false)
            ->orWhere('age', '>=', 18);

        $expected = new Group()
            ->add(Constraint::where('active', false))
            ->add(Constraint::orWhere('age', '>=', 18));

        $this->assertTrue($expected->equals($builder->build()));
    }

    #[Test()]
    public function nested_wheres_return_a_group(): void
    {
        $builder = Builder::make()->where('active', false)->where(function ($query): void {
            $query->where('a', 'b')->where('c', 'd');
        });

        $expected = new Group()
            ->add(Constraint::where('active', false))
            ->add(
                new Group()
                    ->add(Constraint::where('a', 'b'))
                    ->add(Constraint::where('c', 'd')),
            );

        $this->assertTrue($expected->equals($builder->build()));
    }

    #[Test()]
    public function nested_or_where_returns_an_or_group(): void
    {
        $builder = Builder::make()->where('active', false)->orWhere(function ($query): void {
            $query->where('a', 'b')->where('c', 'd');
        });

        $expected = new Group()
            ->add(Constraint::where('active', false))
            ->add(
                Group::withOr()
                    ->add(Constraint::where('a', 'b'))
                    ->add(Constraint::where('c', 'd')),
            );

        $this->assertTrue($expected->equals($builder->build()));
    }

    #[Test()]
    public function can_nest_multiple_levels(): void
    {
        $builder = Builder::make()
            ->where('active', false)
            ->orWhere(function ($query): void {
                $query->where('a', 'b')->where('c', 'd')->where(function ($query): void {
                    $query->where('1', '=', '2')->orWhere('3', '=', '4');
                });
            });

        $expected = new Group()
            ->add(Constraint::where('active', false))
            ->add(
                Group::withOr()
                    ->add(Constraint::where('a', 'b'))
                    ->add(Constraint::where('c', 'd'))
                    ->add(
                        Group::withAnd()
                            ->add(Constraint::where('1', '=', '2'))
                            ->add(Constraint::orWhere('3', '=', '4')),
                    ),
            );

        $this->assertTrue($expected->equals($builder->build()));
    }

    #[Test()]
    public function or_where_column_returns_constraint_with_or_logic(): void
    {
        $builder = Builder::make()->orWhereColumn('team_id', '=', 'team_id');

        $expected = Constraint::orWhereColumn('team_id', '=', 'team_id');

        $this->assertTrue($expected->equals($builder->build()));
    }

    #[Test()]
    public function or_where_column_with_operator_returns_constraint(): void
    {
        $builder = Builder::make()->orWhereColumn('team_id', '!=', 'other_team_id');

        $expected = Constraint::orWhereColumn('team_id', '!=', 'other_team_id');

        $this->assertTrue($expected->equals($builder->build()));
    }
}
