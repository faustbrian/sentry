<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\TestsClipboards;
use Tests\Fixtures\Models\Account;

/**
 * @internal
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class CustomAuthorityTest extends TestCase
{
    use TestsClipboards;

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function can_give_and_remove_abilities($provider): void
    {
        [$warden, $account] = $provider(1, Account::class);

        $warden->allow($account)->to('edit-site');

        $this->assertTrue($warden->can('edit-site'));

        $warden->disallow($account)->to('edit-site');

        $this->assertTrue($warden->cannot('edit-site'));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function can_give_and_remove_roles($provider): void
    {
        [$warden, $account] = $provider(1, Account::class);

        $warden->allow('admin')->to('edit-site');
        $warden->assign('admin')->to($account);

        $editor = $warden->role()->create(['name' => 'editor']);
        $warden->allow($editor)->to('edit-site');
        $warden->assign($editor)->to($account);

        $this->assertTrue($warden->can('edit-site'));

        $warden->retract('admin')->from($account);
        $warden->retract($editor)->from($account);

        $this->assertTrue($warden->cannot('edit-site'));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function can_disallow_abilities_on_roles($provider): void
    {
        [$warden, $account] = $provider(1, Account::class);

        $warden->allow('admin')->to('edit-site');
        $warden->disallow('admin')->to('edit-site');
        $warden->assign('admin')->to($account);

        $this->assertTrue($warden->cannot('edit-site'));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function can_check_roles($provider): void
    {
        [$warden, $account] = $provider(1, Account::class);

        $this->assertTrue($warden->is($account)->notA('moderator'));
        $this->assertTrue($warden->is($account)->notAn('editor'));
        $this->assertFalse($warden->is($account)->an('admin'));

        $warden = $this->bouncer($account = Account::query()->create());

        $warden->assign('moderator')->to($account);
        $warden->assign('editor')->to($account);

        $this->assertTrue($warden->is($account)->a('moderator'));
        $this->assertTrue($warden->is($account)->an('editor'));
        $this->assertFalse($warden->is($account)->notAn('editor'));
        $this->assertFalse($warden->is($account)->an('admin'));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function can_check_multiple_roles($provider): void
    {
        [$warden, $account] = $provider(1, Account::class);

        $this->assertTrue($warden->is($account)->notAn('editor', 'moderator'));
        $this->assertTrue($warden->is($account)->notAn('admin', 'moderator'));

        $warden = $this->bouncer($account = Account::query()->create());
        $warden->assign('moderator')->to($account);
        $warden->assign('editor')->to($account);

        $this->assertTrue($warden->is($account)->a('subscriber', 'moderator'));
        $this->assertTrue($warden->is($account)->an('admin', 'editor'));
        $this->assertTrue($warden->is($account)->all('editor', 'moderator'));
        $this->assertFalse($warden->is($account)->notAn('editor', 'moderator'));
        $this->assertFalse($warden->is($account)->all('admin', 'moderator'));
    }
}
