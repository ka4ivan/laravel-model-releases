<?php

declare(strict_types=1);

namespace Ka4ivan\ModelReleases\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Release extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [
        'id',
    ];

    public function changelog(string $model = null, array $fields = []): array
    {
        $changelog = [
            'deleted' => [],
            'updated' => [],
            'created' => [],
        ];

        $models = $model ? [$model] : array_keys(config('model-releases.models', []));

        foreach ($models as $modelClass) {
            $entities = $modelClass::query()
                ->where('release_id', $this->id)
                ->withTrashed()
                ->with([
                    'origin' => fn($q) => $q->withTrashed()->where('release_id', $this->previousRelease()?->id),
                ])
                ->get();

            foreach ($entities as $entity) {
                $type = $entity->archive_at && $entity->origin ? 'deleted'
                    : ($entity->origin ? 'updated' : 'created');

                $filteredEntity = $fields
                    ? $entity->only($fields)
                    : $entity->id;

                if ($model) {
                    $changelog[$type][] = $filteredEntity;
                } else {
                    $changelog[$type][$entity->getMorphClass()][] = $filteredEntity;
                }
            }
        }

        return array_filter($changelog);
    }

    public function previousRelease(): ?self
    {
        return self::query()
            ->where('created_at', '<', $this->created_at)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    public function nextRelease(): ?self
    {
        return self::query()
            ->where('created_at', '>', $this->created_at)
            ->orderBy('created_at', 'desc')
            ->first();
    }
}
