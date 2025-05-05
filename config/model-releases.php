<?php

return [
    /**
     * Models
     */
    'models' => [
//        \App\Models\Post::class,
//        \App\Models\Translations\PostTranslation::class,
//        \App\Models\Media::class,
    ],

    /**
     * Release model
     */
    'model' => \Ka4ivan\ModelReleases\Models\Release::class,

    /**
     * Number of days after which release data will be considered stale and will be purged.
     *
     * If the number of days is 0, data will not be purged.
     *
     * It is impossible to rollback to a purged release!
     */
    'cleanup' => [
        'outdated_releases_for_days' => 30,
    ],
];
