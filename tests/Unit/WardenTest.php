<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit;

use Cline\Warden\Clipboard\CachedClipboard;
use Cline\Warden\Clipboard\Clipboard;
use Cline\Warden\Conductors\AssignsRoles;
use Cline\Warden\Conductors\ChecksRoles;
use Cline\Warden\Conductors\ForbidsAbilities;
use Cline\Warden\Conductors\GivesAbilities;
use Cline\Warden\Conductors\RemovesAbilities;
use Cline\Warden\Conductors\RemovesRoles;
use Cline\Warden\Conductors\SyncsRolesAndAbilities;
use Cline\Warden\Conductors\UnforbidsAbilities;
use Cline\Warden\Contracts\ClipboardInterface;
use Cline\Warden\Contracts\ScopeInterface;
use Cline\Warden\Database\Ability;
use Cline\Warden\Database\Models;
use Cline\Warden\Database\Role;
use Cline\Warden\Database\Scope\Scope;
use Cline\Warden\Factory;
use Cline\Warden\Guard;
use Cline\Warden\Warden;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Access\Gate;
use Illuminate\Auth\Access\Response;
use Illuminate\Cache\ArrayStore;
use Illuminate\Container\Container;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use RuntimeException;
use Tests\Fixtures\Models\User;
use Tests\TestCase;

/**
 * @internal
 *
 * @author Brian Faust <brian@cline.sh>
 */
#[Small()]
final class WardenTest extends TestCase
{
    private User $user;

    private Guard $guard;

    private Warden $warden;

    #[Override()]
    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::query()->create();
        $gate = new Gate(Container::getInstance(), fn () => $this->user);
        $this->guard = new Guard(
            new Clipboard(),
        );
        $this->warden = new Warden($this->guard);
        $this->warden->setGate($gate);

