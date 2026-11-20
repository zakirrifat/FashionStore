<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** To the shop: a new order needs payment arranged. */
class NewOrderForShop extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New order '.$this->order->number.' — '.\App\Support\Money::format($this->order->total),
            replyTo: [new Address($this->order->email, $this->order->customerName())],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.new-order-for-shop', with: ['order' => $this->order->loadMissing('items')]);
    }
}
