<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\Models;

use Cline\Warden\Database\Concerns\Authorizable;
use Cline\Warden\Database\HasRolesAndAbilities;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Override;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class Organization extends Model
{
    use HasFactory;
    use Authorizable;
    use HasRolesAndAbilities;

    protected $guarded = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $keyType = config('warden.primary_key_type', 'id');

        if ($keyType === 'id') {
            $this->incrementing = true;
            $this->primaryKey = 'id';
            $this->keyType = 'int';
        } else {
            $this->incrementing = false;
            $this->primaryKey = $keyType;
            $this->keyType = 'string';
        }
    }

    #[Override()]
    protected static function boot(): void
    {
        parent::boot();

        self::creating(function ($model): void {
            $keyType = config('warden.primary_key_type', 'id');
            $keyName = $model->getKeyName();

            if ($keyType === 'ulid' && !$model->{$keyName}) {
                $model->{$keyName} = (string) Str::ulid();
            } elseif ($keyType === 'uuid' && !$model->{$keyName}) {
                $model->{$keyName} = (string) Str::uuid();
            }
        });
    }
}
