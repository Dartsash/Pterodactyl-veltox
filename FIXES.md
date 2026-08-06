# Почему на чистой панели тема и аддоны встали не полностью

Разбор архива проекта (Pterodactyl 1.14.1, 1836 файлов) и того, что из него реально
попадает в git.

---

## 1. Собранная тема физически не попадает на GitHub — главная причина

В `public/.gitignore` лежит:

```
assets
assets/*
!assets/svgs
!assets/svgs/*.svg
```

а в корневом `.gitignore` дополнительно `public/assets/manifest.json`.

Значит при `git push` **вся папка `public/assets/` пропускается**: 15 JS-бандлов
(`bundle.*.js`, `dashboard.*.js`, `server.*.js`, `auth.*.js`, чанки, шрифты `.woff2`)
и `manifest.json`.

Pterodactyl при рендере страницы читает `public/assets/manifest.json`. Если его нет —
в логе `file_get_contents(...manifest.json): Failed to open stream`, а в браузере белый
экран или голая панель без вашего дизайна. Именно так выглядит «тема скачалась не полностью».

**Исправлено:** `.gitignore` и `public/.gitignore` переписаны так, что собранный фронт
публикуется, а секреты и мусор — нет.

## 2. Не публикуется то, что нужно любой Laravel-панели

| Путь | Почему пропало | Последствие |
|---|---|---|
| `/vendor` | в `.gitignore` | без `composer install` панель не стартует |
| `node_modules` | в `.gitignore` | нечем собрать фронт |
| `storage/framework/*` | в `.gitignore` | `Please provide a valid cache path` |
| `resources/lang/locales.js` | в `.gitignore` | генерируется сборкой |
| `scripts/commands/misc/` | правило `misc` в `.gitignore` | часть blueprint-скриптов теряется |
| `.env` / `.env.example` | правило `.env*` | нет примера конфига для новой установки |

**Исправлено:** добавлены `.gitkeep` для `storage/framework/{cache/data,sessions,views}`,
`storage/logs`, `bootstrap/cache`; `.env.example` снова публикуется (с `APP_THEME`).

## 3. PHP-классы аддонов лежат в неправильном неймспейсе

В `composer.json` автозагрузка: `"Pterodactyl\\": "app/"`. Неймспейса `App\` в проекте нет.
При этом 8 файлов объявлены как `namespace App\...`:

```
app/Models/Notification.php, AdminPermission.php, UserPermission.php
app/Http/Controllers/Admin/NotificationController.php, PermissionController.php
app/Http/Middleware/CheckCustomPermission.php
app/Policies/AdminPermissionPolicy.php, UserPermissionPolicy.php
```

Любое обращение к ним = `Class "App\Models\Notification" not found`. На вашей рабочей
панели это не всплывало, потому что маршруты к ним не подключены.

**Исправлено:** `App\` → `Pterodactyl\` во всех восьми файлах.

## 4. Нет миграций для таблиц аддонов

В README обещаны миграции `2024_01_15_*` для `notifications`, `admin_permissions`,
`user_permissions` — в проекте их нет. Модели есть, таблиц нет →
`Base table or view not found`.

**Исправлено:** добавлены три миграции (idempotent, с `Schema::hasTable`):

- `2026_08_06_000000_create_veltox_notifications_table.php`
- `2026_08_06_000100_create_admin_permissions_table.php`
- `2026_08_06_000200_create_user_permissions_table.php`

## 5. Конфликт таблицы `notifications`

Модель `Notification` использовала таблицу `notifications`, которая уже создана штатной
миграцией Pterodactyl `2016_09_04_182835_create_notifications_table.php`
(колонки `id/type/notifiable/data/read_at`, а не `title/message/is_active/admin_id`).
Это гарантированный SQL-ошибка при первом же запросе.

**Исправлено:** таблица переименована в `veltox_notifications`, модель обновлена.

## 6. Мелочи, которые тоже ломали вид

- `public/css/custom-theme.css` не был подключён нигде → **добавлена строка** в
  `resources/views/layouts/admin.blade.php`.
- middleware `check.custom.permission` не был зарегистрирован → **добавлен** в `app/Http/Kernel.php`.

## 7. Мусор в репозитории

Удалено из сборки: `panel.tar.gz` (3 МБ), `.build-cache.json` (220 КБ), логи
`storage/logs/laravel-2026-*.log`, папки бэкапов `storage/blueprint-*`,
`storage/theme-fix-*`, `storage/version-manager-uninstall-*`. Они не нужны на чистой
панели и создают конфликты при копировании.

## 8. Безопасность: проверьте `.env`

В архиве проекта лежит рабочий `.env` с `APP_KEY`, `DB_PASSWORD`, `HASHIDS_SALT`,
данными почты. В git он не попадает по правилу `.env*`, **но если файл заливался через
веб-интерфейс GitHub (перетаскиванием), правило не действует** — файл окажется в публичном
репозитории.

Что сделать:

```bash
# есть ли .env в репозитории
git log --all --full-history -- .env
```

Если есть — сменить пароль БД, пароль SMTP, `HASHIDS_SALT`, удалить файл из истории
(`git filter-repo --path .env --invert-paths`) и сделать force-push. `APP_KEY` меняйте
только вместе со сбросом зашифрованных значений (2FA-секреты, токены).

Из этой сборки `.env` удалён, остался только `.env.example`.

---

## Правильная схема раздачи проекта

1. **Репозиторий** — исходники + собранный фронт (с новым `.gitignore`), для разработки.
2. **GitHub Releases** — архив `veltox-<версия>.tar.gz`, собранный `make-release.sh`:
   в нём точно есть `public/assets` + `manifest.json` и точно нет `.env`, `vendor`,
   `node_modules`, логов.
3. **`install.sh`** — ставит релиз на чистую панель одной командой, с бэкапом и откатом.
4. **`check-install.sh`** — если что-то не так, показывает конкретную причину.
