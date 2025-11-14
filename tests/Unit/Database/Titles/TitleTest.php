<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Database\Titles;

use Cline\Warden\Database\Ability;
use Cline\Warden\Database\Role;
use Cline\Warden\Database\Titles\AbilityTitle;
use Cline\Warden\Database\Titles\RoleTitle;
use Cline\Warden\Database\Titles\Title;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

use function mb_strtolower;
use function str_repeat;

/**
 * Comprehensive test suite for the Title abstract class.
 *
 * Tests the abstract Title base class functionality including humanization,
 * factory methods, and string conversion. Tests are executed through concrete
 * implementations (RoleTitle and AbilityTitle) since Title is abstract.
 *
 * @internal
 *
 * @author Brian Faust <brian@cline.sh>
 */
#[Small()]
final class TitleTest extends TestCase
{
    // =========================================================================
    // HAPPY PATH TESTS
    // =========================================================================

    /**
     * Test from() factory method creates RoleTitle instance.
     */
    #[Test()]
    #[TestDox('Creates RoleTitle instance from Role model')]
    #[Group('happy-path')]
    public function creates_role_title_instance_from_role_model(): void
    {
        // Arrange
        $role = Role::query()->create(['name' => 'admin']);

        // Act
        $title = RoleTitle::from($role);

        // Assert
        $this->assertInstanceOf(RoleTitle::class, $title);
        $this->assertInstanceOf(Title::class, $title);
    }

    /**
     * Test from() factory method creates AbilityTitle instance.
     */
    #[Test()]
    #[TestDox('Creates AbilityTitle instance from Ability model')]
    #[Group('happy-path')]
    public function creates_ability_title_instance_from_ability_model(): void
    {
        // Arrange
        $ability = Ability::query()->create(['name' => 'edit-posts']);

        // Act
        $title = AbilityTitle::from($ability);

        // Assert
        $this->assertInstanceOf(AbilityTitle::class, $title);
        $this->assertInstanceOf(Title::class, $title);
    }

    /**
     * Test toString() returns the generated title.
     */
    #[Test()]
    #[TestDox('Returns generated title string from toString()')]
    #[Group('happy-path')]
    public function returns_generated_title_string_from_to_string(): void
    {
        // Arrange
        $role = Role::query()->create(['name' => 'super_admin']);

        // Act
        $title = RoleTitle::from($role);
        $result = $title->toString();

        // Assert
        $this->assertIsString($result);
        $this->assertSame('Super admin', $result);
    }

    /**
     * Test humanize() capitalizes first letter.
     */
    #[Test()]
    #[TestDox('Capitalizes first letter of simple string')]
    #[Group('happy-path')]
    public function capitalizes_first_letter_of_simple_string(): void
    {
        // Arrange
        $role = Role::query()->create(['name' => 'admin']);

        // Act
        $title = RoleTitle::from($role);

        // Assert
        $this->assertSame('Admin', $title->toString());
    }

    /**
     * Test humanize() converts underscores to spaces.
     */
    #[Test()]
    #[TestDox('Converts underscores to spaces in role name')]
    #[Group('happy-path')]
    public function converts_underscores_to_spaces_in_role_name(): void
    {
        // Arrange
        $role = Role::query()->create(['name' => 'site_admin']);

        // Act
        $title = RoleTitle::from($role);

        // Assert
        $this->assertSame('Site admin', $title->toString());
    }

    /**
     * Test humanize() converts dashes to spaces.
     */
    #[Test()]
    #[TestDox('Converts dashes to spaces in role name')]
    #[Group('happy-path')]
    public function converts_dashes_to_spaces_in_role_name(): void
    {
        // Arrange
        $role = Role::query()->create(['name' => 'site-admin']);

        // Act
        $title = RoleTitle::from($role);

        // Assert
        $this->assertSame('Site admin', $title->toString());
    }

