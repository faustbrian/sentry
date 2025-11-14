<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use Cline\Warden\Support\Helpers;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use stdClass;
use Tests\Fixtures\Models\User;

/**
 * @internal
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class HelpersTest extends TestCase
{
    #[Test()]
    #[TestDox('extractModelAndKeys() returns null when keys parameter is provided but model is not a Model instance')]
    #[Group('edge-case')]
    public function extract_model_and_keys_returns_null_for_non_model_with_keys(): void
    {
        // Arrange
        $nonModel = new stdClass();
        $keys = [1, 2, 3];

        // Act
        $result = Helpers::extractModelAndKeys($nonModel, $keys);

        // Assert
        $this->assertNull($result);
    }

    #[Test()]
    #[TestDox('extractModelAndKeys() returns null when Collection is empty')]
    #[Group('edge-case')]
    public function extract_model_and_keys_returns_null_for_empty_collection(): void
    {
        // Arrange
        $collection = new Collection([]);

        // Act
        $result = Helpers::extractModelAndKeys($collection);

        // Assert
        $this->assertNull($result);
    }

    #[Test()]
    #[TestDox('extractModelAndKeys() returns null for unhandled input types')]
    #[Group('edge-case')]
    public function extract_model_and_keys_returns_null_for_unhandled_input(): void
    {
        // Arrange
        $unhandledInput = 'string-input';

        // Act
        $result = Helpers::extractModelAndKeys($unhandledInput);

        // Assert
        $this->assertNull($result);
    }

    #[Test()]
    #[TestDox('extractModelAndKeys() returns null for integer input')]
    #[Group('edge-case')]
    public function extract_model_and_keys_returns_null_for_integer_input(): void
    {
        // Arrange
        $unhandledInput = 123;

        // Act
        $result = Helpers::extractModelAndKeys($unhandledInput);

        // Assert
        $this->assertNull($result);
    }

    #[Test()]
    #[TestDox('extractModelAndKeys() returns null for array input')]
    #[Group('edge-case')]
    public function extract_model_and_keys_returns_null_for_array_input(): void
    {
        // Arrange
        $unhandledInput = [1, 2, 3];

        // Act
        $result = Helpers::extractModelAndKeys($unhandledInput);

        // Assert
        $this->assertNull($result);
    }

    #[Test()]
    #[TestDox('isIndexedArray() returns false for non-array input (string)')]
    #[Group('edge-case')]
    public function is_indexed_array_returns_false_for_string(): void
    {
        // Arrange
        $nonArray = 'not-an-array';

        // Act
        $result = Helpers::isIndexedArray($nonArray);

        // Assert
        $this->assertFalse($result);
    }

    #[Test()]
    #[TestDox('isIndexedArray() returns false for non-array input (integer)')]
    #[Group('edge-case')]
    public function is_indexed_array_returns_false_for_integer(): void
    {
        // Arrange
        $nonArray = 42;

        // Act
        $result = Helpers::isIndexedArray($nonArray);

        // Assert
        $this->assertFalse($result);
    }

    #[Test()]
    #[TestDox('isIndexedArray() returns false for non-array input (object)')]
    #[Group('edge-case')]
    public function is_indexed_array_returns_false_for_object(): void
    {
        // Arrange
        $nonArray = new stdClass();

        // Act
        $result = Helpers::isIndexedArray($nonArray);

        // Assert
        $this->assertFalse($result);
    }

    #[Test()]
    #[TestDox('isIndexedArray() returns false for non-array input (null)')]
    #[Group('edge-case')]
    public function is_indexed_array_returns_false_for_null(): void
    {
        // Arrange
        $nonArray = null;

        // Act
        $result = Helpers::isIndexedArray($nonArray);

        // Assert
        $this->assertFalse($result);
    }

    #[Test()]
    #[TestDox('mapAuthorityByClass() maps integer authority to User class')]
    #[Group('happy-path')]
    public function map_authority_by_class_maps_integer_to_user_class(): void
    {
        // Arrange
        $authorities = [1, 2, 3];

        // Act
        $result = Helpers::mapAuthorityByClass($authorities);

        // Assert
        $this->assertArrayHasKey(User::class, $result);
        $this->assertEquals([1, 2, 3], $result[User::class]);
    }

    #[Test()]
    #[TestDox('mapAuthorityByClass() maps string authority to User class')]
    #[Group('happy-path')]
    public function map_authority_by_class_maps_string_to_user_class(): void
    {
        // Arrange
        $authorities = ['uuid-1', 'uuid-2'];

        // Act
        $result = Helpers::mapAuthorityByClass($authorities);

        // Assert
        $this->assertArrayHasKey(User::class, $result);
        $this->assertEquals(['uuid-1', 'uuid-2'], $result[User::class]);
    }

    #[Test()]
    #[TestDox('mapAuthorityByClass() maps mixed integer and string authorities to User class')]
    #[Group('happy-path')]
    public function map_authority_by_class_maps_mixed_non_models_to_user_class(): void
    {
        // Arrange
        $authorities = [1, 'uuid-1', 2, 'uuid-2'];

        // Act
        $result = Helpers::mapAuthorityByClass($authorities);

        // Assert
        $this->assertArrayHasKey(User::class, $result);
        $this->assertEquals([1, 'uuid-1', 2, 'uuid-2'], $result[User::class]);
    }

    #[Test()]
    #[TestDox('mapAuthorityByClass() maps Model instances and non-Model values separately')]
    #[Group('happy-path')]
    public function map_authority_by_class_handles_mixed_models_and_primitives(): void
    {
        // Arrange
        $user1 = User::query()->create();
        $user2 = User::query()->create();
        $authorities = [$user1, 123, $user2, 'uuid-1'];

        // Act
        $result = Helpers::mapAuthorityByClass($authorities);

        // Assert
        $this->assertArrayHasKey(User::class, $result);
        $this->assertCount(4, $result[User::class]);
        $this->assertContains($user1->id, $result[User::class]);
        $this->assertContains($user2->id, $result[User::class]);
        $this->assertContains(123, $result[User::class]);
        $this->assertContains('uuid-1', $result[User::class]);
    }

    #[Test()]
    #[TestDox('extractModelAndKeys() successfully extracts model and keys from string class with keys array')]
    #[Group('happy-path')]
    public function extract_model_and_keys_succeeds_with_string_class_and_keys(): void
    {
        // Arrange
        $modelClass = User::class;
        $keys = [1, 2, 3];

        // Act
        $result = Helpers::extractModelAndKeys($modelClass, $keys);

        // Assert
        $this->assertNotNull($result);
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertInstanceOf(User::class, $result[0]);
        $this->assertEquals($keys, $result[1]);
    }

    #[Test()]
    #[TestDox('extractModelAndKeys() successfully extracts model and keys from model instance')]
    #[Group('happy-path')]
    public function extract_model_and_keys_succeeds_with_model_instance(): void
    {
        // Arrange
        $user = User::query()->create();

        // Act
        $result = Helpers::extractModelAndKeys($user);

        // Assert
        $this->assertNotNull($result);
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertSame($user, $result[0]);
        $this->assertEquals([$user->getKey()], $result[1]);
    }

    #[Test()]
    #[TestDox('extractModelAndKeys() successfully extracts model and keys from Collection of models')]
    #[Group('happy-path')]
    public function extract_model_and_keys_succeeds_with_collection_of_models(): void
    {
        // Arrange
        $user1 = User::query()->create();
        $user2 = User::query()->create();
        $collection = new Collection([$user1, $user2]);

        // Act
        $result = Helpers::extractModelAndKeys($collection);

        // Assert
        $this->assertNotNull($result);
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertSame($user1, $result[0]);
        $this->assertInstanceOf(Collection::class, $result[1]);
        $this->assertEquals([$user1->getKey(), $user2->getKey()], $result[1]->all());
    }

    #[Test()]
    #[TestDox('isIndexedArray() returns true for numerically indexed array')]
    #[Group('happy-path')]
    public function is_indexed_array_returns_true_for_numeric_keys(): void
    {
        // Arrange
        $array = [0 => 'a', 1 => 'b', 2 => 'c'];

        // Act
        $result = Helpers::isIndexedArray($array);

        // Assert
        $this->assertTrue($result);
    }

    #[Test()]
    #[TestDox('isIndexedArray() returns true for non-sequential numeric keys')]
    #[Group('edge-case')]
    public function is_indexed_array_returns_true_for_non_sequential_numeric_keys(): void
    {
        // Arrange
        $array = [0 => 'a', 5 => 'b', 10 => 'c'];

        // Act
        $result = Helpers::isIndexedArray($array);

        // Assert
        $this->assertTrue($result);
    }

    #[Test()]
    #[TestDox('isIndexedArray() returns false for associative array with string keys')]
    #[Group('happy-path')]
    public function is_indexed_array_returns_false_for_string_keys(): void
    {
        // Arrange
        $array = ['name' => 'John', 'email' => 'john@example.com'];

        // Act
        $result = Helpers::isIndexedArray($array);

        // Assert
        $this->assertFalse($result);
    }
}
