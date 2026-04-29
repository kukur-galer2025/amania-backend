<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class UserStatusNotification extends Notification
{
    use Queueable;

    public $registration;
    public $status;

    public function __construct($registration, $status)
    {
        $this->registration = $registration;
        $this->status = $status;
    }

    public function via($notifiable)
    {
        return ['database']; 
    }

    public function toArray($notifiable)
    {
        $eventName = $this->registration->event->title;
        $eventSlug = $this->registration->event->slug; // Ambil slug untuk link
        
        if ($this->status === 'verified') {
            return [
                'title' => 'Pendaftaran Diverifikasi! 🎉',
                'message' => "Selamat! Pembayaran untuk kelas \"$eventName\" telah disetujui. Silakan masuk ke Ruang Kelas.",
                'event_name' => $eventName,
                'status' => 'verified',
                // 🔥 PERBAIKAN: Langsung arahkan ke Ruang Kelas (My Events)
                'url' => "/my-events/{$eventSlug}"
            ];
        } else {
            return [
                'title' => 'Pendaftaran Ditolak ❌',
                'message' => "Maaf, pembayaran untuk kelas \"$eventName\" ditolak. Alasan: " . ($this->registration->rejection_reason ?? 'Bukti tidak valid.'),
                'event_name' => $eventName,
                'status' => 'rejected',
                // 🔥 PERBAIKAN: Arahkan ke halaman tiket agar bisa reupload bukti
                'url' => '/dashboard/ticket'
            ];
        }
    }
}