    /**
     * Test humanize() preserves spaces.
     */
    #[Test()]
    #[TestDox('Preserves spaces in original string')]
    #[Group('happy-path')]
    public function preserves_spaces_in_original_string(): void
    {
        // Arrange
        $role = Role::query()->create(['name' => 'site admin']);

        // Act
        $title = RoleTitle::from($role);

        // Assert
        $this->assertSame('Site admin', $title->toString());
    }

    /**
     * Test humanize() converts camelCase to spaces.
     */
    #[Test()]
    #[TestDox('Converts camelCase to spaced words')]
    #[Group('happy-path')]
    public function converts_camel_case_to_spaced_words(): void
    {
        // Arrange
        $role = Role::query()->create(['name' => 'siteAdmin']);

        // Act
        $title = RoleTitle::from($role);

        // Assert
        $this->assertSame('Site admin', $title->toString());
    }

    /**
     * Test humanize() converts StudlyCase to spaces.
     */
    #[Test()]
    #[TestDox('Converts StudlyCase to spaced words')]
    #[Group('happy-path')]
    public function converts_studly_case_to_spaced_words(): void
    {
        // Arrange
        $role = Role::query()->create(['name' => 'SiteAdmin']);

        // Act
        $title = RoleTitle::from($role);

        // Assert
        $this->assertSame('Site admin', $title->toString());
    }

    /**
     * Test humanize() adds space before hash symbol.
     */
    #[Test()]
    #[TestDox('Adds space before hash symbol in string')]
    #[Group('happy-path')]
    public function adds_space_before_hash_symbol_in_string(): void
    {
        // Arrange
        $role = Role::query()->create(['name' => 'admin#1']);

        // Act
        $title = RoleTitle::from($role);

        // Assert
        $this->assertStringContainsString(' #', $title->toString());
    }

    // =========================================================================
    // SAD PATH TESTS
    // =========================================================================

    /**
     * Test humanize() handles empty string gracefully.
     */
    #[Test()]
    #[TestDox('Handles empty string input gracefully')]
    #[Group('sad-path')]
    public function handles_empty_string_input_gracefully(): void
    {
        // Arrange
        $role = Role::query()->create(['name' => '']);

        // Act
        $title = RoleTitle::from($role);

        // Assert
        $this->assertIsString($title->toString());
        $this->assertSame('', $title->toString());
    }

    /**
     * Test humanize() handles string with only special characters.
     */
    #[Test()]
    #[TestDox('Handles string with only special characters')]
    #[Group('sad-path')]
    public function handles_string_with_only_special_characters(): void
    {
        // Arrange
        $role = Role::query()->create(['name' => '___']);

        // Act
        $title = RoleTitle::from($role);

        // Assert
        $this->assertIsString($title->toString());
        // Three underscores become spaces, trimmed by snake_case conversion
    }

    // =========================================================================
    // EDGE CASE TESTS
    // =========================================================================

    /**
     * Test humanize() handles multiple consecutive underscores.
     */
    #[Test()]
    #[TestDox('Collapses multiple consecutive underscores')]
    #[Group('edge-case')]
    public function collapses_multiple_consecutive_underscores(): void
    {
        // Arrange
        $role = Role::query()->create(['name' => 'super___admin']);

        // Act
        $title = RoleTitle::from($role);

        // Assert
        $this->assertSame('Super admin', $title->toString());
        $this->assertStringNotContainsString('  ', $title->toString());
    }

    /**
     * Test humanize() handles multiple consecutive dashes.
     */
    #[Test()]
    #[TestDox('Collapses multiple consecutive dashes')]
    #[Group('edge-case')]
    public function collapses_multiple_consecutive_dashes(): void
    {
        // Arrange
        $role = Role::query()->create(['name' => 'super---admin']);

        // Act
        $title = RoleTitle::from($role);

        // Assert
        $this->assertSame('Super admin', $title->toString());
        $this->assertStringNotContainsString('  ', $title->toString());
    }

