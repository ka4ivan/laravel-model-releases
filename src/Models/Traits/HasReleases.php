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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

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
            $this->updateWithReleases([
                'archive_at' => Carbon::now(),
            ]);
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

            $this->updateRelations($model, $relationsData);

            return $model;
        });
    }

    private function updateRelations(Model $model, array $relationsData = []): void
    {
        foreach (config('model-releases.models.' . __CLASS__ . '.relations', []) as $relation) {
            foreach ($this->$relation ?? [] as $origin) {
                $origin->getPrereleaseOrOrigin(array_merge([
                    $this->{$relation}()->getForeignKeyName() => $model->id
                ], $relationsData[$relation] ?? []));
            }
        }
    }

    public function getPrereleaseOrOrigin(array $replicaData = []): Model
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

        $replica->prerelease_id = $this->id;
        $replica->save();

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

    public function changelog($release = null, array $fields = []): array
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
            $type = $entity->archive_at && $entity->origin ? 'deleted'
                : ($entity->origin ? 'updated' : 'created');

            $filteredEntity = $fields
                ? $entity->only($fields)
                : $entity;

            $changelog[$entity->release_id ?? 'prerelease'][$type] = $filteredEntity;
        }

        return $changelog;
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
