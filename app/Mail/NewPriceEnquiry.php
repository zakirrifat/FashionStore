<?php

namespace App\Mail;

use App\Filament\Resources\PriceEnquiries\PriceEnquiryResource;
use App\Models\PriceEnquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** To the shop: a customer asked for the price of a product. */
class NewPriceEnquiry extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PriceEnquiry $enquiry) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Price request: '.$this->enquiry->product_name.' — '.$this->enquiry->name,
            // "Reply" in the mail app goes straight to the customer when they gave an email.
            replyTo: $this->enquiry->email ? [new Address($this->enquiry->email, $this->enquiry->name)] : [],
        );
    }

    public function content(): Content
    {
        $product = $this->enquiry->product;

        return new Content(markdown: 'mail.new-price-enquiry', with: [
            'enquiry' => $this->enquiry,
            'productUrl' => $product ? $product->url() : null,
            'adminUrl' => PriceEnquiryResource::getUrl('index', panel: 'admin'),
            'tel' => preg_replace('/[^\d+]/', '', $this->enquiry->phone),
        ]);
    }
}