    /**
     * Test humanize() handles mixed separators.
     */
    #[Test()]
    #[TestDox('Handles mixed underscores and dashes')]
    #[Group('edge-case')]
    public function handles_mixed_underscores_and_dashes(): void
    {
        // Arrange
        $role = Role::query()->create(['name' => 'site_admin-user']);

        // Act
        $title = RoleTitle::from($role);

        // Assert
        $this->assertSame('Site admin user', $title->toString());
    }

    /**
     * Test humanize() handles hash in middle of string.
     */
    #[Test()]
    #[TestDox('Adds space before hash in middle of string')]
    #[Group('edge-case')]
    public function adds_space_before_hash_in_middle_of_string(): void
    {
        // Arrange
        $role = Role::query()->create(['name' => 'user#admin']);

        // Act
        $title = RoleTitle::from($role);

        // Assert
        $this->assertStringContainsString(' #', $title->toString());
    }

    /**
     * Test humanize() handles hash at start of string.
     */
    #[Test()]
    #[TestDox('Handles hash at start of string')]
    #[Group('edge-case')]
    public function handles_hash_at_start_of_string(): void
    {
        // Arrange
        $role = Role::query()->create(['name' => '#admin']);

        // Act
        $title = RoleTitle::from($role);

        // Assert
        $this->assertIsString($title->toString());
        // Hash at start shouldn't get space before it
        $this->assertStringStartsWith('#', $title->toString());
    }

    /**
     * Test humanize() handles multiple hash symbols.
     */
    #[Test()]
    #[TestDox('Handles multiple hash symbols correctly')]
    #[Group('edge-case')]
    public function handles_multiple_hash_symbols_correctly(): void
    {
        // Arrange
        $role = Role::query()->create(['name' => 'admin#1#2']);

        // Act
        $title = RoleTitle::from($role);

        // Assert
        $this->assertIsString($title->toString());
        $this->assertStringContainsString(' #', $title->toString());
    }

    /**
     * Test humanize() handles numeric strings.
     */
    #[Test()]
    #[TestDox('Handles numeric string values')]
    #[Group('edge-case')]
    public function handles_numeric_string_values(): void
    {
        // Arrange
        $role = Role::query()->create(['name' => '123']);

        // Act
        $title = RoleTitle::from($role);

        // Assert
        $this->assertEquals('123', $title->toString());
    }

    /**
     * Test humanize() handles alphanumeric combinations.
     */
    #[Test()]
    #[TestDox('Handles alphanumeric combinations')]
    #[Group('edge-case')]
    public function handles_alphanumeric_combinations(): void
    {
        // Arrange
        $role = Role::query()->create(['name' => 'admin123user']);

        // Act
        $title = RoleTitle::from($role);

        // Assert
        $this->assertIsString($title->toString());
        $this->assertStringContainsString('admin', mb_strtolower($title->toString()));
        $this->assertStringContainsString('user', mb_strtolower($title->toString()));
    }

    /**
     * Test humanize() handles Unicode characters.
     */
    #[Test()]
    #[TestDox('Handles Unicode characters properly')]
    #[Group('edge-case')]
    public function handles_unicode_characters_properly(): void
    {
        // Arrange
        $role = Role::query()->create(['name' => 'café_admin']);

        // Act
        $title = RoleTitle::from($role);

        // Assert
        $this->assertStringContainsString('café', mb_strtolower($title->toString()));
        $this->assertStringContainsString('admin', mb_strtolower($title->toString()));
    }

    /**
     * Test humanize() handles single character.
     */
    #[Test()]
    #[TestDox('Handles single character input')]
    #[Group('edge-case')]
    public function handles_single_character_input(): void
    {
        // Arrange
        $role = Role::query()->create(['name' => 'a']);

        // Act
        $title = RoleTitle::from($role);

        // Assert
        $this->assertSame('A', $title->toString());
    }