        $this->guard->registerAt($gate);
    }

    #[Override()]
    protected function tearDown(): void
    {
        Models::reset();

        parent::tearDown();
    }

    #[Test()]
    #[TestDox('Creates Warden instance with default configuration')]
    #[Group('happy-path')]
    public function creates_warden_instance_with_default_configuration(): void
    {
        // Arrange - N/A (static factory method)

        // Act
        $warden = Warden::create();

        // Assert
        $this->assertInstanceOf(Warden::class, $warden);
        $this->assertInstanceOf(Gate::class, $warden->getGate());
        $this->assertTrue($warden->usesCachedClipboard());
    }

    #[Test()]
    #[TestDox('Creates Warden instance with user')]
    #[Group('happy-path')]
    public function creates_warden_instance_with_user(): void
    {
        // Arrange
        $user = User::query()->create();

        // Act
        $warden = Warden::create($user);

        // Assert
        $this->assertInstanceOf(Warden::class, $warden);
        $this->assertInstanceOf(Gate::class, $warden->getGate());
    }

    #[Test()]
    #[TestDox('Creates factory instance for custom configuration')]
    #[Group('happy-path')]
    public function creates_factory_instance_for_custom_configuration(): void
    {
        // Arrange - N/A (static factory method)

        // Act
        $factory = Warden::make();

        // Assert
        $this->assertInstanceOf(Factory::class, $factory);
    }

    #[Test()]
    #[TestDox('Creates factory with user')]
    #[Group('happy-path')]
    public function creates_factory_with_user(): void
    {
        // Arrange
        $user = User::query()->create();

        // Act
        $factory = Warden::make($user);

        // Assert
        $this->assertInstanceOf(Factory::class, $factory);
    }

    #[Test()]
    #[TestDox('Returns GivesAbilities conductor for authority')]
    #[Group('happy-path')]
    public function returns_gives_abilities_conductor_for_authority(): void
    {
        // Arrange
        $user = User::query()->create();

        // Act
        $conductor = $this->warden->allow($user);

        // Assert
        $this->assertInstanceOf(GivesAbilities::class, $conductor);
    }

    #[Test()]
    #[TestDox('Returns GivesAbilities conductor for everyone')]
    #[Group('happy-path')]
    public function returns_gives_abilities_conductor_for_everyone(): void
    {
        // Arrange - N/A (no specific authority)

        // Act
        $conductor = $this->warden->allowEveryone();

        // Assert
        $this->assertInstanceOf(GivesAbilities::class, $conductor);
    }

    #[Test()]
    #[TestDox('Returns RemovesAbilities conductor for authority')]
    #[Group('happy-path')]
    public function returns_removes_abilities_conductor_for_authority(): void
    {
        // Arrange
        $user = User::query()->create();

        // Act
        $conductor = $this->warden->disallow($user);

        // Assert
        $this->assertInstanceOf(RemovesAbilities::class, $conductor);
    }

    #[Test()]
    #[TestDox('Returns RemovesAbilities conductor for everyone')]
    #[Group('happy-path')]
    public function returns_removes_abilities_conductor_for_everyone(): void
    {
        // Arrange - N/A (no specific authority)

        // Act
        $conductor = $this->warden->disallowEveryone();

        // Assert
        $this->assertInstanceOf(RemovesAbilities::class, $conductor);
    }

    #[Test()]
    #[TestDox('Returns ForbidsAbilities conductor for authority')]
    #[Group('happy-path')]
    public function returns_forbids_abilities_conductor_for_authority(): void
    {
        // Arrange
        $user = User::query()->create();

        // Act
        $conductor = $this->warden->forbid($user);

        // Assert
        $this->assertInstanceOf(ForbidsAbilities::class, $conductor);
    }

    #[Test()]
    #[TestDox('Returns ForbidsAbilities conductor for everyone')]
    #[Group('happy-path')]
    public function returns_forbids_abilities_conductor_for_everyone(): void
    {
        // Arrange - N/A (no specific authority)

        // Act
        $conductor = $this->warden->forbidEveryone();

        // Assert
        $this->assertInstanceOf(ForbidsAbilities::class, $conductor);
    }

    #[Test()]
    #[TestDox('Returns UnforbidsAbilities conductor for authority')]
    #[Group('happy-path')]
    public function returns_unforbids_abilities_conductor_for_authority(): void
    {
        // Arrange
        $user = User::query()->create();

        // Act
        $conductor = $this->warden->unforbid($user);

        // Assert
        $this->assertInstanceOf(UnforbidsAbilities::class, $conductor);
    }

    #[Test()]
    #[TestDox('Returns UnforbidsAbilities conductor for everyone')]
    #[Group('happy-path')]
    public function returns_unforbids_abilities_conductor_for_everyone(): void
    {
        // Arrange - N/A (no specific authority)

        // Act
        $conductor = $this->warden->unforbidEveryone();

        // Assert
        $this->assertInstanceOf(UnforbidsAbilities::class, $conductor);
    }

    #[Test()]
    #[TestDox('Returns AssignsRoles conductor')]
    #[Group('happy-path')]
    public function returns_assigns_roles_conductor(): void
    {
        // Arrange
        $role = Role::query()->create(['name' => 'admin']);

        // Act
        $conductor = $this->warden->assign($role);

        // Assert
        $this->assertInstanceOf(AssignsRoles::class, $conductor);
    }

    #[Test()]
    #[TestDox('Returns RemovesRoles conductor')]
    #[Group('happy-path')]
    public function returns_removes_roles_conductor(): void
    {
        // Arrange
        $role = Role::query()->create(['name' => 'admin']);

        // Act
        $conductor = $this->warden->retract($role);

        // Assert
        $this->assertInstanceOf(RemovesRoles::class, $conductor);
    }

    #[Test()]
    #[TestDox('Returns SyncsRolesAndAbilities conductor')]
    #[Group('happy-path')]
    public function returns_syncs_roles_and_abilities_conductor(): void
    {
        // Arrange
        $user = User::query()->create();

        // Act
        $conductor = $this->warden->sync($user);

        // Assert
        $this->assertInstanceOf(SyncsRolesAndAbilities::class, $conductor);
    }

    #[Test()]
    #[TestDox('Returns ChecksRoles conductor')]
    #[Group('happy-path')]
    public function returns_checks_roles_conductor(): void
    {
        // Arrange
        $user = User::query()->create();

        // Act
        $conductor = $this->warden->is($user);

        // Assert
        $this->assertInstanceOf(ChecksRoles::class, $conductor);
    }

    #[Test()]
    #[TestDox('Gets clipboard instance')]
    #[Group('happy-path')]
    public function gets_clipboard_instance(): void
    {
        // Arrange - N/A (clipboard set in setUp)

        // Act
        $clipboard = $this->warden->getClipboard();

        // Assert
        $this->assertInstanceOf(ClipboardInterface::class, $clipboard);
    }

    #[Test()]
    #[TestDox('Sets clipboard instance')]
    #[Group('happy-path')]
    public function sets_clipboard_instance(): void
    {
        // Arrange
        $newClipboard = new Clipboard();

        // Act
        $result = $this->warden->setClipboard($newClipboard);

        // Assert
        $this->assertSame($this->warden, $result);
        $this->assertSame($newClipboard, $this->warden->getClipboard());
    }

    #[Test()]
    #[TestDox('Registers clipboard in container')]
    #[Group('happy-path')]
    public function registers_clipboard_in_container(): void
    {
        // Arrange
        $clipboard = new Clipboard();
        $this->warden->setClipboard($clipboard);

        // Act
        $result = $this->warden->registerClipboardAtContainer();

        // Assert
        $this->assertSame($this->warden, $result);
        $this->assertSame($clipboard, Container::getInstance()->make(ClipboardInterface::class));
    }

    #[Test()]
    #[TestDox('Enables cached clipboard with default cache store')]
    #[Group('happy-path')]
    public function enables_cached_clipboard_with_default_cache_store(): void
    {
        // Arrange
        Container::getInstance()->make(CacheRepository::class);

        // Act
        $result = $this->warden->cache();

        // Assert
        $this->assertSame($this->warden, $result);
        $this->assertTrue($this->warden->usesCachedClipboard());
    }

    #[Test()]
    #[TestDox('Updates cache store when cached clipboard already exists')]
    #[Group('happy-path')]
    public function updates_cache_store_when_cached_clipboard_exists(): void
    {
        // Arrange
        $this->warden->cache();
        $customStore = new ArrayStore();

        // Act
        $result = $this->warden->cache($customStore);

        // Assert
        $this->assertSame($this->warden, $result);
        $this->assertTrue($this->warden->usesCachedClipboard());
        $clipboard = $this->warden->getClipboard();
        $this->assertInstanceOf(CachedClipboard::class, $clipboard);
        // Cache may be wrapped in TaggedCache if store supports tags
        $cache = $clipboard->getCache();
        $this->assertNotNull($cache);
    }

    #[Test()]
    #[TestDox('Disables clipboard caching')]
    #[Group('happy-path')]
    public function disables_clipboard_caching(): void
    {
        // Arrange
        $this->warden->cache();

        // Act
        $result = $this->warden->dontCache();

        // Assert
        $this->assertSame($this->warden, $result);
        $this->assertFalse($this->warden->usesCachedClipboard());
    }

    #[Test()]
    #[TestDox('Refreshes cache for all authorities')]
    #[Group('happy-path')]
    public function refreshes_cache_for_all_authorities(): void
    {
        // Arrange
        $this->warden->cache();

        // Act
        $result = $this->warden->refresh();

        // Assert
        $this->assertSame($this->warden, $result);
    }

    #[Test()]
    #[TestDox('Refreshes cache for specific authority')]
    #[Group('happy-path')]
    public function refreshes_cache_for_specific_authority(): void
    {
        // Arrange
        $this->warden->cache();
        $user = User::query()->create();

        // Act
        $result = $this->warden->refresh($user);

        // Assert
        $this->assertSame($this->warden, $result);
    }

    #[Test()]
    #[TestDox('Refreshes cache using refreshFor method')]
    #[Group('happy-path')]
    public function refreshes_cache_using_refresh_for_method(): void
    {
        // Arrange
        $this->warden->cache();
        $user = User::query()->create();

        // Act
        $result = $this->warden->refreshFor($user);

        // Assert
        $this->assertSame($this->warden, $result);
    }

    #[Test()]
    #[TestDox('Does not refresh when not using cached clipboard')]
    #[Group('edge-case')]
    public function does_not_refresh_when_not_using_cached_clipboard(): void
    {
        // Arrange
        $this->warden->dontCache();
        $user = User::query()->create();

        // Act
        $result = $this->warden->refresh($user);

        // Assert
        $this->assertSame($this->warden, $result);
    }

    #[Test()]
    #[TestDox('Sets gate instance')]
    #[Group('happy-path')]
    public function sets_gate_instance(): void
    {
        // Arrange
        $gate = new Gate(Container::getInstance(), fn (): User => $this->user);

        // Act
        $result = $this->warden->setGate($gate);

        // Assert
        $this->assertSame($this->warden, $result);
        $this->assertSame($gate, $this->warden->getGate());
    }

    #[Test()]
    #[TestDox('Gets gate instance')]
    #[Group('happy-path')]
    public function gets_gate_instance(): void
    {
        // Arrange
        $gate = new Gate(Container::getInstance(), fn (): User => $this->user);
        $this->warden->setGate($gate);

        // Act
        $result = $this->warden->getGate();

        // Assert
        $this->assertSame($gate, $result);
    }

    #[Test()]
    #[TestDox('Returns gate instance with gate() method')]
    #[Group('happy-path')]
    public function returns_gate_instance_with_gate_method(): void
    {
        // Arrange
        $gate = new Gate(Container::getInstance(), fn (): User => $this->user);
        $this->warden->setGate($gate);

        // Act
        $result = $this->warden->gate();

        // Assert
        $this->assertSame($gate, $result);
    }

    #[Test()]
    #[TestDox('Throws exception when gate not set')]
    #[Group('sad-path')]
    public function throws_exception_when_gate_not_set(): void
    {
        // Arrange
        $warden = new Warden($this->guard);

        // Act & Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The gate instance has not been set.');
        $warden->gate();
    }

    #[Test()]
    #[TestDox('Checks if using cached clipboard')]
    #[Group('happy-path')]
    public function checks_if_using_cached_clipboard(): void
    {
        // Arrange
        $this->warden->cache();

        // Act
        $result = $this->warden->usesCachedClipboard();

        // Assert
        $this->assertTrue($result);
    }

    #[Test()]
    #[TestDox('Defines custom ability at gate')]
    #[Group('happy-path')]
    public function defines_custom_ability_at_gate(): void
    {
        // Arrange
        $callback = fn (): true => true;

        // Act
        $result = $this->warden->define('custom-ability', $callback);

        // Assert
        $this->assertSame($this->warden, $result);
    }

    #[Test()]
    #[TestDox('Authorizes ability successfully')]
    #[Group('happy-path')]
    public function authorizes_ability_successfully(): void
    {
        // Arrange
        $this->warden->allow($this->user)->to('test-ability');

        // Act
        $result = $this->warden->authorize('test-ability');

        // Assert
        $this->assertInstanceOf(Response::class, $result);
        $this->assertTrue($result->allowed());
    }

    #[Test()]
    #[TestDox('Throws authorization exception when ability denied')]
    #[Group('sad-path')]
    public function throws_authorization_exception_when_ability_denied(): void
    {
        // Arrange - No ability granted

        // Act & Assert
        $this->expectException(AuthorizationException::class);
        $this->warden->authorize('non-existent-ability');
    }

    #[Test()]
    #[TestDox('Checks if ability is allowed')]
    #[Group('happy-path')]
    public function checks_if_ability_is_allowed(): void
    {
        // Arrange
        $this->warden->allow($this->user)->to('test-ability');

        // Act
        $result = $this->warden->can('test-ability');

        // Assert
        $this->assertTrue($result);
    }

    #[Test()]
    #[TestDox('Returns false when ability not allowed')]
    #[Group('happy-path')]
    public function returns_false_when_ability_not_allowed(): void
    {
        // Arrange - No ability granted

        // Act
        $result = $this->warden->can('non-existent-ability');

        // Assert
        $this->assertFalse($result);
    }

    #[Test()]
    #[TestDox('Checks if any abilities are allowed')]
    #[Group('happy-path')]
    public function checks_if_any_abilities_are_allowed(): void
    {
        // Arrange
        $this->warden->allow($this->user)->to('ability-one');

        // Act
        $result = $this->warden->canAny(['ability-one', 'ability-two']);

        // Assert
        $this->assertTrue($result);
    }

    #[Test()]
    #[TestDox('Returns false when no abilities allowed')]
    #[Group('happy-path')]
    public function returns_false_when_no_abilities_allowed(): void
    {
        // Arrange - No abilities granted

        // Act
        $result = $this->warden->canAny(['ability-one', 'ability-two']);

        // Assert
        $this->assertFalse($result);
    }

    #[Test()]
    #[TestDox('Checks if ability is denied')]
    #[Group('happy-path')]
    public function checks_if_ability_is_denied(): void
    {
        // Arrange - No ability granted

        // Act
        $result = $this->warden->cannot('non-existent-ability');

        // Assert
        $this->assertTrue($result);
    }

    #[Test()]
    #[TestDox('Returns false when ability allowed')]
    #[Group('happy-path')]
    public function returns_false_when_ability_allowed(): void
    {
        // Arrange
        $this->warden->allow($this->user)->to('test-ability');

        // Act
        $result = $this->warden->cannot('test-ability');

        // Assert
        $this->assertFalse($result);
    }

    #[Test()]
    #[TestDox('Checks ability with deprecated allows() method')]
    #[Group('happy-path')]
    public function checks_ability_with_deprecated_allows_method(): void
    {
        // Arrange
        $this->warden->allow($this->user)->to('test-ability');

        // Act
        $result = $this->warden->allows('test-ability');

        // Assert
        $this->assertTrue($result);
    }

    #[Test()]
    #[TestDox('Checks ability with deprecated denies() method')]
    #[Group('happy-path')]
    public function checks_ability_with_deprecated_denies_method(): void
    {
        // Arrange - No ability granted

        // Act
        $result = $this->warden->denies('non-existent-ability');

        // Assert
        $this->assertTrue($result);
    }

    #[Test()]
    #[TestDox('Creates role instance')]
    #[Group('happy-path')]
    public function creates_role_instance(): void
    {
        // Arrange
        $attributes = ['name' => 'admin'];

        // Act
        $role = $this->warden->role($attributes);

        // Assert
        $this->assertInstanceOf(Role::class, $role);
        $this->assertEquals('admin', $role->name);
    }

    #[Test()]
    #[TestDox('Creates ability instance')]
    #[Group('happy-path')]
    public function creates_ability_instance(): void
    {
        // Arrange
        $attributes = ['name' => 'edit-posts'];

        // Act
        $ability = $this->warden->ability($attributes);

        // Assert
        $this->assertInstanceOf(Ability::class, $ability);
        $this->assertEquals('edit-posts', $ability->name);
    }

    #[Test()]
    #[TestDox('Configures to run before policies')]
    #[Group('happy-path')]
    public function configures_to_run_before_policies(): void
    {
        // Arrange - N/A (default is 'after')

        // Act
        $result = $this->warden->runBeforePolicies(true);

        // Assert
        $this->assertSame($this->warden, $result);
        $this->assertSame('before', $this->guard->slot());
    }

    #[Test()]
    #[TestDox('Configures to run after policies')]
    #[Group('happy-path')]
    public function configures_to_run_after_policies(): void
    {
        // Arrange
        $this->warden->runBeforePolicies(true);

        // Act
        $result = $this->warden->runBeforePolicies(false);

        // Assert
        $this->assertSame($this->warden, $result);
        $this->assertSame('after', $this->guard->slot());
    }

    #[Test()]
    #[TestDox('Configures ownership via attribute')]
    #[Group('happy-path')]
    public function configures_ownership_via_attribute(): void
    {
        // Arrange
        $model = User::class;
        $attribute = 'user_id';

        // Act
        $result = $this->warden->ownedVia($model, $attribute);

        // Assert
        $this->assertSame($this->warden, $result);
    }

    #[Test()]
    #[TestDox('Sets custom ability model')]
    #[Group('happy-path')]
    public function sets_custom_ability_model(): void
    {
        // Arrange
        $customModel = Ability::class;

        // Act
        $result = $this->warden->useAbilityModel($customModel);

        // Assert
        $this->assertSame($this->warden, $result);
    }

    #[Test()]
    #[TestDox('Throws exception for non-existent ability model')]
    #[Group('sad-path')]
    public function throws_exception_for_non_existent_ability_model(): void
    {
        // Arrange
        $invalidModel = 'App\\NonExistentAbilityModel';

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Class App\\NonExistentAbilityModel does not exist');
        $this->warden->useAbilityModel($invalidModel);
    }

    #[Test()]
    #[TestDox('Sets custom role model')]
    #[Group('happy-path')]
    public function sets_custom_role_model(): void
    {
        // Arrange
        $customModel = Role::class;

        // Act
        $result = $this->warden->useRoleModel($customModel);

        // Assert
        $this->assertSame($this->warden, $result);
    }

    #[Test()]
    #[TestDox('Throws exception for non-existent role model')]
    #[Group('sad-path')]
    public function throws_exception_for_non_existent_role_model(): void
    {
        // Arrange
        $invalidModel = 'App\\NonExistentRoleModel';

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Class App\\NonExistentRoleModel does not exist');
        $this->warden->useRoleModel($invalidModel);
    }

    #[Test()]
    #[TestDox('Sets custom user model')]
    #[Group('happy-path')]
    public function sets_custom_user_model(): void
    {
        // Arrange
        $customModel = User::class;

        // Act
        $result = $this->warden->useUserModel($customModel);

        // Assert
        $this->assertSame($this->warden, $result);
    }

    #[Test()]
    #[TestDox('Throws exception for non-existent user model')]
    #[Group('sad-path')]
    public function throws_exception_for_non_existent_user_model(): void
    {
        // Arrange
        $invalidModel = 'App\\NonExistentUserModel';

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Class App\\NonExistentUserModel does not exist');
        $this->warden->useUserModel($invalidModel);
    }

    #[Test()]
    #[TestDox('Configures custom table names')]
    #[Group('happy-path')]
    public function configures_custom_table_names(): void
    {
        // Arrange
        $tableMap = [
            'abilities' => 'custom_abilities',
            'roles' => 'custom_roles',
        ];

        // Act
        $result = $this->warden->tables($tableMap);

        // Assert
        $this->assertSame($this->warden, $result);
        $this->assertSame('custom_abilities', Models::table('abilities'));
        $this->assertSame('custom_roles', Models::table('roles'));
    }

    #[Test()]
    #[TestDox('Gets and sets scope instance')]
    #[Group('happy-path')]
    public function gets_and_sets_scope_instance(): void
    {
        // Arrange
        $scope = new Scope();

        // Act
        $result = $this->warden->scope($scope);

        // Assert
        $this->assertSame($scope, $result);
    }

    #[Test()]
    #[TestDox('Gets current scope instance')]
    #[Group('happy-path')]
    public function gets_current_scope_instance(): void
    {
        // Arrange - N/A (default scope)

        // Act
        $result = $this->warden->scope();

        // Assert
        $this->assertInstanceOf(ScopeInterface::class, $result);
    }
}
