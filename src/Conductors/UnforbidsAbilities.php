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
 * Removes forbidden abilities from authorities.
 *
 * This conductor handles the detachment of explicitly denied abilities from
 * an authority entity. It specifically targets abilities where the 'forbidden'
 * flag is true, leaving granted abilities intact. This allows removal of
 * explicit denials without affecting granted permissions.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class UnforbidsAbilities
{
    use DisassociatesAbilities;

    /**
     * The constraints to use for the detach abilities query.
     *
     * This constraint ensures only forbidden abilities (forbidden = true) are
     * removed, preserving any granted abilities associated with the authority.
     * This separation allows independent management of denials and grants.
     *
     * @var array<string, bool>
     */
    protected $constraints = ['forbidden' => true];

    /**
     * Create a new forbidden abilities remover instance.
     *
     * @param null|Model|string $authority The entity from which to remove forbidden abilities.
     *                                     Can be a Model instance, a string identifier representing
     *                                     a role name, or null to be set later via trait methods.
     */
    public function __construct(
        protected $authority = null,
    ) {}
}
