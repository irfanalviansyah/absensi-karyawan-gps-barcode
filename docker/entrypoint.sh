#!/bin/bash
set -e

echo "🚀 Starting Absensi Karyawan..."

# Create .env file from environment variables if not exists
if [ ! -f /var/www/.env ]; then
    echo "📝 Creating .env from environment variables..."
    cp /var/www/.env.example /var/www/.env
fi

# Wait for DB using PHP PDO
echo "⏳ Waiting for database..."
until php -r "
try {
    new PDO(
        'mysql:host=' . getenv('DB_HOST') . ';port=' . (getenv('DB_PORT') ?: '3306'),
        getenv('DB_USERNAME'),
        getenv('DB_PASSWORD')
    );
    exit(0);
} catch (Exception \$e) {
    exit(1);
}
" 2>/dev/null; do
    echo "  ...waiting for MySQL at ${DB_HOST:-db}:${DB_PORT:-3306}"
    sleep 2
done
echo "✅ Database ready!"

# Generate app key if not set or placeholder
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "yourappkey" ]; then
    echo "🔑 Generating app key..."
    php artisan key:generate --force
    export APP_KEY=$(grep '^APP_KEY=' /var/www/.env | cut -d= -f2)
fi

# Run migrations
echo "📦 Running migrations..."
php artisan migrate --force

# Seed if fresh DB
USER_COUNT=$(php artisan tinker --execute="echo \App\Models\User::count();" 2>/dev/null || echo "0")
if [ "$USER_COUNT" = "0" ]; then
    echo "🌱 Seeding database..."
    php artisan db:seed --force
fi

# Cache
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Storage link
php artisan storage:link --force 2>/dev/null || true

echo "✅ App ready at http://localhost:8000"

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
