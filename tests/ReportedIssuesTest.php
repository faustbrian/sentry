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
final class ReportedIssuesTest extends TestCase
{
    use TestsClipboards;

    /**
     * @see https://github.com/JosephSilber/bouncer/pull/589
     */
    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function forbid_an_ability_on_everything_with_zero_id($provider): void
    {
        [$warden, $user1, $user2, $user3] = $provider(3);

        $user2->setAttribute($user2->getKeyName(), 0);

        $warden->allow($user1)->everything();
        $warden->forbid($user1)->to('edit', $user2);

        $this->assertTrue($warden->cannot('edit', $user2));
        $this->assertTrue($warden->can('edit', $user3));
    }
}
