<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use Cline\Warden\Warden as WardenClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\TestsClipboards;
use Tests\Fixtures\Models\Account;

/**
 * @internal
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class BeforePoliciesTest extends TestCase
{
    use TestsClipboards;

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function policy_forbids_and_warden_allows($provider): void
    {
        [$warden, $user] = $provider();

        $this->setUpWithPolicy($warden);

        $account = Account::query()->create(['name' => 'false']);

        $warden->allow($user)->to('view', $account);

        $this->assertTrue($warden->cannot('view', $account));

        $warden->runBeforePolicies();

        $this->assertTrue($warden->can('view', $account));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function policy_allows_and_warden_forbids($provider): void
    {
        [$warden, $user] = $provider();

        $this->setUpWithPolicy($warden);

        $account = Account::query()->create(['name' => 'true']);

        $warden->forbid($user)->to('view', $account);

        $this->assertTrue($warden->can('view', $account));

        $warden->runBeforePolicies();

        $this->assertTrue($warden->cannot('view', $account));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function passes_auth_check_when_warden_allows($provider): void
    {
        [$warden, $user] = $provider();

        $this->setUpWithPolicy($warden);

        $account = Account::query()->create(['name' => 'ignored by policy']);

        $warden->allow($user)->to('view', $account);

        $this->assertTrue($warden->can('view', $account));

        $warden->runBeforePolicies();

        $this->assertTrue($warden->can('view', $account));
    }

    #[Test()]
    #[DataProvider('bouncerProvider')]
    public function fails_auth_check_when_warden_does_not_allow($provider): void
    {
        [$warden, $user] = $provider();

        $this->setUpWithPolicy($warden);

        $account = Account::query()->create(['name' => 'ignored by policy']);

        $this->assertTrue($warden->cannot('view', $account));

        $warden->runBeforePolicies();

        $this->assertTrue($warden->cannot('view', $account));
    }

    /**
     * Set up the given Bouncer instance with the test policy.
     *
     * @param \Silber\Buoncer\Bouncer $warden
     */
    private function setUpWithPolicy(WardenClass $warden): void
    {
        $warden->gate()->policy(Account::class, AccountPolicyForAfter::class);
    }
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class AccountPolicyForAfter
{
    public function view($user, $account): mixed
    {
        if ($account->name === 'true') {
            return true;
        }

        if ($account->name === 'false') {
            return false;
        }

        return null;
    }
}
