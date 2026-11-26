<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContentController extends Controller
{
    public function page(string $slug): View
    {
        $page = Page::active()->where('slug', $slug)->firstOrFail();

        return view('shop.page', ['page' => $page]);
    }

    public function faq(): View
    {
        $faqs = Faq::where('is_active', true)->orderBy('sort_order')->get()
            ->groupBy('topic')
            ->sortBy(fn ($items, $topic) => array_search($topic, Faq::TOPICS) === false ? 99 : array_search($topic, Faq::TOPICS));

        return view('shop.faq', ['faqs' => $faqs]);
    }

    public function contact(): View
    {
        return view('shop.contact', ['subjects' => ContactMessage::SUBJECTS]);
    }

    public function sendContact(Request $request): RedirectResponse
    {
        // Simple bot trap: real visitors leave this hidden field empty.
        if (filled($request->input('website'))) {
            return back()->with('sent', true);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150'],
            // With country code, so the shop can call or WhatsApp back: + then 7–15 digits.
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+[\d\s()-]+$/', function ($attr, $value, $fail) {
                $digits = strlen(preg_replace('/\D/', '', (string) $value));
                if ($digits < 7 || $digits > 15) {
                    $fail('Please enter a valid phone number with country code, e.g. +27 82 123 4567.');
                }
            }],
            'subject' => ['required', Rule::in(ContactMessage::SUBJECTS)],
            'order_number' => ['nullable', 'string', 'max:50'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ], [
            'phone.required' => 'Please enter your phone number so we can get back to you.',
            'phone.regex' => 'Please start with + and your country code, e.g. +27 82 123 4567.',
        ]);

        $message = ContactMessage::create($data + ['ip' => $request->ip()]);

        \App\Services\AdminNotifier::contactMessage($message);

        // Notify the shop. A mail failure must not lose the message — it's already saved.
        // Sent after the page reaches the visitor, so they don't wait for the mail server.
        defer(function () use ($message) {
            try {
                Mail::raw(
                    "New message from {$message->name}\n"
                    ."Phone: {$message->phone}\n"
                    .($message->email ? "Email: {$message->email}\n" : '')
                    ."Subject: {$message->subject}\n"
                    .($message->order_number ? "Order: {$message->order_number}\n" : '')
                    ."\n{$message->message}\n\nView in admin: ".url('/admin/contact-messages'),
                    function ($mail) use ($message) {
                        $mail->to(Setting::get('contact_email'))->subject('Contact form: '.$message->subject);
                        if ($message->email) {
                            $mail->replyTo($message->email, $message->name);
                        }
                    }
                );
            } catch (\Throwable $e) {
                Log::warning('Contact form email failed: '.$e->getMessage());
            }
        });

        return redirect()->route('shop.contact')->with('sent', [
            'name' => strtok(trim($message->name), ' '),
            'email' => $message->email,
            'phone' => $message->phone,
        ]);
    }
}
