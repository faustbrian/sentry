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
use Cline\Warden\Contracts\ClipboardInterface;
use Cline\Warden\Database\Ability;
use Cline\Warden\Database\Models;
use Cline\Warden\Guard;
use Illuminate\Auth\Access\Gate;
use Illuminate\Auth\Access\Response;
use Illuminate\Cache\ArrayStore;
use Illuminate\Container\Container;
use InvalidArgumentException;
use Iterator;
use Mockery;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Fixtures\Models\User;
use Tests\TestCase;

/**
 * @internal
 *
 * @author Brian Faust <brian@cline.sh>
 */
#[Small()]
final class GuardTest extends TestCase
{
    private User $user;

    private ClipboardInterface $clipboard;

    private Guard $guard;

    private Gate $gate;

    #[Override()]
    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::query()->create();
        $this->clipboard = new Clipboard();
        $this->guard = new Guard($this->clipboard);
        $this->gate = new Gate(Container::getInstance(), fn () => $this->user);
    }

    #[Override()]
    protected function tearDown(): void
    {
        Models::reset();
        Mockery::close();

        parent::tearDown();
    }

    #[Test()]
    #[TestDox('Gets the clipboard instance')]
    #[Group('happy-path')]
    public function gets_the_clipboard_instance(): void
    {
        // Arrange - clipboard set in setUp

        // Act
        $result = $this->guard->getClipboard();

        // Assert
        $this->assertSame($this->clipboard, $result);
        $this->assertInstanceOf(ClipboardInterface::class, $result);
    }

    #[Test()]
    #[TestDox('Sets a new clipboard instance')]
    #[Group('happy-path')]
    public function sets_a_new_clipboard_instance(): void
    {
        // Arrange
        $newClipboard = new Clipboard();

        // Act
        $result = $this->guard->setClipboard($newClipboard);

        // Assert
        $this->assertSame($this->guard, $result);
        $this->assertSame($newClipboard, $this->guard->getClipboard());
        $this->assertNotSame($this->clipboard, $this->guard->getClipboard());
    }

    #[Test()]
    #[TestDox('Replaces clipboard instance maintaining fluent interface')]
    #[Group('happy-path')]
    public function replaces_clipboard_instance_maintaining_fluent_interface(): void
    {
        // Arrange
        $clipboard1 = new Clipboard();
        $clipboard2 = new CachedClipboard(
            new ArrayStore(),
        );

        // Act
        $guard = $this->guard->setClipboard($clipboard1)->setClipboard($clipboard2);

        // Assert
        $this->assertSame($this->guard, $guard);
        $this->assertSame($clipboard2, $this->guard->getClipboard());
    }

    #[Test()]
    #[TestDox('Returns false when using non-cached clipboard')]
    #[Group('happy-path')]
    public function returns_false_when_using_non_cached_clipboard(): void
    {
        // Arrange - using regular Clipboard from setUp

        // Act
        $result = $this->guard->usesCachedClipboard();

        // Assert
        $this->assertFalse($result);
    }

    #[Test()]
    #[TestDox('Returns true when using cached clipboard')]
    #[Group('happy-path')]
    public function returns_true_when_using_cached_clipboard(): void
    {
        // Arrange
        $cachedClipboard = new CachedClipboard(
            new ArrayStore(),
        );
        $this->guard->setClipboard($cachedClipboard);

        // Act
        $result = $this->guard->usesCachedClipboard();

        // Assert
        $this->assertTrue($result);
    }

    #[Test()]
    #[TestDox('Gets current slot value when called without arguments')]
    #[Group('happy-path')]
    public function gets_current_slot_value_when_called_without_arguments(): void
    {
        // Arrange - default slot is 'after'

        // Act
        $result = $this->guard->slot();

        // Assert
        $this->assertSame('after', $result);
    }

    #[Test()]
    #[TestDox('Sets slot to before and returns guard instance')]
    #[Group('happy-path')]
    public function sets_slot_to_before_and_returns_guard_instance(): void
    {
        // Arrange - default is 'after'

        // Act
        $result = $this->guard->slot('before');

        // Assert
        $this->assertSame($this->guard, $result);
        $this->assertSame('before', $this->guard->slot());
    }

    #[Test()]
    #[TestDox('Sets slot to after and returns guard instance')]
    #[Group('happy-path')]
    public function sets_slot_to_after_and_returns_guard_instance(): void
    {
        // Arrange
        $this->guard->slot('before');

        // Act
        $result = $this->guard->slot('after');

        // Assert
        $this->assertSame($this->guard, $result);
        $this->assertSame('after', $this->guard->slot());
    }

    #[Test()]
    #[TestDox('Throws exception when setting invalid slot value')]
    #[Group('sad-path')]
    public function throws_exception_when_setting_invalid_slot_value(): void
    {
        // Arrange
        $invalidSlot = 'invalid';

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid is an invalid gate slot');
        $this->guard->slot($invalidSlot);
    }

    #[Test()]
    #[TestDox('Throws exception for empty string slot')]
    #[Group('edge-case')]
    public function throws_exception_for_empty_string_slot(): void
    {
        // Arrange
        $emptySlot = '';

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(' is an invalid gate slot');
        $this->guard->slot($emptySlot);
    }

    #[Test()]
    #[TestDox('Registers guard callbacks at gate and returns guard instance')]
    #[Group('happy-path')]
    public function registers_guard_callbacks_at_gate_and_returns_guard_instance(): void
    {
        // Arrange - gate set in setUp

        // Act
        $result = $this->guard->registerAt($this->gate);

        // Assert
        $this->assertSame($this->guard, $result);
    }

    #[Test()]
    #[TestDox('Before callback grants permission when slot is before and ability exists')]
    #[Group('happy-path')]
    public function before_callback_grants_permission_when_slot_is_before_and_ability_exists(): void
    {
        // Arrange
        $this->guard->slot('before');
        $this->guard->registerAt($this->gate);

        $ability = Ability::query()->create(['name' => 'edit-posts']);
        $this->user->abilities()->attach($ability);

        // Act
        $result = $this->gate->allows('edit-posts');

        // Assert
        $this->assertTrue($result);
    }

    #[Test()]
    #[TestDox('Before callback returns null when slot is after')]
    #[Group('happy-path')]
    public function before_callback_returns_null_when_slot_is_after(): void
    {
        // Arrange
        $this->guard->slot('after');
        $this->guard->registerAt($this->gate);

        $ability = Ability::query()->create(['name' => 'edit-posts']);
        $this->user->abilities()->attach($ability);

        // Act
        $result = $this->gate->allows('edit-posts');

        // Assert
        $this->assertTrue($result);
    }

    #[Test()]
    #[TestDox('After callback grants permission when slot is after and ability exists')]
    #[Group('happy-path')]
    public function after_callback_grants_permission_when_slot_is_after_and_ability_exists(): void
    {
        // Arrange
        $this->guard->slot('after');
        $this->guard->registerAt($this->gate);

        $ability = Ability::query()->create(['name' => 'edit-posts']);
        $this->user->abilities()->attach($ability);

        // Act
        $result = $this->gate->allows('edit-posts');

        // Assert
        $this->assertTrue($result);
    }

    #[Test()]
    #[TestDox('After callback returns null when slot is before')]
    #[Group('happy-path')]
    public function after_callback_returns_null_when_slot_is_before(): void
    {
        // Arrange
        $this->guard->slot('before');
        $this->guard->registerAt($this->gate);

        // No ability granted

        // Act
        $result = $this->gate->allows('edit-posts');

        // Assert
        $this->assertFalse($result);
    }

    #[Test()]
    #[TestDox('Before callback denies permission when ability is forbidden')]
    #[Group('sad-path')]
    public function before_callback_denies_permission_when_ability_is_forbidden(): void
    {
        // Arrange
        $this->guard->slot('before');
        $this->guard->registerAt($this->gate);

        $ability = Ability::query()->create(['name' => 'edit-posts']);
        $this->user->abilities()->attach($ability, ['forbidden' => true]);

        // Act
        $result = $this->gate->allows('edit-posts');

        // Assert
        $this->assertFalse($result);
    }

    #[Test()]
    #[TestDox('After callback denies permission when ability is forbidden')]
    #[Group('sad-path')]
    public function after_callback_denies_permission_when_ability_is_forbidden(): void
    {
        // Arrange
        $this->guard->slot('after');
        $this->guard->registerAt($this->gate);

        $ability = Ability::query()->create(['name' => 'edit-posts']);
        $this->user->abilities()->attach($ability, ['forbidden' => true]);

        // Act
        $result = $this->gate->allows('edit-posts');

        // Assert
        $this->assertFalse($result);
    }

    #[Test()]
    #[TestDox('Before callback returns null when no permission exists')]
    #[Group('happy-path')]
    public function before_callback_returns_null_when_no_permission_exists(): void
    {
        // Arrange
        $this->guard->slot('before');
        $this->guard->registerAt($this->gate);

        // No ability granted or forbidden

        // Act
        $result = $this->gate->allows('edit-posts');

        // Assert
        $this->assertFalse($result);
    }

    #[Test()]
    #[TestDox('After callback returns null when no permission exists')]
    #[Group('happy-path')]
    public function after_callback_returns_null_when_no_permission_exists(): void
    {
        // Arrange
        $this->guard->slot('after');
        $this->guard->registerAt($this->gate);

        // No ability granted or forbidden

        // Act
        $result = $this->gate->allows('edit-posts');

        // Assert
        $this->assertFalse($result);
    }

    #[Test()]
    #[TestDox('Before callback handles model instance argument without throwing error')]
    #[Group('happy-path')]
    public function before_callback_handles_model_instance_argument_without_throwing_error(): void
    {
        // Arrange
        $this->guard->slot('before');
        $this->guard->registerAt($this->gate);

        $targetUser = User::query()->create();

        // Act - Should not throw exception even without matching ability
        // This tests that the Guard properly handles Model arguments
        $result = $this->gate->allows('edit', $targetUser);

        // Assert - Will be false since no ability granted, but no exception thrown
        $this->assertFalse($result);
    }

    #[Test()]
    #[TestDox('Before callback handles string model class argument without throwing error')]
    #[Group('happy-path')]
    public function before_callback_handles_string_model_class_argument_without_throwing_error(): void
    {
        // Arrange
        $this->guard->slot('before');
        $this->guard->registerAt($this->gate);

        // Act - Should not throw exception even without matching ability
        // This tests that the Guard properly handles string Model class arguments
        $result = $this->gate->allows('create', User::class);

        // Assert - Will be false since no ability granted, but no exception thrown
        $this->assertFalse($result);
    }

    #[Test()]
    #[TestDox('Before callback skips check when more than 2 arguments provided')]
    #[Group('edge-case')]
    public function before_callback_skips_check_when_more_than_two_arguments_provided(): void
    {
        // Arrange
        $this->guard->slot('before');
        $this->guard->registerAt($this->gate);

        $targetUser = User::query()->create();

        // Act
        $result = $this->gate->allows('edit', [$targetUser, 'extra', 'arguments']);

        // Assert
        $this->assertFalse($result);
    }

    #[Test()]
    #[TestDox('After callback skips check when more than 2 arguments provided')]
    #[Group('edge-case')]
    public function after_callback_skips_check_when_more_than_two_arguments_provided(): void
    {
        // Arrange
        $this->guard->slot('after');
        $this->guard->registerAt($this->gate);

        $targetUser = User::query()->create();

        // Act
        $result = $this->gate->allows('edit', [$targetUser, 'extra', 'arguments']);

        // Assert
        $this->assertFalse($result);
    }

    #[Test()]
    #[TestDox('Before callback skips check when argument is not Model or string')]
    #[Group('edge-case')]
    public function before_callback_skips_check_when_argument_is_not_model_or_string(): void
    {
        // Arrange
        $this->guard->slot('before');
        $this->guard->registerAt($this->gate);

        // Act
        $result = $this->gate->allows('edit', [123]);

        // Assert
        $this->assertFalse($result);
    }

    #[Test()]
    #[TestDox('After callback skips check when argument is not Model or string')]
    #[Group('edge-case')]
    public function after_callback_skips_check_when_argument_is_not_model_or_string(): void
    {
        // Arrange
        $this->guard->slot('after');
        $this->guard->registerAt($this->gate);

        // Act
        $result = $this->gate->allows('edit', [123]);

        // Assert
        $this->assertFalse($result);
    }

    #[Test()]
    #[TestDox('After callback respects existing policy result')]
    #[Group('happy-path')]
    public function after_callback_respects_existing_policy_result(): void
    {
        // Arrange
        $this->guard->slot('after');
        $this->guard->registerAt($this->gate);

        // Define a policy that returns true
        $this->gate->define('custom-ability', fn (): true => true);

        // User has no abilities granted, but policy says yes
        // After callback should respect the policy result

        // Act
        $result = $this->gate->allows('custom-ability');

        // Assert
        $this->assertTrue($result);
    }

    #[Test()]
    #[TestDox('After callback provides result when no policy exists')]
    #[Group('happy-path')]
    public function after_callback_provides_result_when_no_policy_exists(): void
    {
        // Arrange
        $this->guard->slot('after');
        $this->guard->registerAt($this->gate);

        $ability = Ability::query()->create(['name' => 'publish-posts']);
        $this->user->abilities()->attach($ability);

        // Act
        $result = $this->gate->allows('publish-posts');

        // Assert
        $this->assertTrue($result);
    }

    #[Test()]
    #[TestDox('Before callback can override policy when running before policies')]
    #[Group('happy-path')]
    public function before_callback_can_override_policy_when_running_before_policies(): void
    {
        // Arrange
        $this->guard->slot('before');
        $this->guard->registerAt($this->gate);

        // Policy denies access
        $this->gate->define('restricted-action', fn (): false => false);

        // But Warden grants permission
        $ability = Ability::query()->create(['name' => 'restricted-action']);
        $this->user->abilities()->attach($ability);

        // Act
        $result = $this->gate->allows('restricted-action');

        // Assert
        $this->assertTrue($result); // Warden overrides policy
    }

    #[Test()]
    #[TestDox('Clipboard check returns Response object with message when permission granted')]
    #[Group('happy-path')]
    public function clipboard_check_returns_response_object_with_message_when_permission_granted(): void
    {
        // Arrange
        $this->guard->slot('before');
        $this->guard->registerAt($this->gate);

        $ability = Ability::query()->create(['name' => 'test-ability']);
        $this->user->abilities()->attach($ability);

        // Act
        $response = $this->gate->inspect('test-ability');

        // Assert
        $this->assertInstanceOf(Response::class, $response);
        $this->assertTrue($response->allowed());
        $this->assertStringContainsString('Bouncer granted permission via ability #', (string) $response->message());
        $this->assertStringContainsString((string) $ability->id, (string) $response->message());
    }

    #[Test()]
    #[TestDox('Works with cached clipboard implementation')]
    #[Group('happy-path')]
    public function works_with_cached_clipboard_implementation(): void
    {
        // Arrange
        $cachedClipboard = new CachedClipboard(
            new ArrayStore(),
        );
        $guard = new Guard($cachedClipboard);
        $guard->slot('before');
        $guard->registerAt($this->gate);

        $ability = Ability::query()->create(['name' => 'cached-ability']);
        $this->user->abilities()->attach($ability);

        // Act
        $result1 = $this->gate->allows('cached-ability');
        $result2 = $this->gate->allows('cached-ability'); // Should hit cache

        // Assert
        $this->assertTrue($result1);
        $this->assertTrue($result2);
        $this->assertTrue($guard->usesCachedClipboard());
    }

    #[Test()]
    #[TestDox('Slot setting is chainable with other methods')]
    #[Group('happy-path')]
    public function slot_setting_is_chainable_with_other_methods(): void
    {
        // Arrange
        $newClipboard = new Clipboard();

        // Act
        $result = $this->guard->slot('before')->setClipboard($newClipboard);

        // Assert
        $this->assertSame($this->guard, $result);
        $this->assertSame('before', $this->guard->slot());
        $this->assertSame($newClipboard, $this->guard->getClipboard());
    }

    #[Test()]
    #[TestDox('RegisterAt is chainable with slot configuration')]
    #[Group('happy-path')]
    public function register_at_is_chainable_with_slot_configuration(): void
    {
        // Arrange
        $guard = new Guard(
            new Clipboard(),
        );

        // Act
        $result = $guard->slot('before')->registerAt($this->gate);

        // Assert
        $this->assertSame($guard, $result);
        $this->assertSame('before', $guard->slot());
    }

    #[Test()]
    #[DataProvider('provideThrows_exception_for_various_invalid_slot_valuesCases')]
    #[TestDox('Throws exception for various invalid slot values')]
    #[Group('edge-case')]
    public function throws_exception_for_various_invalid_slot_values(string $invalidValue): void
    {
        // Arrange - various invalid values provided by data provider

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $this->guard->slot($invalidValue);
    }

    /**
     * Data provider for invalid slot values.
     *
     * @return Iterator<string, array<string>>
     */
    public static function provideThrows_exception_for_various_invalid_slot_valuesCases(): iterable
    {
        yield 'random string' => ['random'];

        yield 'uppercase BEFORE' => ['BEFORE'];

        yield 'uppercase AFTER' => ['AFTER'];

        yield 'mixed case Before' => ['Before'];

        yield 'numeric string' => ['123'];

        yield 'special chars' => ['@#$'];

        yield 'whitespace' => ['   '];

        yield 'before with space' => ['before '];

        yield 'after with space' => [' after'];
    }

    #[Test()]
    #[TestDox('Before callback handles null model argument')]
    #[Group('happy-path')]
    public function before_callback_handles_null_model_argument(): void
    {
        // Arrange
        $this->guard->slot('before');
        $this->guard->registerAt($this->gate);

        $ability = Ability::query()->create(['name' => 'global-ability']);
        $this->user->abilities()->attach($ability);

        // Act
        $result = $this->gate->allows('global-ability', [null]);

        // Assert
        $this->assertTrue($result);
    }

    #[Test()]
    #[TestDox('After callback handles null model argument')]
    #[Group('happy-path')]
    public function after_callback_handles_null_model_argument(): void
    {
        // Arrange
        $this->guard->slot('after');
        $this->guard->registerAt($this->gate);

        $ability = Ability::query()->create(['name' => 'global-ability']);
        $this->user->abilities()->attach($ability);

        // Act
        $result = $this->gate->allows('global-ability', [null]);

        // Assert
        $this->assertTrue($result);
    }

    #[Test()]
    #[TestDox('Multiple guard instances can be registered at same gate')]
    #[Group('edge-case')]
    public function multiple_guard_instances_can_be_registered_at_same_gate(): void
    {
        // Arrange
        $guard1 = new Guard(
            new Clipboard(),
        );
        $guard2 = new Guard(
            new Clipboard(),
        );

        // Act
        $result1 = $guard1->registerAt($this->gate);
        $result2 = $guard2->registerAt($this->gate);

        // Assert
        $this->assertSame($guard1, $result1);
        $this->assertSame($guard2, $result2);
    }
}
