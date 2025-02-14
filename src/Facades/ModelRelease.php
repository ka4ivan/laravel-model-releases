<?php

declare(strict_types=1);

namespace Ka4ivan\ModelReleases\Facades;

class ModelRelease extends \Illuminate\Support\Facades\Facade
{
    /**
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return \Ka4ivan\ModelReleases\ModelRelease::class;
    }
}