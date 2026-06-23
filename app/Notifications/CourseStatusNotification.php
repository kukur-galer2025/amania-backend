<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Contracts\Queue\ShouldQueue;

class CourseStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $transaction;
    public $status;

    public function __construct($transaction, $status)
    {
        $this->transaction = $transaction;
        $this->status = $status;
    }

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable)
    {
        $courseName = $this->transaction->course->title;
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
        
        if ($this->status === 'verified') {
            $url = $frontendUrl . '/courses/' . $this->transaction->course->slug . '/learn';
            return (new MailMessage)
                ->subject('Pembelian Kursus Disetujui!')
                ->greeting('Halo, ' . $notifiable->name . '!')
                ->line('Selamat! Pembayaran untuk kursus "' . $courseName . '" telah disetujui.')
                ->action('Mulai Belajar Sekarang', $url)
                ->line('Terima kasih telah bergabung dengan kami!');
        } else {
            $url = $frontendUrl . '/transactions';
            $reason = $this->transaction->rejection_reason ?? 'Bukti tidak valid.';
            return (new MailMessage)
                ->subject('Pembelian Kursus Ditolak')
                ->greeting('Halo, ' . $notifiable->name . '!')
                ->line('Maaf, pembayaran untuk kursus "' . $courseName . '" ditolak.')
                ->line('Alasan: ' . $reason)
                ->action('Upload Ulang Bukti', $url)
                ->line('Silakan periksa dan upload ulang bukti transfer Anda.');
        }
    }

    public function toArray($notifiable)
    {
        $courseName = $this->transaction->course->title;
        
        if ($this->status === 'verified') {
            return [
                'title' => 'Pembelian Kursus Sukses',
                'message' => 'Pembayaran Anda untuk kursus "' . substr($courseName, 0, 30) . '..." telah diverifikasi.',
                'type' => 'success',
                'url' => '/my-courses'
            ];
        } else {
            return [
                'title' => 'Pembelian Kursus Ditolak',
                'message' => 'Pembayaran Anda ditolak. Alasan: ' . ($this->transaction->rejection_reason ?? 'Cek detail di transaksi.'),
                'type' => 'danger',
                'url' => '/transactions'
            ];
        }
    }
}
