<?php

declare(strict_types=1);

namespace Ka4ivan\ModelReleases;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

class ModelRelease
{
    private ?Model $newRelease;
    private ?Model $prevRelease;

    protected ?Model $release = null;

    public function getActiveRelease(): ?Model
    {
        return $this->release ??= $this->getReleaseModel()::query()
            ->where('is_active', true)
            ->first()
            ?: $this->getReleaseModel()::query()->latest()->first();
    }

    public function getActiveReleasesIds(): array
    {
        return $this->getActiveRelease()?->getExtra('releases', []) ?? [];
    }

    public function runRelease(array $data = []): array
    {
        try {
            return DB::transaction(function () use ($data) {
                $release = $this->getActiveRelease();

                $this->newRelease = $this->getReleaseModel()::create(array_merge($data, [
                    'is_active' => true,
                    'parent_id' => $release?->id,
                ]));

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

                $this->newRelease->updateQuietly(['extra' => ['releases' => array_merge($release?->getExtra('releases') ?? [], [$this->newRelease->id])]]);
                $release?->updateQuietly(['is_active' => false]);

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
            $origin->delete();
        }

        $model->setAttribute('release_id', $this->newRelease->id);
        $model->setAttribute('release_data->source_id', $sourceId);
        $model->saveQuietly();

        if ($model->archive_at) {
            $model->delete();
        }
    }

    public function switchRelease(Model $release): array
    {
        try {
            return DB::transaction(function () use ($release) {
                if ($release->id === $this->getActiveRelease()?->id) {
                    return [
                        'status' => 'success',
                        'message' => 'The release was successfully switched!',
                    ];
                }

                if ($release->cleaned_at) {
                    return [
                        'status' => 'error',
                        'message' => 'This release is not available for switching!',
                    ];
                }

                $this->getActiveRelease()?->update([
                    'is_active' => false,
                ]);

                $release->update([
                    'is_active' => true,
                ]);

                $this->release = $release;

                foreach (array_keys(config('model-releases.models', [])) as $model) {
                    $model::query()
                        ->whereNull('release_id')
                        ->forceDelete();

                    $model::query()
                        ->with([
                            'postrelease' => fn($q) => $q->withTrashed(),
                        ])
                        ->withTrashed()
                        ->chunk(50, function ($entities) {
                            foreach ($entities as $entity) {
                                $this->doSwitch($entity);
                            }
                        });
                }

                return [
                    'status' => 'success',
                    'message' => 'The release was successfully switched!',
                ];
            });
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'message' => 'The release switching failed: ' . $e->getMessage(),
            ];
        }
    }

    private function doSwitch(Model $model): void
    {
        if ($model->postrelease) {
            $model->delete();
        } elseif ($model->deleted_at && !$model->archive_at) {
            $model->restore();
        }
    }

    public function rollbackRelease(): array
    {
        try {
            return DB::transaction(function () {
                $release = $this->getActiveRelease()->load('childrensRecursive');
                $this->prevRelease = $release?->getPrevRelease();

                if ((!$release) || ($this->prevRelease?->cleaned_at)) {
                    return [
                        'status' => 'warning',
                        'message' => 'No release available!',
                    ];
                }

                foreach (array_keys(config('model-releases.models', [])) as $model) {
                    $model::query()
                        ->whereNull('release_id')
                        ->forceDelete();

                    foreach ($release->getAllChildrens() as $child) {
                        $model::query()
                            ->where('release_id', $child->id)
                            ->forceDelete();

                        $child->delete();
                    }

                    $model::query()
                        ->with([
                            'origin' => fn($q) => $q->withTrashed(),
                        ])
                        ->withTrashed()
                        ->where('release_id', $release->id)
                        ->chunk(50, function ($entities) {
                            foreach ($entities as $entity) {
                                $this->doRollback($entity);
                            }
                        });
                }

                $this->prevRelease?->update([
                    'is_active' => true,
                ]);
                $release->delete();

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

    public function clearPrereleases(): array
    {
        foreach (array_keys(config('model-releases.models', [])) as $model) {
            $model::query()
                ->whereNull('release_id')
                ->withTrashed()
                ->forceDelete();
        }

        return [
            'status' => 'success',
            'message' => 'Prereleases was successfully cleared!',
        ];
    }

    public function getReleaseModel(): string
    {
        return config('model-release.model', \Ka4ivan\ModelReleases\Models\Release::class);
    }
}
