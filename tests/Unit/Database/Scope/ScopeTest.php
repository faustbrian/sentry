<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Database\Scope;

use Cline\Warden\Database\Scope\Scope;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

use function serialize;

/**
 * @internal
 *
 * @author Brian Faust <brian@cline.sh>
 */
#[Small()]
final class ScopeTest extends TestCase
{
    #[Test()]
    #[TestDox('Appends scalar scope value to cache key')]
    #[Group('happy-path')]
    public function appends_scalar_scope_value_to_cache_key(): void
    {
        // Arrange
        $scope = new Scope();
        $scope->to(42);

        $baseKey = 'cache-key';

        // Act
        $result = $scope->appendToCacheKey($baseKey);

        // Assert
        $this->assertSame('cache-key-42', $result);
    }

    #[Test()]
    #[TestDox('Appends string scope value to cache key')]
    #[Group('happy-path')]
    public function appends_string_scope_value_to_cache_key(): void
    {
        // Arrange
        $scope = new Scope();
        $scope->to('tenant-uuid-123');

        $baseKey = 'cache-key';

        // Act
        $result = $scope->appendToCacheKey($baseKey);

        // Assert
        $this->assertSame('cache-key-tenant-uuid-123', $result);
    }

    #[Test()]
    #[TestDox('Serializes non-scalar array scope value for cache key')]
    #[Group('happy-path')]
    public function serializes_non_scalar_array_scope_value_for_cache_key(): void
    {
        // Arrange
        $scope = new Scope();
        $scopeValue = ['tenant_id' => 42, 'organization_id' => 7];
        $scope->to($scopeValue);
        $baseKey = 'cache-key';

        // Act
        $result = $scope->appendToCacheKey($baseKey);

        // Assert
        $expectedSuffix = serialize($scopeValue);
        $this->assertSame('cache-key-'.$expectedSuffix, $result);
        $this->assertStringContainsString('cache-key-', $result);
    }

    #[Test()]
    #[TestDox('Serializes non-scalar object scope value for cache key')]
    #[Group('happy-path')]
    public function serializes_non_scalar_object_scope_value_for_cache_key(): void
    {
        // Arrange
        $scope = new Scope();
        $scopeValue = (object) ['tenant_id' => 42, 'organization_id' => 7];
        $scope->to($scopeValue);
        $baseKey = 'cache-key';

        // Act
        $result = $scope->appendToCacheKey($baseKey);

        // Assert
        $expectedSuffix = serialize($scopeValue);
        $this->assertSame('cache-key-'.$expectedSuffix, $result);
        $this->assertStringContainsString('cache-key-', $result);
    }

    #[Test()]
    #[TestDox('Returns original cache key when scope is null')]
    #[Group('edge-case')]
    public function returns_original_cache_key_when_scope_is_null(): void
    {
        // Arrange
        $scope = new Scope();
        $baseKey = 'cache-key';

        // Act
        $result = $scope->appendToCacheKey($baseKey);

        // Assert
        $this->assertSame('cache-key', $result);
    }

    #[Test()]
    #[TestDox('Sets scope value')]
    #[Group('happy-path')]
    public function sets_scope_value(): void
    {
        // Arrange
        $scope = new Scope();

        // Act
        $scope->to(123);

        // Assert
        $this->assertSame(123, $scope->get());
    }

    #[Test()]
    #[TestDox('Removes scope value')]
    #[Group('happy-path')]
    public function removes_scope_value(): void
    {
        // Arrange
        $scope = new Scope();
        $scope->to(123);

        // Act
        $scope->remove();

        // Assert
        $this->assertNull($scope->get());
    }

    #[Test()]
    #[TestDox('Configures relationship-only scoping')]
    #[Group('happy-path')]
    public function configures_relationship_only_scoping(): void
    {
        // Arrange
        $scope = new Scope();

        // Act
        $result = $scope->onlyRelations(true);

        // Assert
        $this->assertSame($scope, $result);
    }

    #[Test()]
    #[TestDox('Configures role abilities scoping')]
    #[Group('happy-path')]
    public function configures_role_abilities_scoping(): void
    {
        // Arrange
        $scope = new Scope();

        // Act
        $result = $scope->dontScopeRoleAbilities();

        // Assert
        $this->assertSame($scope, $result);
    }
}
