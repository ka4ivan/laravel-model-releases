<?php

if (! function_exists('build_release_tree')) {
    /**
     * @param \Illuminate\Database\Eloquent\Collection $releases
     * @param string|null $parentId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    function build_release_tree(Illuminate\Database\Eloquent\Collection $releases, string $parentId = null): Illuminate\Database\Eloquent\Collection
    {
        return $releases
            ->filter(fn($release) => $release->parent_id === $parentId)
            ->map(function ($release) use ($releases) {
                $release->setRelation('childrens', build_release_tree($releases, $release->id));
                return $release;
            });
    }
}
