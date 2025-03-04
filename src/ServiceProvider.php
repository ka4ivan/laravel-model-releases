<?php

declare(strict_types=1);

namespace Ka4ivan\ModelReleases;

use Illuminate\Database\Schema\Blueprint;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function boot(): void
    {
        $this->publishConfig();

        $this->publishMigration();

        $this->registerMigrationMacros();
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/model-releases.php', 'model-releases');

        $this->app->singleton(\Ka4ivan\ModelReleases\ModelRelease::class, function () {
            return new \Ka4ivan\ModelReleases\ModelRelease;
        });

        $this->commands([
            Console\CleanOutdatedReleaseData::class,
        ]);
    }

    protected function publishConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../config/model-releases.php' => config_path('model-releases.php'),
        ], 'model-releases');
    }

    protected function publishMigration(): void
    {
        if (! class_exists('CreateReleasesTable')) {
            $this->publishes([
                __DIR__.'/../database/migrations/create_releases_table.php.stub' => database_path('migrations/0002_02_02_000001_create_releases_table.php'),
            ], 'laravel-model-releases-migrations');
        }
    }

    protected function registerMigrationMacros(): void
    {
        Blueprint::macro('releaseUuidFields', function () {
            /** @var Blueprint $this */
            $this->timestamp('archive_at')->nullable();
            $this->json('release_data')->nullable();
            $this->foreignUuid('release_id')->nullable()->constrained('releases')->onDelete('set null');
            $this->uuid('prerelease_id')->nullable();
        });

        Blueprint::macro('releaseFields', function () {
            /** @var Blueprint $this */
            $this->timestamp('archive_at')->nullable();
            $this->json('release_data')->nullable();
            $this->foreignUuid('release_id')->nullable()->constrained('releases')->onDelete('set null');
            $this->unsignedBigInteger('prerelease_id')->nullable();
        });

        Blueprint::macro('dropReleaseFields', function () {
            /** @var Blueprint $this */
            $this->dropForeign(['release_id']);
            $this->dropColumn(['release_id', 'prerelease_id', 'release_data', 'archive_at']);
        });
    }
}