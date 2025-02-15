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
                __DIR__.'/../database/migrations/create_releases_table.php.stub' => database_path('migrations/'.date('Y_m_d_His', time()).'_create_releases_table.php'),
            ], 'laravel-model-releases-migrations');
        }
    }

    protected function registerMigrationMacros(): void
    {
        Blueprint::macro('releaseUuidFields', function () {
            /** @var Blueprint $this */
            $this->timestamp('archive_at')->nullable()->after('deleted_at');
            $this->foreignUuid('release_id')->nullable()->constrained('releases')->onDelete('set null');
            $this->uuid('prerelease_id')->nullable();
        });

        Blueprint::macro('releaseFields', function () {
            /** @var Blueprint $this */
            $this->timestamp('archive_at')->nullable()->after('deleted_at');
            $this->foreignUuid('release_id')->nullable()->constrained('releases')->onDelete('set null');
            $this->unsignedBigInteger('prerelease_id')->nullable();
        });

        Blueprint::macro('dropReleaseFields', function () {
            /** @var Blueprint $this */
            $this->dropForeign(['release_id']);
            $this->dropColumn(['release_id', 'prerelease_id', 'archive_at']);
        });
    }
}