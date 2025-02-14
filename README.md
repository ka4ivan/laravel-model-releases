# Model releases (versions) for Laravel Framework

[![License](https://img.shields.io/packagist/l/ka4ivan/laravel-model-releases.svg?style=for-the-badge)](https://packagist.org/packages/ka4ivan/laravel-model-releases)
[![Build Status](https://img.shields.io/github/stars/ka4ivan/laravel-model-releases.svg?style=for-the-badge)](https://github.com/ka4ivan/laravel-model-releases)
[![Latest Stable Version](https://img.shields.io/packagist/v/ka4ivan/laravel-model-releases.svg?style=for-the-badge)](https://packagist.org/packages/ka4ivan/laravel-model-releases)
[![Total Downloads](https://img.shields.io/packagist/dt/ka4ivan/laravel-model-releases.svg?style=for-the-badge)](https://packagist.org/packages/ka4ivan/laravel-model-releases)

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

3) Run migration:
```shell
php artisan migrate
```

<!-- 

## Usage

### Examples usage

```php
{!! \Sort::getSortLink('status', 'Status') !!}
// <a class="lte-sort-link" href="http://test.test?sort=status&amp;order=asc" style="position: relative">Status </a>

{!! \Sort::getSortLink(sort: 'city', text: 'City', order: 'desc', class: 'text-black') !!}
// <a class="text-black" href="http://test.test?sort=city&amp;order=desc" style="position: relative">City </a>

{!! (new \Ka4ivan\ViewSortable\Support\Sort)->getSortLink('phone', 'Phone') !!}
// <a class="lte-sort-link" href="http://test.test?sort=phone&amp;order=asc" style="position: relative">Phone </a>
```
 -->