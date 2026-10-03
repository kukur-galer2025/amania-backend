<?php

namespace App\Jobs;

use App\Models\EProduct;
use App\Models\User;
use App\Mail\EProductBroadcastMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendEProductBroadcastJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $user;
    public $product;
    public $productId;

    public function __construct(User $user, EProduct $product, $productId)
    {
        $this->user = $user;
        $this->product = $product;
        $this->productId = $productId;
    }

    public function handle(): void
    {
        try {
            Mail::to($this->user->email)->send(new EProductBroadcastMail($this->product));
        } catch (\Exception $e) {
            // Abaikan jika error (misal email salah format) agar queue lanjut ke email berikutnya
        }

        // Increment progress di Cache
        $cacheKey = "broadcast_eproduct_{$this->productId}_progress";
        $newProgress = \Illuminate\Support\Facades\Cache::increment($cacheKey);

        // Jika semua email sudah terkirim, lepas Lock agar bisa broadcast lagi nanti
        $total = \Illuminate\Support\Facades\Cache::get("broadcast_eproduct_{$this->productId}_total", 0);
        if ($total > 0 && $newProgress >= $total) {
            \Illuminate\Support\Facades\Cache::forget("broadcast_eproduct_{$this->productId}_lock");
        }
    }
}
