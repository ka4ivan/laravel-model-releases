<?php

declare(strict_types=1);

namespace Ka4ivan\ModelReleases\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class Release extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [
        'id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'extra' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function childrens()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function childrensRecursive()
    {
        return $this->childrens()->with('childrensRecursive');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

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
                    'origin' => fn($q) => $q->withTrashed(),
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

    public static function getActiveRelease(): ?self
    {
        return self::query()
            ->where('is_active', true)
            ->first();   
    }

    public static function getLastRelease(): ?self
    {
        return self::query()
            ->orderBy('created_at', 'desc')
            ->first();
    }

    public function getPrevRelease(): ?self
    {
        return $this->parent;
    }

    public function getNextRelease(): ?self
    {
        return self::query()
            ->where('created_at', '>', $this->created_at)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    public function getAllChildrens(): Collection
    {
        $allChildrens = collect();

        foreach ($this->childrensRecursive as $child) {
            $allChildrens->push($child);
            $allChildrens = $allChildrens->merge($child->getAllChildrens());
        }

        return $allChildrens;
    }

    /**
     * @param $key
     * @param null $default
     * @return array|\ArrayAccess|mixed
     */
    public function getExtra($key, $default = null)
    {
        return Arr::get($this->extra ?? [], $key, $default);

    }
}
