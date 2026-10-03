<?php

namespace App\Mail;

use App\Models\EProduct;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EProductBroadcastMail extends Mailable
{
    use Queueable, SerializesModels;

    public $product;

    public function __construct(EProduct $product)
    {
        $this->product = $product;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Produk Digital Baru: ' . $this->product->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.eproduct.broadcast',
        );
    }
}
