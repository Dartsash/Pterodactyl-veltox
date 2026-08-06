# 🚀 Готовый проект Pterodactyl с аддонами

## ⭐ Что получилось

Я добавил все аддоны прямо в ваш проект! Просто замените папки через FileZilla и запустите миграции.

### 📦 Что добавлено:

✅ **Система уведомлений** - "Good evening, dartsash" на дашборде  
✅ **Система разрешений** - управление правами администраторов  
✅ **Красивая тема** - темный режим с голубым акцентом  

---

## 🎯 Быстрая установка (5 минут)

### Способ 1️⃣: FileZilla (Рекомендуется)

1. **Скачайте и распакуйте архив** `panel_complete.tar.gz`

2. **Откройте FileZilla** и подключитесь к вашему серверу

3. **Откройте папку** `panel_complete` на вашем компьютере

4. **Перетащите все файлы** на сервер в корневую папку панели (`/var/www/pterodactyl/` или где-то ещё)
   - Когда спросит "заменить?" - нажмите "Yes to all"

5. **Откройте SSH консоль** (через PuTTY или встроенную в FileZilla):
```bash
cd /путь/до/вашей/панели
php artisan migrate
php artisan view:clear
php artisan cache:clear
```

### Способ 2️⃣: SSH (Если есть доступ)

```bash
# Загрузите архив на сервер
# Например через SCP или через панель управления

# Распакуйте
cd /var/www/pterodactyl
tar -xzf panel_complete.tar.gz --strip-components=1

# Запустите миграции
php artisan migrate

# Очистите кэши
php artisan view:clear
php artisan cache:clear
npm run build
```

---

## ✅ Проверка

После установки зайдите в админ-панель:

**http://your-panel.com/admin**

Найдите в меню **Addons** → должны быть:
- ✅ Notification Manager (управление уведомлениями)
- ✅ Permission Manager (управление правами)

---

## 📝 Что изменилось в вашем проекте

### 🆕 Новые файлы:

```
app/Models/
  ├── Notification.php
  ├── AdminPermission.php
  └── UserPermission.php

app/Http/Controllers/Admin/
  ├── NotificationController.php
  └── PermissionController.php

app/Http/Middleware/
  └── CheckCustomPermission.php

app/Policies/
  ├── AdminPermissionPolicy.php
  └── UserPermissionPolicy.php

database/migrations/
  ├── 2024_01_15_000000_create_notifications_table.php
  ├── 2024_01_15_000001_create_admin_permissions_table.php
  └── 2024_01_15_000002_create_user_permissions_table.php

resources/views/admin/addons/
  ├── notification-manager.blade.php
  └── permission-manager.blade.php

resources/scripts/components/
  └── admin/
      ├── NotificationManager.tsx
      └── PermissionManager.tsx
      
public/css/
  └── custom-theme.css (новая красивая тема)
```

### 🔄 Измененные файлы:

- `app/Models/User.php` - добавлены методы для разрешений
- `routes/api-application.php` - добавлены API маршруты
- `routes/api-client.php` - добавлен маршрут для уведомлений
- `app/Http/Kernel.php` - зарегистрирован middleware
- `resources/views/layouts/admin.blade.php` - добавлена CSS тема

**Полный список изменений смотрите в `ADDONS_CHANGES.md` внутри архива**

---

## 💡 Использование

### Создать уведомление

1. Админ-панель → **Addons** → **Notification Manager**
2. Нажмите **"+ Новое уведомление"**
3. Введите:
   - Название: "Good evening, dartsash"
   - Сообщение: "Добро пожаловать!"
4. Нажмите **Сохранить**

Уведомление будет отображаться на дашборде!

### Создать разрешение и назначить его

1. Админ-панель → **Addons** → **Permission Manager**
2. Нажмите **"+ Новое разрешение"**
3. Введите:
   - Имя: "manage.users" (без пробелов)
   - Описание: "Управление пользователями"
4. Нажмите **Сохранить**
5. Нажмите **"+ Назначить разрешение"**
6. Выберите пользователя и разрешение
7. Нажмите **Назначить**

### Проверить разрешение в коде

