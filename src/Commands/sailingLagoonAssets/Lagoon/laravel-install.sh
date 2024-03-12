#!/bin/sh

##########################################
# Install Laravel
##########################################
echo "Laravel install starting"
if [ ! -f "/app/_tmp-laravel/composer.json" ]; then
  composer create-project laravel/laravel _tmp-laravel
fi

cd _tmp-laravel

##########################################
# Syny new Laravel
##########################################
echo "Syncing new Laravel"
rsync -a \
  --exclude ".git" \
  --exclude ".gitignore" \
  --exclude ".env*" \
  --exclude "README.md" \
  . /app
#  --dry-run \

echo "" >> /app/.gitignore
echo "### Laravel Standard Gitignore" >> /app/.gitignore
cat .gitignore >> /app/.gitignore

echo "Laravel synced to the app directory"
cd /app

##########################################
# Install Horizon
##########################################
/app/lagoon/laravel-install-horizon.sh

##########################################
# Initial Frontend Build
##########################################
/app/lagoon/laravel-install-frontend.sh

##########################################
# Install Horizon
##########################################
echo "Complete!"
