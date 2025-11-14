<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use Cline\Warden\Database\Models;
use Cline\Warden\Exceptions\MorphKeyViolationException;
use Override;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Models\Organization;
use Tests\Fixtures\Models\User;

/**
 * Tests for polymorphic key mapping functionality.
 *
 * @internal
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MorphKeyMapTest extends TestCase
{
    #[Override()]
    protected function setUp(): void
    {
        parent::setUp();

        Models::reset();
        Models::setUsersModel(User::class);
    }

    #[Override()]
    protected function tearDown(): void
    {
        Models::reset();
        parent::tearDown();
    }

    #[Test()]
    public function it_returns_model_key_name_when_no_mapping_exists(): void
    {
        $user = User::query()->create(['name' => 'John']);

        $this->assertSame('id', Models::getModelKey($user));
    }

    #[Test()]
    public function it_returns_mapped_key_when_mapping_exists(): void
    {
        Models::morphKeyMap([
            Organization::class => 'ulid',
        ]);

        $org = Organization::query()->create(['name' => 'Acme']);

        $this->assertSame('ulid', Models::getModelKey($org));
    }

    #[Test()]
    public function it_allows_multiple_models_to_be_mapped(): void
    {
        Models::morphKeyMap([
            User::class => 'id',
            Organization::class => 'ulid',
        ]);

        $user = User::query()->create(['name' => 'John']);
        $org = Organization::query()->create(['name' => 'Acme']);

        $this->assertSame('id', Models::getModelKey($user));
        $this->assertSame('ulid', Models::getModelKey($org));
    }

    #[Test()]
    public function it_merges_multiple_morph_key_map_calls(): void
    {
        Models::morphKeyMap([
            User::class => 'id',
        ]);

        Models::morphKeyMap([
            Organization::class => 'ulid',
        ]);

        $user = User::query()->create(['name' => 'John']);
        $org = Organization::query()->create(['name' => 'Acme']);

        $this->assertSame('id', Models::getModelKey($user));
        $this->assertSame('ulid', Models::getModelKey($org));
    }

    #[Test()]
    public function it_does_not_throw_without_enforcement_when_mapping_missing(): void
    {
        Models::morphKeyMap([
            User::class => 'id',
        ]);

        $org = Organization::query()->create(['name' => 'Acme']);

        // Should not throw, should return model's key name
        $key = Models::getModelKey($org);

        $this->assertSame('ulid', $key);
    }

    #[Test()]
    public function it_throws_with_enforcement_when_mapping_missing(): void
    {
        $this->expectException(MorphKeyViolationException::class);
        $this->expectExceptionMessage('No polymorphic key mapping defined for [Tests\Fixtures\Models\Organization]');

        Models::enforceMorphKeyMap([
            User::class => 'id',
        ]);

        $org = Organization::query()->create(['name' => 'Acme']);

        Models::getModelKey($org);
    }

    #[Test()]
    public function it_enables_enforcement_with_require_key_map(): void
    {
        $this->expectException(MorphKeyViolationException::class);

        Models::morphKeyMap([
            User::class => 'id',
        ]);

        Models::requireKeyMap();

        $org = Organization::query()->create(['name' => 'Acme']);

        Models::getModelKey($org);
    }

    #[Test()]
    public function it_does_not_throw_with_enforcement_when_mapping_exists(): void
    {
        Models::enforceMorphKeyMap([
            User::class => 'id',
            Organization::class => 'ulid',
        ]);

        $org = Organization::query()->create(['name' => 'Acme']);

        $key = Models::getModelKey($org);

        $this->assertSame('ulid', $key);
    }

    #[Test()]
    public function reset_clears_key_mappings(): void
    {
        Models::morphKeyMap([
            User::class => 'id',
            Organization::class => 'ulid',
        ]);

        Models::reset();

        $user = User::query()->create(['name' => 'John']);

        // After reset, should return model's key name
        $this->assertSame('id', Models::getModelKey($user));
    }

    #[Test()]
    public function reset_clears_enforcement_flag(): void
    {
        Models::enforceMorphKeyMap([
            User::class => 'id',
        ]);

        Models::reset();

        $org = Organization::query()->create(['name' => 'Acme']);

        // Should not throw after reset
        $key = Models::getModelKey($org);

        $this->assertSame('ulid', $key);
    }
}