```php
// В контроллере
if (auth()->user()->hasAdminPermission('manage.users')) {
    // Пользователь имеет разрешение
    // Выполните действие
}

// На маршруте
Route::post('/users', 'UserController@store')
    ->middleware('check.custom.permission:manage.users');

// В Blade шаблоне
@if(auth()->user()->hasAdminPermission('manage.users'))
    <button>Управлять пользователями</button>
@endif
```

---

## 🎨 Тема

Новая красивая тема уже подключена и готова к использованию!

**Особенности:**
- Темный режим
- Голубые акценты (вместо синего по умолчанию)
- Плавные переходы и анимации
- Лучший контраст

Она включена в **`public/css/custom-theme.css`** и подключена в layout.

Если хотите изменить цвета - отредактируйте этот файл!

---

## 🐛 Если что-то не работает

### Миграции не запускаются
```bash
php artisan migrate:status    # Проверьте статус
php artisan migrate           # Попробуйте снова
```

### Аддоны не появляются в меню
```bash
php artisan view:clear
php artisan cache:clear
# Перезагрузите страницу в браузере
```

### React компоненты не загружаются
```bash
npm run build
php artisan view:clear
```

### Ошибки в логах
Проверьте файл логов:
```bash
tail -f storage/logs/laravel.log
```

---

## 📋 Файлы в архиве

```
panel_complete/
├── app/                          (обновлено)
│   ├── Models/
│   │   ├── Notification.php       (новое)
│   │   ├── AdminPermission.php    (новое)
│   │   ├── UserPermission.php     (новое)
│   │   └── User.php               (обновлено)
│   ├── Http/
│   │   ├── Controllers/Admin/
│   │   │   ├── NotificationController.php  (новое)
│   │   │   └── PermissionController.php    (новое)
│   │   ├── Middleware/
│   │   │   └── CheckCustomPermission.php   (новое)
│   │   └── Kernel.php             (обновлено)
│   └── Policies/
│       ├── AdminPermissionPolicy.php       (новое)
│       └── UserPermissionPolicy.php        (новое)
├── database/
│   └── migrations/
│       ├── 2024_01_15_000000_*.php (новое)
│       ├── 2024_01_15_000001_*.php (новое)
│       └── 2024_01_15_000002_*.php (новое)
├── resources/
│   ├── views/
│   │   ├── layouts/
│   │   │   └── admin.blade.php    (обновлено)
│   │   └── admin/addons/
│   │       ├── notification-manager.blade.php  (новое)
│   │       └── permission-manager.blade.php    (новое)
│   └── scripts/components/
│       ├── admin/
│       │   ├── NotificationManager.tsx   (новое)
│       │   └── PermissionManager.tsx     (новое)
│       └── dashboard/
│           └── NotificationDisplay.tsx   (новое)
├── public/
│   └── css/
│       └── custom-theme.css       (новое)
├── routes/
│   ├── api-application.php        (обновлено)
│   └── api-client.php             (обновлено)
├── ADDONS_CHANGES.md              (Подробное описание изменений)
└── ... (остальные файлы Pterodactyl)
```

---

## ⚡ Что дальше?

1. ✅ Загрузите файлы через FileZilla
2. ✅ Запустите `php artisan migrate`
3. ✅ Очистите кэши
4. ✅ Откройте админ-панель и проверьте Addons
5. ✅ Создайте тестовое уведомление
6. ✅ Создайте тестовое разрешение
7. ✅ Наслаждайтесь! 🎉

---

## 📞 Помощь

Если возникнут проблемы:

1. **Проверьте логи:**
   ```bash
   tail -50 storage/logs/laravel.log
   ```

2. **Убедитесь в правильности пути:**
   ```bash
   ls -la app/Models/Notification.php
   ls -la database/migrations/ | grep 2024_01_15
   ```

3. **Проверьте БД:**
   ```bash
   php artisan tinker
   >>> \App\Models\Notification::all()
   ```

---

## ✨ Готово!

Архив **`panel_complete.tar.gz`** содержит ваш полный проект с интегрированными аддонами.

**Просто замените файлы через FileZilla и наслаждайтесь! 🚀**

---

**Версия:** 1.0  
**Дата:** 2024-01-15  
**Pterodactyl:** 1.0+
