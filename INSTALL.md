# Veltox для Pterodactyl 1.14.1 — установка

> Важно: Veltox — это **модифицированная панель целиком**, а не аддон поверх любой версии.
> Ставится только на **чистый Pterodactyl 1.14.1**. Другая версия = конфликты файлов.

---

## Способ 1. Готовый релиз (рекомендуется, 5 минут)

Скачивать **не через «Code → Download ZIP»**, а из раздела **Releases** — там лежит архив
с уже собранной темой (`public/assets`), которого в исходниках git нет.

```bash
# 1. Ставим чистый Pterodactyl 1.14.1 по официальной инструкции и убеждаемся, что он открывается
# 2. Забираем релиз
cd /root
wget https://github.com/Dartsash/Pterodactyl-veltox/releases/latest/download/veltox.tar.gz
mkdir -p veltox && tar -xzf veltox.tar.gz -C veltox

# 3. Ставим
cd veltox
bash install.sh /var/www/pterodactyl
```

Скрипт сам:
1. делает бэкап файлов + дамп БД в `/root/veltox-backup-<дата>`;
2. включает режим обслуживания и копирует файлы (не трогая ваш `.env`);
3. `composer install --no-dev --optimize-autoloader`;
4. проверяет, что тема собрана (`public/assets/manifest.json`);
5. `php artisan migrate --force` — создаёт таблицы аддонов;
6. чистит кэши, чинит владельца файлов, перезапускает `pteroq` / `php-fpm`.

После установки — **Ctrl+F5** в браузере (старый бандл висит в кэше).

---

## Способ 2. Из репозитория (для разработки)

Здесь тему придётся собрать самому — это нормально, так и должно быть.

```bash
cd /var/www/pterodactyl
git clone https://github.com/Dartsash/Pterodactyl-veltox.git /root/veltox-src
rsync -a --exclude '.git' --exclude '.env' /root/veltox-src/ /var/www/pterodactyl/

composer install --no-dev --optimize-autoloader

# фронтенд: нужен Node 18+ и yarn
npm install -g yarn
yarn install --frozen-lockfile
yarn build:production          # создаёт public/assets/*.js + manifest.json

php artisan migrate --force
php artisan optimize:clear
chown -R www-data:www-data /var/www/pterodactyl
chmod -R 755 storage bootstrap/cache
systemctl restart pteroq php8.3-fpm nginx
```

---

## Проверка после установки

```bash
bash check-install.sh /var/www/pterodactyl
```

Скрипт проверяет: `.env` и `APP_KEY`, `vendor/`, наличие `public/assets/manifest.json` и всех
файлов из него, папки `storage/framework/*`, неймспейсы PHP-классов, таблицы
`staff_roles`, `admin_permissions`, `user_permissions`, `veltox_notifications`, `addons`,
владельца `storage` и последние строки лога.

---

## Типовые ошибки и лечение

| Симптом | Причина | Лечение |
|---|---|---|
| Белый экран, в логе `file_get_contents(.../public/assets/manifest.json)` | Тема не попала в git (`public/.gitignore` исключает `assets`) | Взять релиз или `yarn build:production` |
| Панель выглядит как обычный Pterodactyl | Загружен старый бандл из кэша браузера / не собран фронт | Ctrl+F5, `php artisan view:clear`, пересобрать фронт |
| `Please provide a valid cache path` | Нет `storage/framework/{cache,sessions,views}` (git не хранит пустые папки) | `mkdir -p storage/framework/{cache/data,sessions,views}` + chown |
| `Class "App\Models\Notification" not found` | Composer грузит только неймспейс `Pterodactyl\`, а не `App\` | Исправлено в этой сборке; после копирования `composer dump-autoload` |
| `Base table or view not found: 'admin_permissions'` | Не было миграций для таблиц аддонов | `php artisan migrate --force` (миграции добавлены) |
| `500` после копирования файлов | Владелец файлов root | `chown -R www-data:www-data /var/www/pterodactyl` |
| Ошибки composer про `ext-*` / версию PHP | На чистом сервере другая версия PHP | Нужен PHP 8.2/8.3 с расширениями из требований Pterodactyl |

---

## Откат

```bash
tar -xzf /root/veltox-backup-<дата>/panel-files.tar.gz -C /var/www/pterodactyl
mysql -u pterodactyl -p panel < /root/veltox-backup-<дата>/database.sql
php artisan optimize:clear
```

---

## Выпуск новой версии

Когда на рабочей панели всё как надо:

```bash
bash make-release.sh /var/www/pterodactyl 1.0.1
# -> /root/veltox-1.0.1.tar.gz  ->  GitHub -> Releases -> Draft a new release -> прикрепить файл
```
