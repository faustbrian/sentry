<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Conductors;

use Cline\Warden\Conductors\Concerns\AssociatesAbilities;
use Illuminate\Database\Eloquent\Model;

/**
 * Grants abilities to authorities (users or roles).
 *
 * This conductor handles the process of assigning abilities to an authority entity,
 * which can be either a Model instance or a string identifier representing a role.
 * The abilities granted through this conductor are normal (non-forbidden) permissions.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class GivesAbilities
{
    use AssociatesAbilities;

    /**
     * Whether the associated abilities should be forbidden abilities.
     *
     * When true, this conductor operates in "forbid" mode, where abilities
     * are explicitly denied rather than granted. This flag is inherited by
     * the trait and controls the association behavior.
     *
     * @var bool
     */
    protected $forbidding = false;

    /**
     * The context model within which permissions are granted.
     *
     * When set, abilities are only valid within the specified context
     * (e.g., a Team, Organization, or other model instance).
     *
     * @var null|Model
     */
    protected $context;

    /**
     * Create a new abilities granter instance.
     *
     * @param null|Model|string $authority The entity receiving the abilities. Can be a Model
     *                                     instance (typically a User or Role), a string identifier
     *                                     representing a role name that will be resolved to a Role
     *                                     model, or null to be set later via trait methods.
     */
    public function __construct(
        protected $authority = null,
    ) {}

    /**
     * Set the context for context-aware permissions.
     *
     * Enables permissions that are only valid within a specific organizational
     * context (e.g., team, workspace, organization). Returns the conductor
     * instance for method chaining.
     *
     * ```php
     * Bouncer::allow($user)->within($team)->to('view', 'invoices');
     * ```
     *
     * @param  Model $context The context model instance (e.g., Team, Organization)
     * @return $this
     */
    public function within(Model $context): self
    {
        $this->context = $context;

        return $this;
    }
}
