<?php

return [
    /**
     * Models with relations for which slugs will be created using the command
     */
    'models' => [
//        \App\Models\Post::class => [
//            'relations' => [
//                'media',
//                'translations',
//            ],
//        ],
//        \App\Models\Translations\PostTranslation::class => [],
//        \App\Models\Media::class => [],
    ],

    /**
     * Release model
     */
    'model' => \Ka4ivan\ModelReleases\Models\Release::class,
];
