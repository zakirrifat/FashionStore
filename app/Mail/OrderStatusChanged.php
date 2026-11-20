<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** To the customer: payment received / shipped / cancelled. */
class OrderStatusChanged extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order, public string $event) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->event) {
            'paid' => 'Payment received for order '.$this->order->number,
            'shipped' => 'Your order '.$this->order->number.' is on its way',
            'cancelled' => 'Order '.$this->order->number.' has been cancelled',
            default => 'Update on order '.$this->order->number,
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.order-status', with: ['order' => $this->order->loadMissing('items'), 'event' => $this->event]);
    }
}
