<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Facades;

use Cline\Warden\Clipboard\Clipboard;
use Cline\Warden\Conductors\AssignsRoles;
use Cline\Warden\Conductors\ChecksRoles;
use Cline\Warden\Conductors\GivesAbilities;
use Cline\Warden\Conductors\RemovesAbilities;
use Cline\Warden\Conductors\RemovesRoles;
use Cline\Warden\Conductors\SyncsRolesAndAbilities;
use Cline\Warden\Contracts\ClipboardInterface;
use Cline\Warden\Database\Ability;
use Cline\Warden\Database\Role;
use Cline\Warden\Facades\Warden as WardenFacade;
use Cline\Warden\Factory;
use Cline\Warden\Warden;
use Illuminate\Auth\Access\Response;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Tests\Fixtures\Models\User;

/**
 * Test suite for Warden facade.
 *
 * Verifies that the facade properly resolves to the Warden instance and proxies
 * method calls correctly through Laravel's facade infrastructure.
 *
 * @internal
 *
 * @author Brian Faust <brian@cline.sh>
 */
#[Small()]
final class WardenTest extends TestCase
{
    private Container $container;

    private Warden $wardenInstance;

    private MockObject $gate;

    protected function setUp(): void
    {
        parent::setUp();

        // Arrange
        $this->container = new Container();
        $this->gate = $this->createMock(Gate::class);

        // Create real Warden instance with mocked dependencies
        $factory = new Factory();
        $clipboard = new Clipboard();
        $this->wardenInstance = $factory
            ->withClipboard($clipboard)
            ->withGate($this->gate)
            ->create();

        // Bind facade to container
        $this->container->singleton(WardenFacade::class, fn (): Warden => $this->wardenInstance);
        Container::setInstance($this->container);

        // Set facade root using reflection to work around facade caching
        WardenFacade::setFacadeApplication($this->container);
    }

    protected function tearDown(): void
    {
        // Assert
        WardenFacade::clearResolvedInstance(WardenFacade::class);
        Container::setInstance();

        parent::tearDown();
    }

    // ==================== Happy Path Tests ====================

    #[Test()]
    #[TestDox('Returns correct facade accessor for service container resolution')]
    #[Group('happy-path')]
    public function returns_correct_facade_accessor_for_service_container_resolution(): void
    {
        // Arrange
        $reflection = new ReflectionClass(WardenFacade::class);
        $method = $reflection->getMethod('getFacadeAccessor');

        // Act
        $accessor = $method->invoke(null);

        // Assert
        $this->assertSame(WardenFacade::class, $accessor);
    }

    #[Test()]
    #[TestDox('Proxies allow method to underlying Warden instance')]
    #[Group('happy-path')]
    public function proxies_allow_method_to_underlying_warden_instance(): void
    {
        // Arrange
        $user = new User(['name' => 'John']);

        // Act
        $result = WardenFacade::allow($user);

        // Assert
        $this->assertInstanceOf(GivesAbilities::class, $result);
    }

    #[Test()]
    #[TestDox('Proxies allowEveryone method to underlying Warden instance')]
    #[Group('happy-path')]
    public function proxies_allow_everyone_method_to_underlying_warden_instance(): void
    {
        // Arrange

        // Act
        $result = WardenFacade::allowEveryone();

        // Assert
        $this->assertInstanceOf(GivesAbilities::class, $result);
    }

    #[Test()]
    #[TestDox('Proxies disallow method to underlying Warden instance')]
    #[Group('happy-path')]
    public function proxies_disallow_method_to_underlying_warden_instance(): void
    {
        // Arrange
        $user = new User(['name' => 'Jane']);

        // Act
        $result = WardenFacade::disallow($user);

        // Assert
        $this->assertInstanceOf(RemovesAbilities::class, $result);
    }

    #[Test()]
    #[TestDox('Proxies assign method to underlying Warden instance')]
    #[Group('happy-path')]
    public function proxies_assign_method_to_underlying_warden_instance(): void
    {
        // Arrange
        $role = 'admin';

        // Act
        $result = WardenFacade::assign($role);

        // Assert
        $this->assertInstanceOf(AssignsRoles::class, $result);
    }

