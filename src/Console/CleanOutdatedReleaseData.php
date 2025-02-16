<?php

declare(strict_types=1);

namespace Ka4ivan\ModelReleases\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanOutdatedReleaseData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'release:clean';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean outdated release data';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $days = config('model-releases.cleanup.outdated_releases_for_days');

        if ($days <= 0) {
            $this->warn('Release data is not cleaned!');
            return;
        }

        $releaseModel = \ModelRelease::getReleaseModel();

        $releases = $releaseModel::query()
            ->where('created_at', '<=', Carbon::now()->subDays($days))
            ->whereNot('id', $releaseModel::getLastRelease()?->id)
            ->get();

        try {
            DB::transaction(function () use ($releases) {
                foreach (array_keys(config('model-releases.models', [])) as $model) {
                    $model::query()
                        ->withTrashed()
                        ->whereNotNull('deleted_at')
                        ->whereIn('release_id', $releases->pluck('id')->toArray())
                        ->chunk(50, function ($entities) {
                            foreach ($entities as $entity) {
                                $entity->forceDelete();
                            }
                        });
                }

                foreach ($releases as $release) {
                    if (!$release->cleaned_at) {
                        $release->setAttribute('cleaned_at', Carbon::now());
                        $release->saveQuietly();
                    }
                }
            });

            $this->info('Release data cleaned successfully!');

        } catch (\Exception $e) {
            $this->error('Error while cleaning up outdated release data: ' . $e->getMessage());
        }
    }
}
