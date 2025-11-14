<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Conductors;

use Cline\Warden\Conductors\Concerns\DisassociatesAbilities;
use Illuminate\Database\Eloquent\Model;

/**
 * Removes granted abilities from authorities.
 *
 * This conductor handles the detachment of normal (non-forbidden) abilities
 * from an authority entity. It specifically targets abilities where the
 * 'forbidden' flag is false, leaving forbidden abilities intact.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class RemovesAbilities
{
    use DisassociatesAbilities;

    /**
     * The constraints to use for the detach abilities query.
     *
     * This constraint ensures only granted abilities (forbidden = false) are
     * removed, preserving any explicitly forbidden abilities associated with
     * the authority. This separation allows independent management of grants
     * and denials.
     *
     * @var array<string, bool>
     */
    protected $constraints = ['forbidden' => false];

    /**
     * Create a new abilities remover instance.
     *
     * @param null|Model|string $authority The entity from which to remove abilities.
     *                                     Can be a Model instance, a string identifier
     *                                     representing a role name, or null to be set later
     *                                     via trait methods.
     */
    public function __construct(
        protected $authority = null,
    ) {}
}