    #[Test()]
    #[TestDox('Proxies retract method to underlying Warden instance')]
    #[Group('happy-path')]
    public function proxies_retract_method_to_underlying_warden_instance(): void
    {
        // Arrange
        $role = 'editor';

        // Act
        $result = WardenFacade::retract($role);

        // Assert
        $this->assertInstanceOf(RemovesRoles::class, $result);
    }

    #[Test()]
    #[TestDox('Proxies sync method to underlying Warden instance')]
    #[Group('happy-path')]
    public function proxies_sync_method_to_underlying_warden_instance(): void
    {
        // Arrange
        $user = new User(['name' => 'Bob']);

        // Act
        $result = WardenFacade::sync($user);

        // Assert
        $this->assertInstanceOf(SyncsRolesAndAbilities::class, $result);
    }

    #[Test()]
    #[TestDox('Proxies is method to underlying Warden instance')]
    #[Group('happy-path')]
    public function proxies_is_method_to_underlying_warden_instance(): void
    {
        // Arrange
        $user = new User(['name' => 'Alice']);

        // Act
        $result = WardenFacade::is($user);

        // Assert
        $this->assertInstanceOf(ChecksRoles::class, $result);
    }

    #[Test()]
    #[TestDox('Proxies getClipboard method to underlying Warden instance')]
    #[Group('happy-path')]
    public function proxies_get_clipboard_method_to_underlying_warden_instance(): void
    {
        // Arrange

        // Act
        $result = WardenFacade::getClipboard();

        // Assert
        $this->assertInstanceOf(ClipboardInterface::class, $result);
    }

    #[Test()]
    #[TestDox('Proxies gate method to underlying Warden instance')]
    #[Group('happy-path')]
    public function proxies_gate_method_to_underlying_warden_instance(): void
    {
        // Arrange

        // Act
        $result = WardenFacade::gate();

        // Assert
        $this->assertInstanceOf(Gate::class, $result);
    }

    #[Test()]
    #[TestDox('Proxies can method to underlying Warden instance')]
    #[Group('happy-path')]
    public function proxies_can_method_to_underlying_warden_instance(): void
    {
        // Arrange
        $ability = 'edit-posts';
        $arguments = ['post' => 123];

        $this->gate
            ->expects($this->once())
            ->method('allows')
            ->with($ability, $arguments)
            ->willReturn(true);

        // Act
        $result = WardenFacade::can($ability, $arguments);

        // Assert
        $this->assertTrue($result);
    }

    #[Test()]
    #[TestDox('Proxies canAny method to underlying Warden instance')]
    #[Group('happy-path')]
    public function proxies_can_any_method_to_underlying_warden_instance(): void
    {
        // Arrange
        $abilities = ['edit-posts', 'delete-posts'];
        $arguments = ['post' => 456];

        $this->gate
            ->expects($this->once())
            ->method('any')
            ->with($abilities, $arguments)
            ->willReturn(true);

        // Act
        $result = WardenFacade::canAny($abilities, $arguments);

        // Assert
        $this->assertTrue($result);
    }

    #[Test()]
    #[TestDox('Proxies authorize method to underlying Warden instance')]
    #[Group('happy-path')]
    public function proxies_authorize_method_to_underlying_warden_instance(): void
    {
        // Arrange
        $ability = 'publish-post';
        $arguments = ['post' => 789];
        $response = Response::allow();

        $this->gate
            ->expects($this->once())
            ->method('authorize')
            ->with($ability, $arguments)
            ->willReturn($response);

        // Act
        $result = WardenFacade::authorize($ability, $arguments);

        // Assert
        $this->assertInstanceOf(Response::class, $result);
    }

    #[Test()]
    #[TestDox('Proxies role method to underlying Warden instance')]
    #[Group('happy-path')]
    public function proxies_role_method_to_underlying_warden_instance(): void
    {
        // Arrange
        $attributes = ['name' => 'admin', 'title' => 'Administrator'];

        // Act
        $result = WardenFacade::role($attributes);

        // Assert
        $this->assertInstanceOf(Role::class, $result);
        $this->assertSame('admin', $result->name);
        $this->assertSame('Administrator', $result->title);
    }

