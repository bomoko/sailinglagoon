#!/bin/sh

echo Installing Laravel Horizon
composer require laravel/horizon
php artisan horizon:install
