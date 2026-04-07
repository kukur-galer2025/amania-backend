<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue; // 🔥 TAMBAHKAN INI
use Illuminate\Notifications\Notification;

// 🔥 TAMBAHKAN "implements ShouldQueue" DI SINI
class AdminAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function via($notifiable)
    {
        return ['database']; // Simpan ke tabel 'notifications'
    }

    public function toDatabase($notifiable)
    {
        return $this->data;
    }
}