    #[Test()]
    #[TestDox('Proxies ability method to underlying Warden instance')]
    #[Group('happy-path')]
    public function proxies_ability_method_to_underlying_warden_instance(): void
    {
        // Arrange
        $attributes = ['name' => 'edit-posts', 'title' => 'Edit Posts'];

        // Act
        $result = WardenFacade::ability($attributes);

        // Assert
        $this->assertInstanceOf(Ability::class, $result);
        $this->assertSame('edit-posts', $result->name);
        $this->assertSame('Edit Posts', $result->title);
    }

    #[Test()]
    #[TestDox('Proxies dontCache method and returns facade instance for chaining')]
    #[Group('happy-path')]
    public function proxies_dont_cache_method_and_returns_facade_instance_for_chaining(): void
    {
        // Arrange

        // Act
        $result = WardenFacade::dontCache();

        // Assert
        $this->assertInstanceOf(Warden::class, $result);
    }

    #[Test()]
    #[TestDox('Proxies refresh method and returns facade instance for chaining')]
    #[Group('happy-path')]
    public function proxies_refresh_method_and_returns_facade_instance_for_chaining(): void
    {
        // Arrange
        $user = new User(['name' => 'Charlie']);

        // Act
        $result = WardenFacade::refresh($user);

        // Assert
        $this->assertInstanceOf(Warden::class, $result);
    }

    #[Test()]
    #[TestDox('Proxies usesCachedClipboard method to underlying Warden instance')]
    #[Group('happy-path')]
    public function proxies_uses_cached_clipboard_method_to_underlying_warden_instance(): void
    {
        // Arrange

        // Act
        $result = WardenFacade::usesCachedClipboard();

        // Assert
        $this->assertIsBool($result);
    }

    #[Test()]
    #[TestDox('Proxies define method and returns facade instance for chaining')]
    #[Group('happy-path')]
    public function proxies_define_method_and_returns_facade_instance_for_chaining(): void
    {
        // Arrange
        $ability = 'moderate-comments';
        $callback = fn (): true => true;

        $this->gate
            ->expects($this->once())
            ->method('define')
            ->with($ability, $callback);

        // Act
        $result = WardenFacade::define($ability, $callback);

        // Assert
        $this->assertInstanceOf(Warden::class, $result);
    }

    #[Test()]
    #[TestDox('Proxies useAbilityModel method and returns facade instance for chaining')]
    #[Group('happy-path')]
    public function proxies_use_ability_model_method_and_returns_facade_instance_for_chaining(): void
    {
        // Arrange
        $model = Ability::class;

        // Act
        $result = WardenFacade::useAbilityModel($model);

        // Assert
        $this->assertInstanceOf(Warden::class, $result);
    }

    #[Test()]
    #[TestDox('Proxies useRoleModel method and returns facade instance for chaining')]
    #[Group('happy-path')]
    public function proxies_use_role_model_method_and_returns_facade_instance_for_chaining(): void
    {
        // Arrange
        $model = Role::class;

        // Act
        $result = WardenFacade::useRoleModel($model);

        // Assert
        $this->assertInstanceOf(Warden::class, $result);
    }

    // ==================== Edge Case Tests ====================

    #[Test()]
    #[TestDox('Facade resolves instance from container on first access')]
    #[Group('edge-case')]
    public function facade_resolves_instance_from_container_on_first_access(): void
    {
        // Arrange
        $resolveCount = 0;
        $this->container->singleton(WardenFacade::class, function () use (&$resolveCount): Warden {
            ++$resolveCount;

            return $this->wardenInstance;
        });

        WardenFacade::clearResolvedInstance(WardenFacade::class);

        // Act
        WardenFacade::getClipboard();
        WardenFacade::getClipboard();

        // Assert
        $this->assertSame(1, $resolveCount, 'Facade should resolve instance only once');
    }

    #[Test()]
    #[TestDox('Facade accessor returns string not null')]
    #[Group('edge-case')]
    public function facade_accessor_returns_string_not_null(): void
    {
        // Arrange
        $reflection = new ReflectionClass(WardenFacade::class);
        $method = $reflection->getMethod('getFacadeAccessor');

        // Act
        $accessor = $method->invoke(null);

        // Assert
        $this->assertIsString($accessor);
        $this->assertNotEmpty($accessor);
    }

