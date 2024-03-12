#!/bin/sh
rm -rf _tmp-laravel/

rm -rf .editorconfig
rm -rf .gitattributes
rm -rf app/
rm -rf artisan
rm -rf bootstrap/
rm -rf composer.json
rm -rf composer.lock
rm -rf config/
rm -rf database/
rm -rf package.json
rm -rf phpunit.xml
rm -rf public/
rm -rf resources/
rm -rf routes/
rm -rf tests/
rm -rf vendor/
rm -rf vendor/
rm -rf vite.config.js
rm -rf package-lock.json
rm -rf node_modules
rm -rf supervisord.log
rm -rf supervisord.pid

rm -rf storage

cp .gitignore.default .gitignore

rm -rf .env
