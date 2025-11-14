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

/**
 * @internal
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class TablePrefixTest extends TestCase
{
    use TestsClipboards;

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function test_ability_queries_work_with_prefix($provider): void
    {
        [$warden, $user] = $provider();

        $warden->allow($user)->everything();

        $this->assertTrue($warden->can('do-something'));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function test_role_queries_work_with_prefix($provider): void
    {
        [$warden, $user] = $provider();

        $warden->assign('artisan')->to($user);

        $this->assertTrue($warden->is($user)->an('artisan'));
    }
}
