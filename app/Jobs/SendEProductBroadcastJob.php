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
    public $logId;

    public function __construct(User $user, EProduct $product, $logId)
    {
        $this->user = $user;
        $this->product = $product;
        $this->logId = $logId;
    }

    public function handle(): void
    {
        $log = \App\Models\EproductBroadcastLog::find($this->logId);
        if (!$log) return;

        try {
            Mail::to($this->user->email)->send(new EProductBroadcastMail($this->product));
            
            $log->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        } catch (\Exception $e) {
            $log->update([
                'status' => 'failed',
            ]);
        }
    }
}
