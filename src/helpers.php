<?php

if (! function_exists('buildReleaseTree')) {
    /**
     * @param \Illuminate\Database\Eloquent\Collection $releases
     * @param string|null $parentId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    function buildReleaseTree(Illuminate\Database\Eloquent\Collection $releases, string $parentId = null): Illuminate\Database\Eloquent\Collection
    {
        return $releases
            ->filter(fn($release) => $release->parent_id === $parentId)
            ->map(function ($release) use ($releases) {
                $release->setRelation('childrens', buildReleaseTree($releases, $release->id));
                return $release;
            });
    }
}
