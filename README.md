# Veltox Panel

Сборка Pterodactyl Panel **1.14.1** с собственными аддонами и тёмной темой Veltox.

> **Это не плагин и не Blueprint-расширение.** В репозитории лежит модифицированная панель
> целиком. Ставится поверх **чистого Pterodactyl ровно 1.14.1**. Другая версия — конфликты файлов.

---

## Состав

| Аддон | Что делает | Страница |
|---|---|---|
| Version Manager | Установка ядер: Paper, Purpur, Folia, Leaves, Fabric, Forge, NeoForge, Quilt, Mohist, Vanilla, прокси | `/server/<id>/versions` |
| Plugin Manager | Каталог плагинов с выбором версий | `/server/<id>/addons` |
| Mod Installer | Поиск и установка модов с Modrinth в `/mods` | `/server/<id>/mods` |
| Minecraft Player Manager | Вайтлист, операторы, баны игроков и IP | `/server/<id>/players` |
| Config Editor | Редактор `server.properties` | `/server/<id>/properties` |
| Permission Manager | Роли персонала и права админов | `/admin/addons/permissions` |
| SubNavigation | Прокрутка и иконки во вкладках сервера | везде |

Админка аддонов: `/admin/addons`.

---

## Требования

- Pterodactyl Panel **1.14.1** (Laravel 11.47)
- PHP **8.2 или 8.3** + расширения: `json, mbstring, pdo, pdo_mysql, posix, zip`
- **Node.js 22+** и Yarn (`package.json` требует `node >= 22`)
- Composer 2, git, unzip
- **Минимум 2 ГБ RAM** на время сборки фронта (иначе webpack падает с OOM — см. раздел проблем)
- Исходящий доступ в сеть с самой панели:
  `api.papermc.io`, `fill.papermc.io`, `api.purpurmc.org`, `api.github.com`, `github.com`,
  `objects.githubusercontent.com`, `meta.fabricmc.net`, `meta.quiltmc.org`,
  `maven.neoforged.net`, `maven.minecraftforge.net`, `repo1.maven.org`,
  `api.modrinth.com`, `launchermeta.mojang.com`
- FontAwesome 5 в клиентской части, FontAwesome 4 в админке

---

## Установка — способ 1: готовый релиз (рекомендуется)

В релизе уже лежит собранный фронтенд, сборка на VPS не нужна.

```bash
# чистый Pterodactyl 1.14.1 уже установлен и открывается в браузере
cd /root
wget https://github.com/Dartsash/Pterodactyl-veltox/releases/latest/download/veltox.tar.gz
mkdir -p veltox && tar -xzf veltox.tar.gz -C veltox
cd veltox
bash install.sh /var/www/pterodactyl
```

`install.sh` делает: бэкап файлов + дамп БД в `/root/veltox-backup-<дата>` → `php artisan down` →
копирование файлов (ваш `.env` не трогается) → `composer install` → проверка собранного
фронта → `php artisan migrate --force` → сброс кэшей → `chown` → рестарт `pteroq` и `php-fpm`.
Команда отката печатается в конце вывода.

После установки — **Ctrl+F5** в браузере.

---

## Установка — способ 2: из исходников

Здесь фронтенд собирается на месте. **Без сборки темы не будет** — весь дизайн живёт
в `resources/scripts` и `tailwind.*.js`, а не в готовом CSS.

```bash
cd /root
git clone https://github.com/Dartsash/Pterodactyl-veltox.git veltox-src

# 1. копируем всё, кроме .env и служебного
rsync -a --exclude '.git' --exclude '.env' veltox-src/ /var/www/pterodactyl/
cd /var/www/pterodactyl

# 2. PHP-зависимости
composer install --no-dev --optimize-autoloader

# 3. фронтенд (Node 22+)
npm install -g yarn
yarn install --frozen-lockfile
yarn build:production

# 4. БД и кэши
php artisan migrate --force
php artisan optimize:clear

# 5. права и сервисы
chown -R www-data:www-data /var/www/pterodactyl
chmod -R 755 storage bootstrap/cache
systemctl restart pteroq php8.3-fpm nginx
```

Проверка установки:

```bash
bash check-install.sh /var/www/pterodactyl
```

---

## Модифицированные файлы Pterodactyl

Это не новые файлы, а переписанные штатные. При обновлении панели они будут
перезаписаны upstream-версиями, и аддоны отвалятся:

- `routes/admin.php`, `routes/api-client.php`
- `resources/lang/en/activity.php`
- `resources/scripts/routers/routes.ts`, `resources/scripts/routers/ServerRouter.tsx`
- `resources/scripts/components/elements/SubNavigation.tsx`
- `app/Http/Controllers/Admin/AddonController.php`
- `app/Http/Kernel.php`, `resources/views/layouts/admin.blade.php`
- `tailwind.config.js`, `tailwind.base.config.js`, `webpack.config.js`, `package.json`

Порядок при обновлении: сначала обновить панель, потом снова `install.sh`, потом
глазами сверить эти файлы с новым upstream.

---

## Тема Veltox

- `tailwind.veltox-palette.js` — палитра, здесь правятся цвета
- `tailwind.base.config.js` + `tailwind.config.js` — база и склейка с палитрой
- `resources/scripts/assets/tailwind.css` — градиент фона и общий тёмный вид
- `resources/scripts/assets/css/GlobalStylesheet.ts` — шрифт и базовые стили
- `public/css/custom-theme.css` — тема админки, подключена в `resources/views/layouts/admin.blade.php`

Любая правка темы требует `yarn build:production` и Ctrl+F5.

---

## Частые проблемы

| Симптом | Причина и лечение |
|---|---|
| Белый экран, в логе `manifest.json ... Failed to open stream` | Фронт не собран. `yarn install && yarn build:production` или берите релиз |
| Панель выглядит как стоковая | Кэш браузера → Ctrl+F5; `php artisan view:clear` |
| `Please provide a valid cache path` | `mkdir -p storage/framework/{cache/data,sessions,views}` + `chown -R www-data:www-data storage` |
| `Base table or view not found` | `php artisan migrate --force` |
| `Class "App\..." not found` | Неймспейс должен быть `Pterodactyl\`, затем `composer dump-autoload` |
| `yarn build:production` убивается (`Killed`) | Мало RAM. Добавьте swap: `fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile` |
| 500 сразу после копирования | Файлы принадлежат root → `chown -R www-data:www-data /var/www/pterodactyl` |

---

## Откат

```bash
tar -xzf /root/veltox-backup-<дата>/panel-files.tar.gz -C /var/www/pterodactyl
mysql -u pterodactyl -p panel < /root/veltox-backup-<дата>/database.sql
php artisan optimize:clear
```

---

## Выпуск новой версии

```bash
bash make-release.sh /var/www/pterodactyl 1.0.1
# -> /root/veltox-1.0.1.tar.gz -> GitHub -> Releases -> Draft a new release
```

---

## Структура репозитория

```
app/  bootstrap/  config/  database/  public/  resources/  routes/  storage/
install.sh          установка на чистую панель
check-install.sh    диагностика после установки
make-release.sh     сборка релизного архива
INSTALL.md          подробная инструкция
FIXES.md            что и почему было исправлено
```

В репозитории **нет и не должно быть**: `.env`, `vendor/`, `node_modules/`, логов,
бэкапов `storage/*-backup*`.

## Лицензия

Pterodactyl Panel — MIT (см. `LICENSE.md`). Изменения Veltox распространяются на тех же условиях.
