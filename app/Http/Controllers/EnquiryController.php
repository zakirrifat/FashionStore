<?php

namespace App\Http\Controllers;

use App\Mail\NewPriceEnquiry;
use App\Models\PriceEnquiry;
use App\Models\Product;
use App\Models\ProductColour;
use App\Models\ProductVariant;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/** "Ask for price": a customer leaves their details so the shop can contact them. */
class EnquiryController extends Controller
{
    public const THANKS = 'Thank you! We’ve received your request and will contact you soon with the price.';

    public function store(Request $request): JsonResponse
    {
        // Simple bot trap: real visitors leave this hidden field empty.
        if (filled($request->input('website'))) {
            return response()->json(['ok' => true, 'message' => self::THANKS]);
        }

        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'colour_id' => ['nullable', 'integer'],
            'variant_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+()\-\s.]{6,30}$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'message' => ['nullable', 'string', 'max:2000'],
        ], [
            'phone.regex' => 'Please enter a valid phone number.',
        ]);

        $product = Product::visible()->findOrFail($data['product_id']);

        // Colour and size are optional; ignore any that don't belong to this product.
        $colour = isset($data['colour_id'])
            ? ProductColour::where('product_id', $product->id)->find($data['colour_id'])
            : null;
        $variant = isset($data['variant_id'])
            ? ProductVariant::whereHas('colour', fn ($q) => $q->where('product_id', $product->id))->find($data['variant_id'])
            : null;
        $colour ??= $variant?->colour;

        $enquiry = PriceEnquiry::create([
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'product_name' => $product->name,
            'colour' => $colour?->name,
            'size' => $variant ? trim($variant->size.' '.$variant->length) : null,
            'sku' => $variant?->sku ?: ($colour ? $product->style_code.'-'.$colour->code : $product->style_code),
            'name' => trim($data['name']),
            'phone' => trim($data['phone']),
            'email' => $data['email'] ?? null,
            'message' => $data['message'] ?? null,
            'ip' => $request->ip(),
        ]);

        \App\Services\AdminNotifier::priceEnquiry($enquiry);

        // Tell the shop. A mail failure must not lose the request — it's already saved and shown in admin.
        // Sent after the reply reaches the visitor, so they don't wait for the mail server.
        $to = Setting::get('contact_email');
        if (filled($to)) {
            defer(function () use ($to, $enquiry) {
                try {
                    Mail::to($to)->send(new NewPriceEnquiry($enquiry));
                } catch (\Throwable $e) {
                    Log::warning('Price enquiry email failed: '.$e->getMessage());
                }
            });
        }

        return response()->json(['ok' => true, 'message' => self::THANKS]);
    }
}
