<?php

declare(strict_types=1);

namespace Ka4ivan\ModelReleases\Models\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

trait HasReleases
{
    /**
     * Release to which this model belongs
     *
     * @return BelongsTo
     */
    public function release(): BelongsTo
    {
        return $this->belongsTo(\ModelRelease::getReleaseModel());
    }

    /**
     * A model that is not yet in release and is a draft of a model in release
     *
     * @return HasOne
     */
    public function prerelease(): HasOne
    {
        return $this->hasOne(self::class, 'prerelease_id', 'id')->whereNull('release_id');
    }

    /**
     * A model that is already fully released
     *
     * @return HasOne
     */
    public function postrelease(): HasOne
    {
        return $this->hasOne(self::class, 'prerelease_id', 'id')->whereIn('release_id', \ModelRelease::getActiveReleasesIds());
    }

    /**
     * The model that is in the release and is the original of the model that is not in the release
     *
     * @return BelongsTo
     */
    public function origin(): BelongsTo
    {
        return $this->belongsTo(self::class, 'prerelease_id', 'id');
    }

    public function initializeHasReleases()
    {
        $this->mergeCasts([
            'release_data' => 'array'
        ]);

        if ($this->fillable) {
            $this->fillable[] = 'archive_at';
            $this->fillable[] = 'release_data';
            $this->fillable[] = 'release_id';
            $this->fillable[] = 'prerelease_id';
        }
    }

    public function scopeByReleased(Builder $query): Builder
    {
        return $query->whereIn('release_id', \ModelRelease::getActiveReleasesIds());
    }

    public function scopeByAdminReleased(Builder $query): Builder
    {
        return $query->whereIn('release_id', \ModelRelease::getActiveReleasesIds())->orWhereNull('release_id');
    }

    public function canEditPrerelease(): bool
    {
        return $this->prerelease && $this->prerelease->release_id === null;
    }

    public function isReleased(): bool
    {
        return boolval($this->release_id);
    }

    public function isNew(): bool
    {
        return (!$this->release_id && !$this->origin);
    }

    public function isPrerelease(bool $withNew = false): bool
    {
        if ($withNew) {
            return !$this->release_id;
        }

        return (!$this->release_id && $this->origin);
    }

    public function isPrereleaseOrNew(): bool
    {
        return $this->isPrerelease(true);
    }

    public function isArchive(): bool
    {
        return boolval($this->archive_at);
    }

    public function releaseProcess()
    {
//        If you need to implement something custom for the model.
    }

    public function archive(): void
    {
        $this->update(['archive_at' => Carbon::now()]);
    }

    public function unarchive(): void
    {
        $this->update(['archive_at' => null]);
    }

    public function deleteWithReleases(array $relationsData = []): Model
    {
        if ($this->release_id) {
            $this->updateWithReleases([
                'archive_at' => Carbon::now(),
                'is_delete' => true,
            ], $relationsData);
        } else {
            $this->forceDelete();
            $this->origin?->updateQuietly([
                'prerelease_id' => null,
            ]);

            foreach (config('model-releases.models.' . __CLASS__ . '.relations', []) as $relation) {
                $this->origin?->{$relation}()->update([
                    'prerelease_id' => null,
                ]);
            }
        }

        return $this;
    }

    public function updateWithReleases(array $data, array $relationsData = []): Model
    {
        return DB::transaction(function () use ($data, $relationsData) {
            $model = $this->getPrereleaseOrOrigin();

            $model->update($data);

            $this->updateRelations($model, $relationsData, $data);

            return $model;
        });
    }

    protected function updateRelations(Model $model, array $relationsData = [], array $data = []): void
    {
        foreach (config('model-releases.models.' . __CLASS__ . '.relations', []) as $relation) {
            foreach ($this->$relation ?? [] as $origin) {
                $origin->getPrereleaseOrOrigin(array_merge([
                    $this->{$relation}()->getForeignKeyName() => $model->id
                ], $relationsData[$relation] ?? []), $data);
            }
        }
    }

    public function getPrereleaseOrOrigin(array $replicaData = [], array $data = []): Model
    {
        $isDelete = (bool) \Arr::get($data, 'is_delete');

        if (is_null($this->release_id) && !$isDelete) {
            return $this;
        }

        $replica = $this->prerelease
            ?? $this->findByUniqueFields($replicaData)
            ?? $this->findByIfDelete($isDelete)
            ?? $this->replicate();

        $replica->release_id = null;
        $replica->setRelations([]);

        foreach ($replicaData as $key => $value) {
            $replica->{$key} = $value;
        }

        $replica->prerelease_id = $this->id;
        $replica->save();

        if ($replica->wasRecentlyCreated) {
            $this->releaseProcess($replica);
        }

        return $replica;
    }

    /**
     * @param $key
     * @param null $default
     * @return array|\ArrayAccess|mixed
     */
    public function getReleaseData($key, $default = null)
    {
        return Arr::get($this->release_data ?? [], $key, $default);
    }

    public function changelog($release = null, array $fields = [], bool $withActions = true): Collection
    {
        $changelog = [];

        $builder = self::query()
            ->withTrashed()
            ->whereJsonContains('release_data->source_id', $this->getReleaseData('source_id'))
            ->byAdminReleased()
            ->with([
                'release',
                'origin' => fn($q) => $q->withTrashed(),
            ])
            ->oldest();

        if ($release) {
            $builder->where('release_id', $release->id);
        }

        $entities = $builder->get();

        foreach ($entities as $entity) {
            $action = $entity->archive_at && $entity->origin ? 'deleted'
                : ($entity->origin ? 'updated' : 'created');

            if ($withActions) {
                $entity->action = $action;
            }

            $filteredEntity = $fields
                ? $entity->only($fields)
                : $entity;

            $changelog[] = $filteredEntity;
        }

        return collect($changelog);
    }

    public function getChangelogIds(): array
    {
        return self::query()
            ->withTrashed()
            ->whereJsonContains('release_data->source_id', $this->getReleaseData('source_id'))
            ->pluck('id')
            ->toArray();
    }

    protected function findByUniqueFields(array $fields = []): ?Model
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

    protected function findByIfDelete(bool $isDelete = true): ?Model
    {
        if (!$isDelete) {
            return null;
        }

        $model = self::query()
            ->whereNot('id', $this->id)
            ->whereNotNull('archive_at')
            ->first();

        return $model;
    }
}
