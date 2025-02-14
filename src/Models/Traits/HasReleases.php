<?php

declare(strict_types=1);

namespace Ka4ivan\ModelReleases\Models\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

trait HasReleases
{
    protected static function bootHasReleases()
    {
        static::deleted(function (Model $model) {
            $model->handleDelete();
        });
    }

    /**
     * Реліз до якого відноситься дана модель
     *
     * @return BelongsTo
     */
    public function release(): BelongsTo
    {
        return $this->BelongsTo(\ModelRelease::getReleaseModel());
    }

    /**
     * Модель яка ще не в релізі і є чернеткою моделі в релізі
     *
     * @return HasOne
     */
    public function prerelease(): HasOne
    {
        return $this->hasOne(self::class, 'id', 'prerelease_id');
    }

    /**
     * Модель яка в релізі і є оригіналом моделі не в релізі
     *
     * @return BelongsTo
     */
    public function original(): BelongsTo
    {
        return $this->belongsTo(self::class, 'prerelease_id', 'id');
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

            $this->restore();
            $this->prerelease?->restore();
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

    public function updateWithReleases(array $data, array $relationsToReplicate = []): Model
    {
        return DB::transaction(function () use ($data, $relationsToReplicate) {
            $model = $this->getDraftOrOriginal();

            $model->update($data);

            $this->updateRelations($model, $relationsToReplicate);

            return $model;
        });
    }

    private function updateRelations(Model $model, array $relationsToReplicate = []): void
    {
        // TODO Доробити збереження якщо це інший зв'язок (поліморфний - model_id, звичайний - post_id)
        foreach ($relationsToReplicate as $relation) {
            foreach ($this->$relation as $original) {
                $replica = $original->replicate();
                $replica->setAttribute($this->getForeignKey(), $model->id);
                $replica->release_id = null;
                $replica->saveQuietly();

                $original->prerelease_id = $replica->id;
                $replica->saveQuietly();
            }
        }
    }

    public function getDraftOrOriginal(): Model
    {
        if (!$this->release_id) {
            return $this;
        }

        $replica = $this->prerelease ?? $this->replicate();
        $replica->release_id = null;
        $replica->setRelations([]);
        $replica->saveQuietly();

        $this->prerelease_id = $replica->id;
        $this->saveQuietly();

        return $replica;
    }
}
