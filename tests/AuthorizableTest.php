<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use Cline\Warden\Database\Role;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\TestsClipboards;
use Tests\Fixtures\Models\Account;
use Tests\Fixtures\Models\User;

/**
 * @internal
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class AuthorizableTest extends TestCase
{
    use TestsClipboards;

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function checking_simple_abilities_on_roles($provider): void
    {
        $provider();

        $role = Role::query()->create(['name' => 'admin']);

        $role->allow('scream');

        $this->assertTrue($role->can('scream'));
        $this->assertTrue($role->cant('shout'));
        $this->assertTrue($role->cannot('cry'));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function checking_model_abilities_on_roles($provider): void
    {
        $provider();

        $role = Role::query()->create(['name' => 'admin']);

        $role->allow('create', User::class);

        $this->assertTrue($role->can('create', User::class));
        $this->assertTrue($role->cannot('create', Account::class));
        $this->assertTrue($role->cannot('update', User::class));
        $this->assertTrue($role->cannot('create'));
    }
}
