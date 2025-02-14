<?php

declare(strict_types=1);

namespace Ka4ivan\ModelReleases;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ModelRelease
{
    private ?Model $release;
    private ?Model $prevRelease;

    public function runRelease(): array
    {
        $this->release = $this->getReleaseModel()::create();

        foreach (config('model-releases.models', []) as $model) {
            $model::query()
                ->with(['original'])
                ->whereNull('release_id')
                ->orWhere(fn ($q) => $q->whereNotNull('archive_at')->whereNull('deleted_at'))
                ->chunk(50, function ($entities) {
                    foreach ($entities as $entity) {
                        $this->doRelease($entity);
                    }
                });
        }

        return [
            'status' => 'success',
            'message' => 'The release was successful created!',
        ];
    }

    private function doRelease(Model $model): void
    {
        /** @var Model $original */
        if ($original = $model->original) {
            $original->release_id = $this->release->id;
            $original->delete();
            $original->save();
        }

        $model->release_id = $this->release->id;

        if ($model->archive_at) {
            $model->delete();
        }

        $model->save();
    }

    public function rollbackRelease(): array
    {
        $this->release = $this->getReleaseModel()::query()->latest('created_at')->first();
        $this->prevRelease = $this->getReleaseModel()::query()->latest('created_at')->skip(1)->first();

        if (!$this->release) {
            return [
                'status' => 'warning',
                'message' => 'No release available!',
            ];
        }

        foreach (config('model-releases.models', []) as $model) {
            $model::query()
                ->with([
                    'original' => fn($q) => $q->withTrashed()->where('release_id', $this->release?->id),
                ])
                ->withTrashed()
                ->where('release_id', $this->release->id)
                ->chunk(50, function ($entities) {
                    foreach ($entities as $entity) {
                        $this->doRelease($entity);
                    }
                });
        }

        return [
            'status' => 'success',
            'message' => 'The last release was rollbacked!',
        ];
    }

    private function doRollback(Model $model): void
    {
        /** @var Model $original */
        if ($original = $model->original) {
            $original->release_id = $this->prevRelease->id;
            $original->restore();
            $original->save();
        }

        if ($model->deleted_at) {
            $model->restore();
        } else {
            $model->release_id = null;
        }

        $model->save();
    }

    public function updateWithReleases(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            $model = $this->createDraftIfNeeded();

            $model->update($data);

            $this->updateRelations($model);

            return $model;
        });
    }

    private function updateRelations(Model $model): void
    {
        foreach ($this->relationsToReplicate() as $relation) {
            foreach ($this->$relation as $original) {
                $replica = $original->replicate();
                $replica->setAttribute($this->getForeignKey(), $model->id);
                $replica->release_id = null;
                $replica->prerelease_id = $original->id;
                $replica->save();
            }
        }
    }

    public function getDraftOrOriginal(): Model
    {
        if (!$this->release_id) {
            return $this;
        }

        $draft = $this->prerelease ?? $this->replicate();
        $draft->prerelease_id = $this->id;
        $draft->release_id = null;
        $draft->setRelations([]);
        $draft->saveQuietly();

        return $draft;
    }

    public function getReleaseModel(): string
    {
        return config('model-release.model', \Ka4ivan\ModelReleases\Models\Release::class);
    }
}
