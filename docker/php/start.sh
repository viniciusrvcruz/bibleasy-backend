# Ensure .env exists
if [ ! -f .env ]; then
  echo "Copying .env.example to .env..."
  cp .env.example .env
fi

echo "Installing dependencies with composer..."
composer install

# Generate application key (only if APP_KEY is not set)
if ! grep -q "APP_KEY=" .env || [ -z "$(grep 'APP_KEY=' .env | cut -d '=' -f2)" ]; then
  echo "Generating application key..."
  php artisan key:generate
fi

echo "Running migrations..."
php artisan migrate --force

php artisan serve --host=0.0.0.0 --port=80