<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use Cline\Warden\Clipboard\CachedClipboard;
use Cline\Warden\Contracts\ClipboardInterface;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\FileStore;
use Illuminate\Cache\TaggedCache;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Tests\Fixtures\Models\Account;
use Tests\Fixtures\Models\User;

use function exec;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

/**
 * @internal
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class CachedClipboardTest extends TestCase
{
    #[Test()]
    public function it_caches_abilities(): void
    {
        $warden = $this->bouncer($user = User::query()->create());

        $warden->allow($user)->to('ban-users');

        $this->assertEquals(['ban-users'], $this->getAbilities($user));

        $warden->allow($user)->to('create-users');

        $this->assertEquals(['ban-users'], $this->getAbilities($user));
    }

    #[Test()]
    public function it_caches_empty_abilities(): void
    {
        $user = User::query()->create();

        $this->assertInstanceOf(Collection::class, $this->clipboard()->getAbilities($user));
        $this->assertInstanceOf(Collection::class, $this->clipboard()->getAbilities($user));
    }

    #[Test()]
    public function it_caches_roles(): void
    {
        $warden = $this->bouncer($user = User::query()->create());

        $warden->assign('editor')->to($user);

        $this->assertTrue($warden->is($user)->an('editor'));

        $warden->assign('moderator')->to($user);

        $this->assertFalse($warden->is($user)->a('moderator'));
    }

    #[Test()]
    public function it_always_checks_roles_in_the_cache(): void
    {
        $warden = $this->bouncer($user = User::query()->create());
        $admin = $warden->role()->create(['name' => 'admin']);

        $warden->assign($admin)->to($user);

        $this->assertTrue($warden->is($user)->an('admin'));

        $this->db()->connection()->enableQueryLog();

        $this->assertTrue($warden->is($user)->an($admin->name));
        $this->assertTrue($warden->is($user)->an('admin'));
        $this->assertTrue($warden->is($user)->an($admin->name));

        $this->assertEmpty($this->db()->connection()->getQueryLog());

        $this->db()->connection()->disableQueryLog();
    }

    #[Test()]
    public function it_can_refresh_the_cache(): void
    {
        new ArrayStore();

        $warden = $this->bouncer($user = User::query()->create());

        $warden->allow($user)->to('create-posts');
        $warden->assign('editor')->to($user);
        $warden->allow('editor')->to('delete-posts');

        $this->assertEquals(['create-posts', 'delete-posts'], $this->getAbilities($user));

        $warden->disallow('editor')->to('delete-posts');
        $warden->allow('editor')->to('edit-posts');

        $this->assertEquals(['create-posts', 'delete-posts'], $this->getAbilities($user));

        $warden->refresh();

        $this->assertEquals(['create-posts', 'edit-posts'], $this->getAbilities($user));
    }

    #[Test()]
    public function it_can_refresh_the_cache_only_for_one_user(): void
    {
        $user1 = User::query()->create();
        $user2 = User::query()->create();

        $warden = $this->bouncer($user = User::query()->create());

        $warden->allow('admin')->to('ban-users');
        $warden->assign('admin')->to($user1);
        $warden->assign('admin')->to($user2);

        $this->assertEquals(['ban-users'], $this->getAbilities($user1));
        $this->assertEquals(['ban-users'], $this->getAbilities($user2));

        $warden->disallow('admin')->to('ban-users');
        $warden->refreshFor($user1);

        $this->assertEquals([], $this->getAbilities($user1));
        $this->assertEquals(['ban-users'], $this->getAbilities($user2));
    }

    #[Test()]
    public function it_returns_cache_instance(): void
    {
        // Arrange
        $cache = new ArrayStore();
        $clipboard = new CachedClipboard($cache);

        // Act
        $result = $clipboard->getCache();

        // Assert
        $this->assertInstanceOf(TaggedCache::class, $result);
    }

    #[Test()]
    public function it_compiles_model_ability_identifiers_for_wildcard(): void
    {
        // Arrange
        $cache = new ArrayStore();
        new CachedClipboard($cache);
        $user = User::query()->create();
        $warden = $this->bouncer($user);

        // Act
        $warden->allow($user)->to('delete', '*');
        $result = $warden->can('delete', '*');

        // Assert
        $this->assertTrue($result);
    }

    #[Test()]
    public function it_compiles_model_ability_identifiers_for_class_string(): void
    {
        // Arrange
        $cache = new ArrayStore();
        new CachedClipboard($cache);
        $user = User::query()->create();
        $warden = $this->bouncer($user);

        // Act
        $warden->allow($user)->to('view', User::class);
        $result = $warden->can('view', User::class);

        // Assert
        $this->assertTrue($result);
    }

    #[Test()]
    public function it_compiles_model_ability_identifiers_for_model_instance(): void
    {
        // Arrange
        $cache = new ArrayStore();
        new CachedClipboard($cache);
        $user = User::query()->create();
        $targetUser = User::query()->create(['name' => 'Target']);
        $warden = $this->bouncer($user);

        // Act
        $warden->allow($user)->to('edit', $targetUser);
        $result = $warden->can('edit', $targetUser);

        // Assert
        $this->assertTrue($result);
    }

    #[Test()]
    public function it_refreshes_all_iteratively_without_tagged_cache(): void
    {
        // Arrange - FileStore doesn't support tags, so refresh() will use refreshAllIteratively()
        $tempDir = sys_get_temp_dir().'/warden-cache-test-'.uniqid();
        mkdir($tempDir, 0o777, true);
        $cache = new FileStore(
            new Filesystem(),
            $tempDir,
        );
        $clipboard = new CachedClipboard($cache);

        $user1 = User::query()->create();
        $user2 = User::query()->create();
        $warden = $this->bouncer($user1);
        $warden->setClipboard($clipboard);

        // Create roles to ensure both users and roles are iterated (lines 400-405)
        $warden->role()->create(['name' => 'editor']);
        $warden->role()->create(['name' => 'admin']);

        $warden->allow($user1)->to('create-posts');
        $warden->allow($user2)->to('delete-posts');
        $warden->assign('editor')->to($user1);

        // Populate cache
        $this->assertEquals(['create-posts'], $this->getAbilities($user1));
        $this->assertEquals(['delete-posts'], $this->getAbilities($user2));

        // Change permissions
        $warden->disallow($user1)->to('create-posts');
        $warden->allow($user1)->to('edit-posts');

        // Act - this should call refreshAllIteratively() which iterates through all users and roles
        $warden->refresh();

        // Assert
        $this->assertEquals(['edit-posts'], $this->getAbilities($user1));

        // Cleanup
        exec('rm -rf '.$tempDir);
    }

    #[Test()]
    public function it_finds_matching_ability_with_ownership(): void
    {
        // Arrange
        $owner = User::query()->create(['name' => 'Owner']);
        $warden = $this->bouncer($owner);

        $post = new Account(['name' => 'Post']);
        $post->actor_id = $owner->id;
        $post->actor_type = $owner->getMorphClass();
        $post->save();

        // Act
        $warden->allow($owner)->toOwn(Account::class);
        $result = $warden->can('edit', $post);

        // Assert
        $this->assertTrue($result);
    }

    #[Test()]
    public function it_returns_false_when_forbidden_ability_matches(): void
    {
        // Arrange
        $user = User::query()->create();
        $warden = $this->bouncer($user);

        // Act
        $warden->allow($user)->to('edit-posts');
        $warden->forbid($user)->to('edit-posts');

        // Assert
        $this->assertFalse($warden->can('edit-posts'));
    }

    #[Test()]
    public function it_throws_exception_for_invalid_role_type(): void
    {
        // Arrange
        $user = User::query()->create();
        $clipboard = $this->clipboard();

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid model identifier');
        $clipboard->checkRole($user, [new stdClass()], 'or');
    }

    /**
     * Make a new clipboard with the container.
     */
    #[Override()]
    protected static function makeClipboard(): ClipboardInterface
    {
        return new CachedClipboard(
            new ArrayStore(),
        );
    }

    /**
     * Get the name of all of the user's abilities.
     *
     * @return array
     */
    private function getAbilities(Model $user)
    {
        return $user->getAbilities($user)->pluck('name')->sort()->values()->all();
    }
}