    /**
     * Test humanize() handles all uppercase strings.
     */
    #[Test()]
    #[TestDox('Handles all uppercase strings with spacing')]
    #[Group('edge-case')]
    public function handles_all_uppercase_strings_with_spacing(): void
    {
        // Arrange
        $role = Role::query()->create(['name' => 'ADMIN']);

        // Act
        $title = RoleTitle::from($role);

        // Assert
        // ADMIN becomes a_d_m_i_n (snake_case), then A d m i n (spaces+ucfirst)
        $this->assertSame('A d m i n', $title->toString());
    }

    /**
     * Test humanize() with various string formats using data provider.
     */
    #[Test()]
    #[TestDox('Humanizes various string formats correctly')]
    #[DataProvider('provideHumanizes_various_string_formats_correctlyCases')]
    #[Group('edge-case')]
    public function humanizes_various_string_formats_correctly(string $input, string $expected): void
    {
        // Arrange
        $role = Role::query()->create(['name' => $input]);

        // Act
        $title = RoleTitle::from($role);

        // Assert
        $this->assertSame($expected, $title->toString());
    }

    /**
     * Data provider for various string format humanization tests.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function provideHumanizes_various_string_formats_correctlyCases(): iterable
    {
        yield 'simple lowercase' => ['admin', 'Admin'];

        yield 'with underscore' => ['super_admin', 'Super admin'];

        yield 'with dash' => ['super-admin', 'Super admin'];

        yield 'with space' => ['super admin', 'Super admin'];

        yield 'camelCase' => ['superAdmin', 'Super admin'];

        yield 'StudlyCase' => ['SuperAdmin', 'Super admin'];

        yield 'multiple underscores' => ['site_admin_user', 'Site admin user'];

        yield 'multiple dashes' => ['site-admin-user', 'Site admin user'];

        yield 'mixed separators' => ['site_admin-user', 'Site admin user'];

        yield 'with number' => ['admin2', 'Admin2'];

        yield 'trailing underscore' => ['admin_', 'Admin '];

        yield 'leading underscore' => ['_admin', ' admin'];

        yield 'ALL_CAPS_UNDERSCORE' => ['ALL_CAPS', 'A l l c a p s'];
    }

    /**
     * Test from() returns static type for concrete implementations.
     */
    #[Test()]
    #[TestDox('Factory method returns correct static type')]
    #[Group('edge-case')]
    public function factory_method_returns_correct_static_type(): void
    {
        // Arrange
        $role = Role::query()->create(['name' => 'admin']);
        $ability = Ability::query()->create(['name' => 'edit']);

        // Act
        $roleTitle = RoleTitle::from($role);
        $abilityTitle = AbilityTitle::from($ability);

        // Assert
        $this->assertInstanceOf(RoleTitle::class, $roleTitle);
        $this->assertInstanceOf(AbilityTitle::class, $abilityTitle);
        $this->assertNotInstanceOf(AbilityTitle::class, $roleTitle);
        $this->assertNotInstanceOf(RoleTitle::class, $abilityTitle);
    }

    /**
     * Test toString() with complex AbilityTitle scenario.
     */
    #[Test()]
    #[TestDox('Handles complex AbilityTitle generation')]
    #[Group('happy-path')]
    public function handles_complex_ability_title_generation(): void
    {
        // Arrange
        $ability = Ability::query()->create(['name' => 'ban-users']);

        // Act
        $title = AbilityTitle::from($ability);

        // Assert
        $this->assertIsString($title->toString());
        $this->assertSame('Ban users', $title->toString());
    }

    /**
     * Test humanize() handles very long strings.
     */
    #[Test()]
    #[TestDox('Handles very long input strings')]
    #[Group('edge-case')]
    public function handles_very_long_input_strings(): void
    {
        // Arrange
        $longName = str_repeat('super_', 50).'admin';
        $role = Role::query()->create(['name' => $longName]);

        // Act
        $title = RoleTitle::from($role);

        // Assert
        $this->assertIsString($title->toString());
        $this->assertStringStartsWith('Super ', $title->toString());
        $this->assertStringEndsWith(' admin', $title->toString());
    }
}
