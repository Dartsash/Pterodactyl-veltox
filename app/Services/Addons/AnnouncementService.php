<?php

namespace Pterodactyl\Services\Addons;

use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

/**
 * Backs the "Announcement" addon.
 *
 * Stores a single panel-wide notification (title + message + style) that is
 * rendered on the client dashboard, right above the "Good evening, {user}"
 * greeting. Everything lives in the settings table, so no migration is needed.
 */
class AnnouncementService
{
    public const SETTING_ENABLED = 'settings::addons:announcement_enabled';
    public const SETTING_TITLE = 'settings::addons:announcement_title';
    public const SETTING_MESSAGE = 'settings::addons:announcement_message';
    public const SETTING_TYPE = 'settings::addons:announcement_type';
    public const SETTING_DISMISSIBLE = 'settings::addons:announcement_dismissible';
    public const SETTING_ADMIN_ONLY = 'settings::addons:announcement_admin_only';
    public const SETTING_UPDATED_AT = 'settings::addons:announcement_updated_at';

    /**
     * Available colour styles for the banner.
     */
    public const TYPES = [
        'info' => 'Info (purple)',
        'success' => 'Success (green)',
        'warning' => 'Warning (yellow)',
        'danger' => 'Danger (red)',
    ];

    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    /**
     * Whether the addon is switched on at all. Off by default.
     */
    public function addonEnabled(): bool
    {
        return (bool) $this->settings->get(self::SETTING_ENABLED, '0');
    }

    public function title(): string
    {
        return (string) $this->settings->get(self::SETTING_TITLE, '');
    }

    public function message(): string
    {
        return (string) $this->settings->get(self::SETTING_MESSAGE, '');
    }

    public function type(): string
    {
        $type = (string) $this->settings->get(self::SETTING_TYPE, 'info');

        return array_key_exists($type, self::TYPES) ? $type : 'info';
    }

    public function dismissible(): bool
    {
        return (bool) $this->settings->get(self::SETTING_DISMISSIBLE, '1');
    }

    public function adminOnly(): bool
    {
        return (bool) $this->settings->get(self::SETTING_ADMIN_ONLY, '0');
    }

    /**
     * Changes bump this value so a dismissed banner shows up again once the
     * text is edited.
     */
    public function version(): string
    {
        return (string) $this->settings->get(self::SETTING_UPDATED_AT, '0');
    }

    /**
     * Persist everything coming from the admin form.
     */
    public function save(array $data): void
    {
        $type = $data['type'] ?? 'info';

        $this->settings->set(self::SETTING_ENABLED, !empty($data['enabled']) ? '1' : '0');
        $this->settings->set(self::SETTING_TITLE, trim((string) ($data['title'] ?? '')));
        $this->settings->set(self::SETTING_MESSAGE, trim((string) ($data['message'] ?? '')));
        $this->settings->set(self::SETTING_TYPE, array_key_exists($type, self::TYPES) ? $type : 'info');
        $this->settings->set(self::SETTING_DISMISSIBLE, !empty($data['dismissible']) ? '1' : '0');
        $this->settings->set(self::SETTING_ADMIN_ONLY, !empty($data['admin_only']) ? '1' : '0');
        $this->settings->set(self::SETTING_UPDATED_AT, (string) time());
    }

    /**
     * Payload handed to the frontend. Returns null when there is nothing to
     * display, which keeps the React side very small.
     */
    public function toArray(bool $isAdmin = false): ?array
    {
        if (!$this->addonEnabled()) {
            return null;
        }

        if ($this->adminOnly() && !$isAdmin) {
            return null;
        }

        $title = $this->title();
        $message = $this->message();

        if ($title === '' && $message === '') {
            return null;
        }

        return [
            'title' => $title,
            'message' => $message,
            'type' => $this->type(),
            'dismissible' => $this->dismissible(),
            'version' => $this->version(),
        ];
    }
}
