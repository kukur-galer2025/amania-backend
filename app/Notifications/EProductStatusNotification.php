<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EProductStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $transaction;
    public $status;

    public function __construct($transaction, $status)
    {
        $this->transaction = $transaction;
        $this->status = $status;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
        
        $itemNames = $this->transaction->items && $this->transaction->items->count() > 0 
            ? $this->transaction->items->map(function($i) { return $i->product ? $i->product->title : 'Produk'; })->implode(', ')
            : 'E-Produk';

        if ($this->status === 'verified') {
            $url = $frontendUrl . '/my-e-products';
            return (new MailMessage)
                ->subject('Pembelian E-Produk Berhasil!')
                ->greeting('Halo, ' . $notifiable->name . '!')
                ->line('Selamat! Pembayaran untuk pesanan "' . $itemNames . '" telah berhasil diverifikasi.')
                ->action('Akses E-Produk Anda', $url)
                ->line('Terima kasih telah belajar bersama EduTech Amania!');
        } else {
            $url = $frontendUrl . '/transactions';
            $reason = $this->transaction->rejection_reason ?? 'Tidak ada alasan spesifik.';
            return (new MailMessage)
                ->subject('Pesanan E-Produk Ditolak')
                ->greeting('Halo, ' . $notifiable->name . '!')
                ->line('Maaf, bukti pembayaran Anda untuk "' . $itemNames . '" telah ditolak oleh Admin.')
                ->line('Alasan penolakan: ' . $reason)
                ->action('Upload Ulang Bukti Pembayaran', $url)
                ->line('Silakan hubungi admin jika Anda membutuhkan bantuan.');
        }
    }

    public function toArray(object $notifiable): array
    {
        $itemNames = $this->transaction->items && $this->transaction->items->count() > 0 
            ? $this->transaction->items->map(function($i) { return $i->product ? $i->product->title : 'Produk'; })->implode(', ')
            : 'E-Produk';

        if ($this->status === 'verified') {
            return [
                'title' => 'Pembelian Berhasil! 🎉',
                'message' => "Pembayaran untuk \"$itemNames\" telah disetujui. Silakan cek koleksi Anda.",
                'status' => 'verified',
                'url' => "/my-e-products"
            ];
        } else {
            return [
                'title' => 'Pembelian Dibatalkan ❌',
                'message' => "Pesanan \"$itemNames\" dibatalkan.",
                'status' => 'rejected',
                'url' => '/e-products'
            ];
        }
    }
}
