<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** To the customer: order received + how payment works. */
class OrderPlaced extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Order '.$this->order->number.' received');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.order-placed', with: [
            'order' => $this->order->loadMissing('items'),
            'paymentInstructions' => Setting::get('payment_instructions'),
        ]);
    }
}
