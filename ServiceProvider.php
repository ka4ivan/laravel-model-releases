<?php

namespace Ka4ivan\ModelReleases;

use Illuminate\Database\Schema\Blueprint;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/model-releases.php' => config_path('model-releases.php'),
        ]);

        if (! class_exists('CreateReleasesTable')) {
            $this->publishes([
                __DIR__.'/../database/migrations/create_releases_table.php.stub' => database_path('migrations/'.date('Y_m_d_His', time()).'_create_releases_table.php'),
            ], 'laravel-model-releases-migrations');
        }

        Blueprint::macro('releaseUuidFields', function () {
            /** @var Blueprint $this */
            $this->timestamp('archive_at')->nullable();
            $this->uuid('release_id')->nullable()->index();
            $this->uuid('prerelease_id')->nullable();
        });

        Blueprint::macro('releaseFields', function () {
            /** @var Blueprint $this */
            $this->timestamp('archive_at')->nullable();
            $this->uuid('release_id')->nullable()->index();
            $this->unsignedBigInteger('prerelease_id')->nullable();
        });
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/model-releases.php', 'model-releases');
    }
}