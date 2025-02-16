# Model Releases (versions) for Laravel Framework

[![License](https://img.shields.io/packagist/l/ka4ivan/laravel-model-releases.svg?style=for-the-badge)](https://packagist.org/packages/ka4ivan/laravel-model-releases)
[![Build Status](https://img.shields.io/github/stars/ka4ivan/laravel-model-releases.svg?style=for-the-badge)](https://github.com/ka4ivan/laravel-model-releases)
[![Latest Stable Version](https://img.shields.io/packagist/v/ka4ivan/laravel-model-releases.svg?style=for-the-badge)](https://packagist.org/packages/ka4ivan/laravel-model-releases)
[![Total Downloads](https://img.shields.io/packagist/dt/ka4ivan/laravel-model-releases.svg?style=for-the-badge)](https://packagist.org/packages/ka4ivan/laravel-model-releases)

## 📖 Table of Contents [![Stand With Ukraine](https://raw.githubusercontent.com/vshymanskyy/StandWithUkraine/main/badges/StandWithUkraine.svg)](https://stand-with-ukraine.pp.ua)
- [Installation](#installation)
- [Usage](#usage)
    - [Preparing your model](#preparing-your-model)
    - [Preparing your migration](#preparing-your-migration)
    - [Base model usage](#base-model-usage)
        - [Store model](#store-model)
        - [Update model](#update-model)
        - [Delete model](#delete-model)
    - [Base relationships](#base-relationships)
    - [Release/Model Changelogs](#releasemodel-changelogs)
        - [Release Changelog](#release-changelog)
        - [Release Model Changelog](#release-model-changelog)
    - [Run/Rollback Releases](#runrollback-releases)
        - [Run release](#run-release)
        - [Rollback release](#rollback-release)

## Installation

1) Require this package with composer
```shell
composer require ka4ivan/laravel-model-releases
```

2) Publish package resource:
```shell
php artisan vendor:publish --provider="Ka4ivan\ModelReleases\ServiceProvider"
```
- config
- migration

#### This is the default content of the config file:
```php
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
```

3) Run migration:
```shell
php artisan migrate
```

## Usage

### Preparing your model

To associate releases with a model, the model must implement the following traits: `HasReleases`, `SoftDeletes`.
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ka4ivan\ModelReleases\Models\Traits\HasReleases;

class Article extends Model
{
    use HasUuids,
        SoftDeletes, // REQUIRED!!!
        HasReleases;
}
```

### Preparing your migration

If this is one migration.
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->nullable()->index();
            $table->string('name')->nullable();
            $table->longText('body')->nullable();
            $table->string('status')->default(true);
            $table->timestamps();
            
            $table->softDeletes();
            $table->releaseFields(); // after $table->softDeletes()
//            $table->releaseUuidFields(); if the `id` field is a uuid

            $table->uuid('category_id')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('articles');
    }
};
```

If this is an additional migration.
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->softDeletes(); // If it wasn't there before
            $table->releaseFields();
//            $table->releaseUuidFields(); If the id field is a uuid
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('deleted_at'); // If it wasn't there before
            $table->dropReleaseFields();
        });
};
```

Here's what they actually add.
```php
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
```

### Base model usage

#### Store model
```php
/**
 * @param ArticleRequest $request
 * @return \Illuminate\Http\RedirectResponse
 */
public function store(ArticleRequest $request)
{
    $data = $request->getData();

    /** @var Article $article */
    $article = Article::create($data);

    $article->sync($request->get('terms', []), [$article->category_id]);
    $article->mediaManage($request);

    return redirect()->route('admin.articles.index')
        ->with('success', trans('alerts.store.success'));
}
```

#### Update model
```php
/**
 * @param ArticleRequest $request
 * @param $id
 * @return \Illuminate\Http\RedirectResponse
 */
public function update(ArticleRequest $request, Article $article)
{
    $data = $request->getData();

    $article = $article->updateWithReleases($data);

    $article->sync($request->get('terms', []), [$article->category_id]);
    $article->mediaManage($request);

    return redirect()->route('admin.articles.index')
        ->with('success', trans('alerts.update.success'));
}
```

#### Delete model
```php
/**
 * @param Article $article
 * @return \Illuminate\Http\RedirectResponse
 */
public function destroy(Article $article)
{
    $article->deleteWithReleases();

    return redirect()->back()
        ->with('success', trans('alerts.destroy.success'));
}
```

### Base relationships
```php
/**
 * Release to which this model belongs
 *
 * @return BelongsTo
 */
public function release(): BelongsTo
{
    return $this->BelongsTo(\ModelRelease::getReleaseModel());
}

/**
 * A model that is not yet in release and is a draft of a model in release
 *
 * @return HasOne
 */
public function prerelease(): HasOne
{
    return $this->hasOne(self::class, 'id', 'prerelease_id');
}

/**
 * The model that is in the release and is the original of the model that is not in the release
 *
 * @return BelongsTo
 */
public function origin(): BelongsTo
{
    return $this->belongsTo(self::class, 'id', 'prerelease_id');
}
```

### Release/Model Changelogs

#### Release Changelog
```php
$release = Release::first();

$changelog = $release->changelog();

//    $changelog = [
//      'deleted' => [
//        'article' => [
//          0 => '9e39f921-de23-4048-9fe8-08844869a40b'
//        ]
//      ]
//      'updated' => [
//        'article' => [
//          0 => '9e39f941-6c07-4ee0-bbab-b883b2bd1219'
//        ]
//        'page' => [
//          0 => '9e39f921-e810-4272-83f6-dac8bb11f8eb'
//          1 => '9e39f941-716e-4f66-a8ff-8ecdee8903b4'
//        ]
//      ]
//      'created' => [
//        'article' => [
//          0 => '9e39f928-1c62-4b70-8c6b-2d370859296a'
//        ]
//        'page' => [
//          0 => '9e39f928-1e1b-4c20-939b-d7e30216f660'
//        ]
//      ]
//    ]
```
OR only one model.
```php
$release = Release::first();

$changelog = $release->changelog(Article::class);

//    $changelog = [
//      'deleted' => [
//        0 => '9e39f921-de23-4048-9fe8-08844869a40b'
//      ]
//      'updated' => [
//        0 => '9e39f941-6c07-4ee0-bbab-b883b2bd1219'
//      ]
//      'created' => [
//        0 => '9e39f928-1c62-4b70-8c6b-2d370859296a'
//      ]
//    ]
```

#### Release Model Changelog

```php
$article = Article::first();

$changelog = $article->changelog();

//    $changelog = [
//      '9e39f91c-6224-461c-996a-b73892cb9875' => [ // Release ID
//        'created' => App\Models\Post {▶}         // Model and action in this release
//      ]
//      '9e39f976-5bdc-4eb9-ad30-2fd3940461b2' => [
//          'updated' => App\Models\Post {▶}
//      ]
//      '9e39f9a4-bcae-42e2-b9eb-b4969308ed32' => [
//          'updated' => App\Models\Post {▶}
//      ]
//      'prerelease' => [
//          'deleted' => App\Models\Post {▶}
//      ]
//    ]
```

### Run/Rollback Releases

#### Run release
```php
$res = \ModelRelease::runRelease($data);

//    $res = [
//        'status' => 'success',
//        'message' => 'The release was successfully created!',
//    ];
//        OR
//    $res = [
//        'status' => 'error',
//        'message' => 'The release failed: ' . $e->getMessage(),
//    ]; 
```

#### Rollback release
```php
$res = \ModelRelease::rollbackRelease();

//    $res = [
//        'status' => 'success',
//        'message' => 'The last release was rollbacked!',
//    ];
//        OR
//    $res = [
//        'status' => 'warning',
//        'message' => 'No release available!',
//    ];
//        OR
//    $res = [
//        'status' => 'error',
//        'message' => 'Rollback failed: ' . $e->getMessage(),
//    ];
```
