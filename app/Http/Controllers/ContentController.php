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
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'subject' => ['required', Rule::in(ContactMessage::SUBJECTS)],
            'order_number' => ['nullable', 'string', 'max:50'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $message = ContactMessage::create($data + ['ip' => $request->ip()]);

        \App\Services\AdminNotifier::contactMessage($message);

        // Notify the shop. A mail failure must not lose the message — it's already saved.
        try {
            Mail::raw(
                "New message from {$message->name} <{$message->email}>\n"
                ."Subject: {$message->subject}\n"
                .($message->order_number ? "Order: {$message->order_number}\n" : '')
                .($message->phone ? "Phone: {$message->phone}\n" : '')
                ."\n{$message->message}\n\nView in admin: ".url('/admin/contact-messages'),
                fn ($mail) => $mail->to(Setting::get('contact_email'))
                    ->replyTo($message->email, $message->name)
                    ->subject('Contact form: '.$message->subject)
            );
        } catch (\Throwable $e) {
            Log::warning('Contact form email failed: '.$e->getMessage());
        }

        return redirect()->route('shop.contact')->with('sent', true);
    }
}
