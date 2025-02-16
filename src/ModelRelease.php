<?php

declare(strict_types=1);

namespace Ka4ivan\ModelReleases;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

class ModelRelease
{
    private ?Model $release;
    private ?Model $prevRelease;

    public function runRelease(array $data = []): array
    {
        try {
            return DB::transaction(function () use ($data) {
                $this->release = $this->getReleaseModel()::create($data);

                foreach (array_keys(config('model-releases.models', [])) as $model) {
                    $model::query()
                        ->with(['origin'])
                        ->whereNull('release_id')
                        ->orWhere(fn($q) => $q->whereNotNull('archive_at')->whereNull('deleted_at'))
                        ->chunk(50, function ($entities) {
                            foreach ($entities as $entity) {
                                $this->doRelease($entity);
                            }
                        });
                }

                return [
                    'status' => 'success',
                    'message' => 'The release was successfully created!',
                ];
            });
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'message' => 'The release failed: ' . $e->getMessage(),
            ];
        }
    }

    private function doRelease(Model $model): void
    {
        /** @var Model $origin */
        $origin = $model->origin;
        $sourceId = $origin?->getReleaseData('source_id') ?? $model->id;

        if ($origin) {
            $origin->saveQuietly();
            $origin->delete();
        }

        $model->release_id = $this->release->id;
        $model->setAttribute('release_data->source_id', $sourceId);
        $model->saveQuietly();

        if ($model->archive_at) {
            $model->delete();
        }
    }

    public function rollbackRelease(): array
    {
        try {
            return DB::transaction(function () {
                $this->release = $this->getReleaseModel()::query()->latest('created_at')->first();
                $this->prevRelease = $this->getReleaseModel()::query()->latest('created_at')->skip(1)->first();

                if ((!$this->release) || ($this->prevRelease?->cleaned_at)) {
                    return [
                        'status' => 'warning',
                        'message' => 'No release available!',
                    ];
                }

                foreach (array_keys(config('model-releases.models', [])) as $model) {
                    $model::query()
                        ->whereNull('release_id')
                        ->forceDelete();

                    $model::query()
                        ->with([
                            'origin' => fn($q) => $q->withTrashed(),
                        ])
                        ->withTrashed()
                        ->where('release_id', $this->release->id)
                        ->chunk(50, function ($entities) {
                            foreach ($entities as $entity) {
                                $this->doRollback($entity);
                            }
                        });
                }

                $this->release->delete();

                return [
                    'status' => 'success',
                    'message' => 'The last release was rollbacked!',
                ];
            });
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'message' => 'Rollback failed: ' . $e->getMessage(),
            ];
        }
    }

    private function doRollback(Model $model): void
    {
        /** @var Model $origin */
        if ($origin = $model->origin) {
            $origin->restore();
        }

        if ($model->deleted_at) {
            $model->restore();
        } elseif ($model->archive_at) {
            $model->archive_at = null;
        }

        $model->release_id = null;
        $model->saveQuietly();
    }

    public function getReleaseModel(): string
    {
        return config('model-release.model', \Ka4ivan\ModelReleases\Models\Release::class);
    }
}
