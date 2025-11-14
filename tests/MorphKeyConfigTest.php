<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use Cline\Warden\Database\Models;
use Cline\Warden\Exceptions\InvalidConfigurationException;
use Cline\Warden\Exceptions\MorphKeyViolationException;
use Cline\Warden\WardenServiceProvider;
use Illuminate\Contracts\Config\Repository;
use Override;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Models\Organization;
use Tests\Fixtures\Models\User;

/**
 * Tests for configuration-based polymorphic key mapping.
 *
 * @internal
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MorphKeyConfigTest extends TestCase
{
    #[Override()]
    protected function setUp(): void
    {
        parent::setUp();

        Models::reset();
    }

    #[Test()]
    public function it_applies_morph_key_map_from_config(): void
    {
        $config = $this->app->make(Repository::class);

        $config->set('warden.morphKeyMap', [
            User::class => 'id',
            Organization::class => 'ulid',
        ]);

        $provider = new WardenServiceProvider($this->app);
        $provider->boot();

        $user = User::query()->create(['name' => 'John']);
        $org = Organization::query()->create(['name' => 'Acme']);

        $this->assertSame('id', Models::getModelKey($user));
        $this->assertSame('ulid', Models::getModelKey($org));
    }

    #[Test()]
    public function it_applies_enforce_morph_key_map_from_config(): void
    {
        $this->expectException(MorphKeyViolationException::class);

        $config = $this->app->make(Repository::class);

        $config->set('warden.enforceMorphKeyMap', [
            User::class => 'id',
        ]);

        $provider = new WardenServiceProvider($this->app);
        $provider->boot();

        $org = Organization::query()->create(['name' => 'Acme']);

        // Should throw because enforcement is enabled and Organization is not mapped
        Models::getModelKey($org);
    }

    #[Test()]
    public function it_throws_when_both_configs_are_set(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Cannot configure both morphKeyMap and enforceMorphKeyMap simultaneously');

        $config = $this->app->make(Repository::class);

        $config->set('warden.morphKeyMap', [
            User::class => 'id',
        ]);

        $config->set('warden.enforceMorphKeyMap', [
            Organization::class => 'ulid',
        ]);

        $provider = new WardenServiceProvider($this->app);
        $provider->boot();
    }

    #[Test()]
    public function it_does_nothing_when_both_configs_are_empty(): void
    {
        $config = $this->app->make(Repository::class);

        $config->set('warden.morphKeyMap', []);
        $config->set('warden.enforceMorphKeyMap', []);

        $provider = new WardenServiceProvider($this->app);
        $provider->boot();

        $user = User::query()->create(['name' => 'John']);

        // Should use model's default key name
        $this->assertSame('id', Models::getModelKey($user));
    }

    #[Test()]
    public function it_allows_one_config_empty_and_one_populated(): void
    {
        $config = $this->app->make(Repository::class);

        $config->set('warden.morphKeyMap', [
            User::class => 'id',
        ]);
        $config->set('warden.enforceMorphKeyMap', []);

        $provider = new WardenServiceProvider($this->app);
        $provider->boot();

        $user = User::query()->create(['name' => 'John']);

        $this->assertSame('id', Models::getModelKey($user));
    }

    #[Test()]
    public function it_prioritizes_enforce_over_morph_when_only_enforce_is_set(): void
    {
        $this->expectException(MorphKeyViolationException::class);

        $config = $this->app->make(Repository::class);

        $config->set('warden.morphKeyMap', []);
        $config->set('warden.enforceMorphKeyMap', [
            User::class => 'id',
        ]);

        $provider = new WardenServiceProvider($this->app);
        $provider->boot();

        $org = Organization::query()->create(['name' => 'Acme']);

        // Should throw because enforcement is enabled
        Models::getModelKey($org);
    }
}