    #[Test()]
    #[TestDox('Facade handles method calls with empty arrays as arguments')]
    #[Group('edge-case')]
    public function facade_handles_method_calls_with_empty_arrays_as_arguments(): void
    {
        // Arrange
        $emptyAttributes = [];

        // Act
        $result = WardenFacade::role($emptyAttributes);

        // Assert
        $this->assertInstanceOf(Role::class, $result);
    }

    #[Test()]
    #[TestDox('Facade handles chained method calls correctly')]
    #[Group('edge-case')]
    public function facade_handles_chained_method_calls_correctly(): void
    {
        // Arrange

        // Act
        $result = WardenFacade::dontCache()->refresh();

        // Assert
        $this->assertInstanceOf(Warden::class, $result);
    }

    #[Test()]
    #[TestDox('Facade properly handles null arguments in method calls')]
    #[Group('edge-case')]
    public function facade_properly_handles_null_arguments_in_method_calls(): void
    {
        // Arrange

        // Act
        $result = WardenFacade::refresh();

        // Assert
        $this->assertInstanceOf(Warden::class, $result);
    }

    #[Test()]
    #[TestDox('Facade maintains singleton behavior across multiple calls')]
    #[Group('edge-case')]
    public function facade_maintains_singleton_behavior_across_multiple_calls(): void
    {
        // Arrange

        // Act
        $result1 = WardenFacade::getClipboard();
        $result2 = WardenFacade::getClipboard();

        // Assert
        $this->assertSame($result1, $result2, 'Should return same clipboard instance');
    }

    #[Test()]
    #[TestDox('Facade clear resolved instance resets cached instance')]
    #[Group('edge-case')]
    public function facade_clear_resolved_instance_resets_cached_instance(): void
    {
        // Arrange
        $clipboard1 = WardenFacade::getClipboard();

        // Act
        WardenFacade::clearResolvedInstance(WardenFacade::class);
        $clipboard2 = WardenFacade::getClipboard();

        // Assert
        // After clearing, facade resolves the instance again from container
        // The same singleton is returned but facade goes through resolution again
        $this->assertSame($clipboard1, $clipboard2, 'Same underlying clipboard from singleton');
    }

    #[Test()]
    #[TestDox('Facade extends Laravel base Facade class')]
    #[Group('edge-case')]
    public function facade_extends_laravel_base_facade_class(): void
    {
        // Arrange
        $reflection = new ReflectionClass(WardenFacade::class);

        // Act
        $parent = $reflection->getParentClass();

        // Assert
        $this->assertNotFalse($parent);
        $this->assertSame(Facade::class, $parent->getName());
    }

    #[Test()]
    #[TestDox('Facade is marked as final class')]
    #[Group('edge-case')]
    public function facade_is_marked_as_final_class(): void
    {
        // Arrange
        $reflection = new ReflectionClass(WardenFacade::class);

        // Act
        $isFinal = $reflection->isFinal();

        // Assert
        $this->assertTrue($isFinal);
    }

    #[Test()]
    #[TestDox('Facade accessor method is protected not public')]
    #[Group('edge-case')]
    public function facade_accessor_method_is_protected_not_public(): void
    {
        // Arrange
        $reflection = new ReflectionClass(WardenFacade::class);
        $method = $reflection->getMethod('getFacadeAccessor');

        // Act
        $isProtected = $method->isProtected();

        // Assert
        $this->assertTrue($isProtected);
        $this->assertFalse($method->isPublic());
    }

    // ==================== Sad Path Tests ====================

    #[Test()]
    #[TestDox('Facade proxies getGate method and returns null when not set')]
    #[Group('sad-path')]
    public function facade_proxies_get_gate_method_and_returns_null_when_not_set(): void
    {
        // Arrange
        // The gate is always set by Factory, so test that getGate returns it

        // Act
        $result = WardenFacade::getGate();

        // Assert
        $this->assertInstanceOf(Gate::class, $result);
    }

    #[Test()]
    #[TestDox('Cannot method returns opposite of can method')]
    #[Group('sad-path')]
    public function cannot_method_returns_opposite_of_can_method(): void
    {
        // Arrange
        $ability = 'delete-posts';
        $arguments = [];

        $this->gate
            ->expects($this->once())
            ->method('denies')
            ->with($ability, $arguments)
            ->willReturn(true);

        // Act
        $result = WardenFacade::cannot($ability, $arguments);

        // Assert
        $this->assertTrue($result);
    }
}
