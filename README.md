# Model releases (versions) for Laravel Framework

[![License](https://img.shields.io/packagist/l/ka4ivan/laravel-model-releases.svg?style=for-the-badge)](https://packagist.org/packages/ka4ivan/laravel-model-releases)
[![Build Status](https://img.shields.io/github/stars/ka4ivan/laravel-model-releases.svg?style=for-the-badge)](https://github.com/ka4ivan/laravel-model-releases)
[![Latest Stable Version](https://img.shields.io/packagist/v/ka4ivan/laravel-model-releases.svg?style=for-the-badge)](https://packagist.org/packages/ka4ivan/laravel-model-releases)
[![Total Downloads](https://img.shields.io/packagist/dt/ka4ivan/laravel-model-releases.svg?style=for-the-badge)](https://packagist.org/packages/ka4ivan/laravel-model-releases)
[![Stand With Ukraine](https://raw.githubusercontent.com/vshymanskyy/StandWithUkraine/main/badges/StandWithUkraine.svg)](https://stand-with-ukraine.pp.ua)


## 📖 Table of Contents
- [Installation](#installation)
- [Usage](#usage)
    - [Preparing your model](#preparing-your-model)
    - [Preparing your migration](#preparing-your-migration)
    - [Base model usage](#base-model-usage)
        - [Store model](#store-model)
        - [Update model](#update-model)
        - [Delete model](#delete-model)
    - [Base relationships](#base-relationships)
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

This is the default content of the config file:
```php
<?php

return [
    /**
     * Models with relations for which slugs will be created using the command
     */
    'models' => [
//        \App\Models\Article::class => [
//            'relations' => [
//                'media',
//                'translations',
//            ],
//        ],
//        \App\Models\Translations\ArticleTranslation::class => [],
//        \App\Models\Media::class => [],
    ],

    /**
     * Release model
     */
    'model' => \Ka4ivan\ModelReleases\Models\Release::class,
];
```

3) Run migration:
```shell
php artisan migrate
```


## Usage

### Preparing your model

To associate releases with a model, the model must implement the following traits: HasReleases, SoftDeletes.
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
            $table->releaseFields(); // After $table->softDeletes()
//            $table->releaseUuidFields(); If the id field is a uuid

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
 * @return RedirectResponse
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
$res = \ModelRelease::runRelease($data);


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
