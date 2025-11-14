<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Conductors\Lazy;

use Cline\Warden\Conductors\ForbidsAbilities;
use Cline\Warden\Conductors\GivesAbilities;
use Cline\Warden\Conductors\RemovesAbilities;
use Cline\Warden\Conductors\UnforbidsAbilities;
use Illuminate\Database\Eloquent\Model;

/**
 * Lazy evaluation wrapper for ability conductor operations.
 *
 * This class defers the actual granting of abilities until object destruction,
 * allowing for a fluent API where additional constraints can be applied before
 * the final operation executes. This pattern enables the "everything()" method
 * to be called after construction but before the abilities are actually assigned.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ConductsAbilities
{
    /**
     * Determines whether the given abilities should be granted on all models.
     *
     * When true, abilities apply to all entity types (represented by '*').
     * When false, abilities are scoped to specific entity types.
     */
    private bool $everything = false;

    /**
     * The extra attributes for the abilities.
     *
     * Additional data to attach to the ability assignment, such as constraints,
     * expiration dates, or custom metadata stored in the permissions pivot table.
     *
     * @var array<string, mixed>
     */
    private array $attributes = [];

    /**
     * Create a new lazy ability conductor instance.
     *
     * @param ForbidsAbilities|GivesAbilities|RemovesAbilities|UnforbidsAbilities $conductor the underlying conductor
     *                                                                                       that will execute the
     *                                                                                       actual ability assignment
     *                                                                                       when this object destructs
     * @param array<mixed>|int|Model|string                                       $abilities The abilities to be granted. Can be a string, array of strings,
     *                                                                                       or collection of ability identifiers/models to associate with
     *                                                                                       the authority.
     */
    public function __construct(
        private readonly ForbidsAbilities|GivesAbilities|RemovesAbilities|UnforbidsAbilities $conductor,
        private readonly array|Model|int|string $abilities,
    ) {}

    /**
     * Execute the deferred ability assignment when object is destroyed.
     *
     * This destructor ensures that even if the caller forgets to explicitly
     * execute the conductor, the abilities will still be granted when the
     * object goes out of scope. This enables seamless lazy evaluation.
     */
    public function __destruct()
    {
        $this->conductor->to(
            $this->abilities,
            $this->everything ? '*' : null,
            $this->attributes,
        );
    }

    /**
     * Apply the abilities to all entity types.
     *
     * Marks the abilities as global, applying to all models in the system
     * rather than being scoped to specific entity types. This is useful for
     * administrative permissions that should work across all models.
     *
     * @param array<string, mixed> $attributes additional pivot table attributes to store
     *                                         with the global ability assignment, such as
     *                                         constraints or metadata
     */
    public function everything(array $attributes = []): void
    {
        $this->everything = true;

        $this->attributes = $attributes;
    }
}
