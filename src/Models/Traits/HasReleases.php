<?php

declare(strict_types=1);

namespace Ka4ivan\ModelReleases\Models\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

trait HasReleases
{
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
    public function origin(): BelongsTo
    {
        return $this->belongsTo(self::class, 'id', 'prerelease_id');
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

    public function archive(): void
    {
        $this->update(['archive_at' => Carbon::now()]);
    }

    public function unarchive(): void
    {
        $this->update(['archive_at' => null]);
    }

    public function deleteWithReleases(): Model
    {
        if ($this->release_id) {
            $this->archive();
            $this->prerelease?->archive();
        } else {
            $this->forceDelete();
            $this->origin?->updateQuietly([
                'prerelease_id' => null,
            ]);

            foreach (config('model-releases.models.' . __CLASS__ . '.relations', []) as $relation) {
                $this->origin->{$relation}()->update([
                    'prerelease_id' => null,
                ]);
            }
        }

        return $this;
    }

    public function updateWithReleases(array $data, array $relationsData = []): Model
    {
        return DB::transaction(function () use ($data, $relationsData) {
            $model = $this->getDraftOrOriginal();

            $model->update($data);

            $this->updateRelations($model, $relationsData);

            return $model;
        });
    }

    private function updateRelations(Model $model, array $relationsData = []): void
    {
        foreach (config('model-releases.models.' . __CLASS__ . '.relations', []) as $relation) {
            foreach ($this->$relation ?? [] as $original) {
                $original->getDraftOrOriginal(array_merge([
                    $this->{$relation}()->getForeignKeyName() => $model->id
                ], $relationsData[$relation] ?? []));
            }
        }
    }

    public function getDraftOrOriginal(array $replicaData = []): Model
    {
        if (!$this->release_id) {
            return $this;
        }

        $replica = $this->prerelease ?? $this->findByUniqueFields($replicaData) ?? $this->replicate();
        $replica->release_id = null;
        $replica->setRelations([]);

        foreach ($replicaData as $key => $value) {
            $replica->{$key} = $value;
        }

        $replica->save();

        $this->prerelease_id = $replica->id;
        $this->save();

        return $replica;
    }

    private function findByUniqueFields(array $fields = []): ?Model
    {
        $table = $this->getTable();

        $uniqueIndexes = collect(Schema::getIndexes($table))
            ->where('unique', true)
            ->where('primary', false)
            ->pluck('columns');

        foreach ($uniqueIndexes as $columns) {
            $query = self::query()
                ->where(array_merge(array_intersect_key($this->attributes, array_flip($columns)), $fields));

            if ($model = $query->first()) {
                return $model;
            }
        }

        return null;
    }
}
