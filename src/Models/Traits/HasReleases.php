<?php

declare(strict_types=1);

namespace Ka4ivan\ModelReleases\Models\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

trait HasReleases
{
    protected static function bootHasReleases()
    {
//        static::created(function ($model) {
//
//        });

        static::deleting(function (Model $model) {
            $model->handleDelete();
        });
    }

    /**
     * Модель яка ще не в релізі і є чернеткою моделі в релізі
     *
     * @return HasOne
     */
    public function prerelease(): HasOne
    {
        return $this->hasOne(self::class, 'prerelease_id');
    }

    /**
     * Модель яка в релізі і є оригіналом моделі не в релізі
     *
     * @return BelongsTo
     */
    public function original(): BelongsTo
    {
        return $this->belongsTo(self::class, 'prerelease_id','id');
    }

    public function scopeByReleased(Builder $query): Builder
    {
        return $query->whereNotNull('release_id');
    }

    public function canEditPrerelease(): bool
    {
        return $this->prerelease && $this->prerelease->release_id === null;
    }

    public function isReleased(): bool
    {
        return boolval($this->release_id);
    }

    public function isPrerelease(): bool
    {
        return !$this->release_id;
    }

    public function isArchive(): bool
    {
        return boolval($this->archive_at);
    }

    protected function handleDelete(): void
    {
        if ($this->release_id) {
            $this->archive();
            $this->prerelease?->archive();
        } else {
            $this->forceDelete();
        }
    }

    public function archive(): void
    {
        $this->update(['archive_at' => Carbon::now()]);
    }

    public function unarchive(): void
    {
        $this->update(['archive_at' => null]);
    }
}
