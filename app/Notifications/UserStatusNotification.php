<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Contracts\Queue\ShouldQueue;

class UserStatusNotification extends Notification implements ShouldQueue
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
        return ['database', 'mail']; 
    }

    public function toMail($notifiable)
    {
        $eventName = $this->registration->event->title;
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
        
        if ($this->status === 'verified') {
            $url = $frontendUrl . '/my-events/' . $this->registration->event->slug;
            return (new MailMessage)
                ->subject('Pendaftaran Disetujui!')
                ->greeting('Halo, ' . $notifiable->name . '!')
                ->line('Selamat! Pembayaran untuk kelas "' . $eventName . '" telah disetujui.')
                ->action('Masuk ke Ruang Kelas', $url)
                ->line('Terima kasih telah belajar bersama EduTech Amania!');
        } else {
            $url = $frontendUrl . '/dashboard/ticket';
            $reason = $this->registration->rejection_reason ?? 'Bukti tidak valid.';
            return (new MailMessage)
                ->subject('Pendaftaran Ditolak')
                ->greeting('Halo, ' . $notifiable->name . '!')
                ->line('Maaf, pembayaran untuk kelas "' . $eventName . '" ditolak.')
                ->line('Alasan: ' . $reason)
                ->action('Upload Ulang Bukti Pembayaran', $url)
                ->line('Silakan hubungi admin jika Anda membutuhkan bantuan.');
        }
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