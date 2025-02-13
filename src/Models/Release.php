<?php

declare(strict_types=1);

namespace Ka4ivan\ModelReleases\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Release extends Model
{
    use HasFactory, HasUuids;

    protected array $guarded = [
        'id',
    ];
}
