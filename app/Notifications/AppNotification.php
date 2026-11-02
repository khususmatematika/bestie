<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Notifikasi dalam aplikasi (kanal database saja; tanpa email/antrian).
 */
final class AppNotification extends Notification
{
    /**
     * @param  string  $kind  paket_baru | minta_ganti | unggah_ulang | fix | tenggat
     */
    public function __construct(
        private readonly string $title,
        private readonly string $body,
        private readonly string $url,
        private readonly string $kind,
        private readonly ?string $key = null,
    ) {
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'url' => $this->url, 'kind' => $this->kind, 'key' => $this->key];
    }
}
