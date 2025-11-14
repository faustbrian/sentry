<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use Cline\Warden\Clipboard\CachedClipboard;
use Cline\Warden\Clipboard\Clipboard;
use Cline\Warden\Contracts\ClipboardInterface;
use Cline\Warden\Warden as WardenClass;
use Illuminate\Auth\Access\Gate;
use Illuminate\Cache\ArrayStore;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Fixtures\Models\User;

/**
 * @internal
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class FactoryTest extends TestCase
{
    #[Test()]
    public function can_create_default_warden_instance(): void
    {
        $warden = WardenClass::create();

        $this->assertInstanceOf(WardenClass::class, $warden);
        $this->assertInstanceOf(Gate::class, $warden->getGate());
        $this->assertTrue($warden->usesCachedClipboard());
    }

    #[Test()]
    public function can_create_warden_instance_for_given_the_user(): void
    {
        $warden = WardenClass::create($user = User::query()->create());

        $warden->allow($user)->to('create-bouncers');

        $this->assertTrue($warden->can('create-bouncers'));
        $this->assertTrue($warden->cannot('delete-bouncers'));
    }

    #[Test()]
    public function can_build_up_warden_with_the_given_user(): void
    {
        $warden = WardenClass::make()->withUser($user = User::query()->create())->create();

        $warden->allow($user)->to('create-bouncers');

        $this->assertTrue($warden->can('create-bouncers'));
        $this->assertTrue($warden->cannot('delete-bouncers'));
    }

    #[Test()]
    public function can_build_up_warden_with_the_given_gate(): void
    {
        $user = User::query()->create();

        $gate = new Gate(
            new Container(),
            fn () => $user,
        );

        $warden = WardenClass::make()->withGate($gate)->create();

        $warden->allow($user)->to('create-bouncers');

        $this->assertTrue($warden->can('create-bouncers'));
        $this->assertTrue($warden->cannot('delete-bouncers'));
    }

    #[Test()]
    #[TestDox('Builds Warden with custom cache store')]
    #[Group('happy-path')]
    public function can_build_up_warden_with_custom_cache_store(): void
    {
        // Arrange
        $user = User::query()->create();
        $customCache = new ArrayStore();

        // Act
        $warden = WardenClass::make()
            ->withUser($user)
            ->withCache($customCache)
            ->create();

        $warden->allow($user)->to('test-ability');

        // Assert
        $this->assertInstanceOf(WardenClass::class, $warden);
        $this->assertTrue($warden->usesCachedClipboard());
        $this->assertTrue($warden->can('test-ability'));
    }

    #[Test()]
    #[TestDox('Builds Warden with custom clipboard implementation')]
    #[Group('happy-path')]
    public function can_build_up_warden_with_custom_clipboard(): void
    {
        // Arrange
        $user = User::query()->create();
        $customClipboard = new Clipboard();

        // Act
        $warden = WardenClass::make()
            ->withUser($user)
            ->withClipboard($customClipboard)
            ->create();

        $warden->allow($user)->to('test-ability');

        // Assert
        $this->assertInstanceOf(WardenClass::class, $warden);
        $this->assertFalse($warden->usesCachedClipboard());
        $this->assertTrue($warden->can('test-ability'));
    }

    #[Test()]
    #[TestDox('Skips clipboard container registration when registerClipboardAtContainer(false)')]
    #[Group('edge-case')]
    public function can_skip_clipboard_container_registration(): void
    {
        // Arrange
        $user = User::query()->create();
        $container = Container::getInstance();

        // Clear any existing clipboard binding
        $container->forgetInstance(ClipboardInterface::class);

        // Act
        $warden = WardenClass::make()
            ->withUser($user)
            ->registerClipboardAtContainer(false)
            ->create();

        // Assert
        $this->assertInstanceOf(WardenClass::class, $warden);
        // The clipboard should not be registered in the container
        $this->assertFalse($container->bound(ClipboardInterface::class) && $container->resolved(ClipboardInterface::class));
    }

    #[Test()]
    #[TestDox('Registers clipboard in container when registerClipboardAtContainer(true)')]
    #[Group('happy-path')]
    public function can_register_clipboard_at_container(): void
    {
        // Arrange
        $user = User::query()->create();
        $container = Container::getInstance();

        // Clear any existing clipboard binding
        $container->forgetInstance(ClipboardInterface::class);

        // Act
        $warden = WardenClass::make()
            ->withUser($user)
            ->registerClipboardAtContainer(true)
            ->create();

        // Assert
        $this->assertInstanceOf(WardenClass::class, $warden);
        // The clipboard should be registered in the container
        $this->assertTrue($container->bound(ClipboardInterface::class));
        $this->assertInstanceOf(ClipboardInterface::class, $container->make(ClipboardInterface::class));
    }

    #[Test()]
    #[TestDox('Skips gate registration when registerAtGate(false)')]
    #[Group('edge-case')]
    public function can_skip_gate_registration(): void
    {
        // Arrange
        $user = User::query()->create();
        $customGate = new Gate(
            new Container(),
            fn () => $user,
        );

        // Define a gate ability that always returns true
        $customGate->define('gate-ability', fn (): true => true);

        // Act
        $warden = WardenClass::make()
            ->withUser($user)
            ->withGate($customGate)
            ->registerAtGate(false)
            ->create();

        // Allow an ability through Warden
        $warden->allow($user)->to('test-ability');

        // Assert
        $this->assertInstanceOf(WardenClass::class, $warden);

        // Gate-defined abilities should still work (because gate itself works)
        $this->assertTrue($warden->can('gate-ability'));

        // But Warden abilities won't work through gate checks because guard wasn't registered
        // The ability is stored in the database but gate doesn't know about it
        $this->assertFalse($warden->can('test-ability'));
    }

    #[Test()]
    #[TestDox('Registers guard at gate when registerAtGate(true)')]
    #[Group('happy-path')]
    public function can_register_guard_at_gate(): void
    {
        // Arrange
        $user = User::query()->create();

        // Act
        $warden = WardenClass::make()
            ->withUser($user)
            ->registerAtGate(true)
            ->create();

        $warden->allow($user)->to('test-ability');

        // Assert
        $this->assertInstanceOf(WardenClass::class, $warden);
        $this->assertTrue($warden->can('test-ability'));
        $this->assertFalse($warden->can('non-existent-ability'));
    }

    #[Test()]
    #[TestDox('Chains all configuration methods together')]
    #[Group('happy-path')]
    public function can_chain_all_configuration_methods(): void
    {
        // Arrange
        $user = User::query()->create();
        $customCache = new ArrayStore();
        $customClipboard = new CachedClipboard($customCache);
        $customGate = new Gate(
            new Container(),
            fn () => $user,
        );

        // Act
        $warden = WardenClass::make()
            ->withUser($user)
            ->withCache($customCache)
            ->withClipboard($customClipboard)
            ->withGate($customGate)
            ->registerClipboardAtContainer(true)
            ->registerAtGate(true)
            ->create();

        $warden->allow($user)->to('chained-ability');

        // Assert
        $this->assertInstanceOf(WardenClass::class, $warden);
        $this->assertTrue($warden->can('chained-ability'));
        $this->assertSame($customGate, $warden->getGate());
    }

    #[Test()]
    #[TestDox('Chains configuration methods with both registration flags disabled')]
    #[Group('edge-case')]
    public function can_chain_methods_with_registration_flags_disabled(): void
    {
        // Arrange
        $user = User::query()->create();
        $customCache = new ArrayStore();
        $customGate = new Gate(
            new Container(),
            fn () => $user,
        );

        // Define a gate ability to verify gate still works
        $customGate->define('gate-ability', fn (): true => true);

        // Act
        $warden = WardenClass::make()
            ->withUser($user)
            ->withCache($customCache)
            ->withGate($customGate)
            ->registerClipboardAtContainer(false)
            ->registerAtGate(false)
            ->create();

        $warden->allow($user)->to('test-ability');

        // Assert
        $this->assertInstanceOf(WardenClass::class, $warden);
        $this->assertTrue($warden->usesCachedClipboard());

        // Gate-defined abilities work
        $this->assertTrue($warden->can('gate-ability'));

        // But Warden abilities won't work through gate because guard wasn't registered
        $this->assertFalse($warden->can('test-ability'));
    }

    #[Test()]
    #[TestDox('Creates Warden with minimal configuration using just withUser')]
    #[Group('edge-case')]
    public function can_create_with_minimal_configuration(): void
    {
        // Arrange
        $user = User::query()->create();

        // Act
        $warden = WardenClass::make()
            ->withUser($user)
            ->create();

        $warden->allow($user)->to('minimal-ability');

        // Assert
        $this->assertInstanceOf(WardenClass::class, $warden);
        $this->assertTrue($warden->can('minimal-ability'));
        $this->assertTrue($warden->usesCachedClipboard());
    }

    #[Test()]
    #[TestDox('Uses default ArrayStore cache when no custom cache provided')]
    #[Group('happy-path')]
    public function uses_default_array_store_when_no_cache_provided(): void
    {
        // Arrange
        $user = User::query()->create();

        // Act - Create without calling withCache()
        $warden = WardenClass::make()
            ->withUser($user)
            ->create();

        $warden->allow($user)->to('cached-ability');

        // Assert
        $this->assertInstanceOf(WardenClass::class, $warden);
        $this->assertTrue($warden->usesCachedClipboard());
        $this->assertTrue($warden->can('cached-ability'));
    }

    #[Test()]
    #[TestDox('Supports method chaining by returning self from fluent methods')]
    #[Group('edge-case')]
    public function fluent_methods_return_self_for_chaining(): void
    {
        // Arrange
        $user = User::query()->create();
        $cache = new ArrayStore();
        $clipboard = new Clipboard();
        $gate = new Gate(
            new Container(),
            fn () => $user,
        );

        // Act & Assert - Each method should return the factory instance
        $factory = WardenClass::make();

        $this->assertSame($factory, $factory->withUser($user));
        $this->assertSame($factory, $factory->withCache($cache));
        $this->assertSame($factory, $factory->withClipboard($clipboard));
        $this->assertSame($factory, $factory->withGate($gate));
        $this->assertSame($factory, $factory->registerClipboardAtContainer(false));
        $this->assertSame($factory, $factory->registerAtGate(false));
    }
}
