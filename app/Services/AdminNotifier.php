<?php

namespace App\Services;

use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\PriceEnquiry;
use App\Models\User;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Notifications\DatabaseNotification as FilamentDatabaseNotification;
use Filament\Notifications\Notification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Log;

/**
 * The admin bell: alerts for every staff/admin user.
 * Each alert carries an "admin_key" so it can be cleared for the whole team once someone handles it.
 */
class AdminNotifier
{
    /** Clear alerts automatically when the underlying item is handled or removed. */
    public static function registerListeners(): void
    {
        PriceEnquiry::updated(function (PriceEnquiry $enquiry) {
            if ($enquiry->wasChanged('status') && $enquiry->status !== 'new') {
                self::resolve('enquiry:'.$enquiry->id);
            }
        });
        PriceEnquiry::deleted(fn (PriceEnquiry $enquiry) => self::forget('enquiry:'.$enquiry->id));

        Order::updated(function (Order $order) {
            if ($order->wasChanged('status') && $order->status !== 'awaiting_payment') {
                self::resolve('order:'.$order->id);
            }
        });
        Order::deleted(fn (Order $order) => self::forget('order:'.$order->id));

        ContactMessage::updated(function (ContactMessage $message) {
            if ($message->wasChanged('read_at') && $message->read_at) {
                self::resolve('message:'.$message->id);
            }
        });
        ContactMessage::deleted(fn (ContactMessage $message) => self::forget('message:'.$message->id));

        User::deleted(fn (User $user) => self::forget('wishlist:'.$user->id.':%'));
    }

    public static function priceEnquiry(PriceEnquiry $enquiry): void
    {
        self::safely(function () use ($enquiry) {
            $product = $enquiry->product_name.($enquiry->optionsLabel() ? ' ('.$enquiry->optionsLabel().')' : '');

            self::send('enquiry:'.$enquiry->id, Notification::make()
                ->title('New price enquiry from '.e($enquiry->name))
                ->body(e($product))
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->iconColor('warning')
                ->actions([
                    self::open('enquiry:'.$enquiry->id),
                    ...self::contactActions($enquiry->phone, $enquiry->email),
                ]));
        });
    }

    public static function order(Order $order): void
    {
        self::safely(function () use ($order) {
            $address = $order->shipping_address ?? [];
            $name = trim(($address['first_name'] ?? '').' '.($address['last_name'] ?? '')) ?: $order->email;

            self::send('order:'.$order->id, Notification::make()
                ->title('New order '.e($order->number))
                ->body(e($name.' · '.Money::format($order->total).' · awaiting payment'))
                ->icon('heroicon-o-shopping-bag')
                ->iconColor('success')
                ->actions([
                    self::open('order:'.$order->id),
                    ...self::contactActions($order->phone, $order->email),
                ]));
        });
    }

    public static function contactMessage(ContactMessage $message): void
    {
        self::safely(function () use ($message) {
            self::send('message:'.$message->id, Notification::make()
                ->title('New message from '.e($message->name))
                ->body(e($message->subject))
                ->icon('heroicon-o-envelope')
                ->iconColor('info')
                ->actions([
                    self::open('message:'.$message->id),
                    ...self::contactActions($message->phone, $message->email),
                ]));
        });
    }

    /**
     * One alert per customer per day, updated in place.
     * Adding an item brings it back as unread; removing one only corrects the text.
     */
    public static function wishlistChanged(User $customer, bool $added): void
    {
        if ($customer->role !== 'customer') {
            return;
        }

        self::safely(function () use ($customer, $added) {
            $dayStart = now(config('app.admin_timezone') ?: config('app.timezone'))->startOfDay();
            $key = 'wishlist:'.$customer->id.':'.$dayStart->format('Y-m-d');

            $items = $customer->wishlistItems()
                ->where('created_at', '>=', $dayStart->copy()->utc())
                ->with('product', 'colour')
                ->get();

            if ($items->isEmpty()) {
                self::forget($key);

                return;
            }

            $first = $items->first();
            $label = ($first->product?->name ?? 'A product').($first->colour ? ' ('.$first->colour->name.')' : '');
            $more = $items->count() > 1 ? ' +'.($items->count() - 1).' more' : '';
            $consent = $customer->marketing_opt_in ? 'Yes' : 'No';

            $message = self::message($key, Notification::make()
                ->title(e($customer->name).' saved '.$items->count().' '.str('item')->plural($items->count()).' today')
                ->body(e($label.$more.' · Newsletter consent: '.$consent))
                ->icon('heroicon-o-heart')
                ->iconColor('danger')
                ->actions([
                    self::open($key),
                    ...self::contactActions($customer->phone, $customer->email),
                ]));

            foreach (User::team()->get() as $member) {
                $existing = $member->notifications()->where('data->admin_key', $key)->first();

                if ($existing) {
                    $existing->forceFill(['data' => $message] + ($added ? ['read_at' => null, 'created_at' => now()] : []))->save();
                } elseif ($added) {
                    $member->notifyNow(new FilamentDatabaseNotification($message));
                }
            }
        });
    }

    /** Mark an alert as handled for the whole team (it stays in the list as read). */
    public static function resolve(string $key): void
    {
        self::safely(fn () => DatabaseNotification::where('data->admin_key', $key)
            ->whereNull('read_at')
            ->update(['read_at' => now()]));
    }

    /** Remove alerts whose item no longer exists. "%" in the key matches a group. */
    public static function forget(string $key): void
    {
        self::safely(fn () => DatabaseNotification::where('data->admin_key', 'like', $key)->delete());
    }

    private static function send(string $key, Notification $notification): void
    {
        $message = self::message($key, $notification);

        // notifyNow: the alert must appear even when no queue worker is running.
        foreach (User::team()->get() as $member) {
            $member->notifyNow(new FilamentDatabaseNotification($message));
        }
    }

    /** @return array<string, mixed> */
    private static function message(string $key, Notification $notification): array
    {
        return $notification->getDatabaseMessage() + ['admin_key' => $key];
    }

    /** Where an alert leads, or null if the key isn't a valid alert. */
    public static function urlFor(string $key): ?string
    {
        if (! preg_match('/^(enquiry|order|message|wishlist):(\d+)(?::\d{4}-\d{2}-\d{2})?$/D', $key, $m)) {
            return null;
        }
        if (($m[1] === 'wishlist') !== (substr_count($key, ':') === 2)) {
            return null;
        }

        return match ($m[1]) {
            'enquiry' => route('filament.admin.resources.price-enquiries.index', ['tableAction' => 'view', 'tableActionRecord' => $m[2]], false),
            'order' => route('filament.admin.resources.orders.view', ['record' => $m[2]], false),
            'message' => route('filament.admin.resources.contact-messages.index', ['tableAction' => 'view', 'tableActionRecord' => $m[2]], false),
            'wishlist' => route('filament.admin.resources.customers.edit', ['record' => $m[2], 'relation' => 1], false),
        };
    }

    /** Posts to the server so the alert is cleared before the page changes. */
    private static function open(string $key): Action
    {
        return Action::make('open')->label('Open')->button()
            ->url(route('filament.admin.alerts.open', ['key' => $key], false))
            ->postToUrl();
    }

    /** @return list<Action> */
    private static function contactActions(?string $phone, ?string $email): array
    {
        $actions = [];
        $dial = preg_replace('/[^\d+]/', '', (string) $phone);

        if (strlen($dial) >= 6) {
            $actions[] = Action::make('call')->label('Call')->link()->url('tel:'.$dial);

            // WhatsApp links need the country code, so local-format numbers don't get one.
            if (str_starts_with($dial, '+')) {
                $actions[] = Action::make('whatsapp')->label('WhatsApp')->link()
                    ->url('https://wa.me/'.ltrim($dial, '+'), shouldOpenInNewTab: true);
            }
        }

        if (filled($email)) {
            $actions[] = Action::make('email')->label('Email')->link()->url('mailto:'.rawurlencode($email));
        }

        return $actions;
    }

    /** An alert failing must never break an order, enquiry or wishlist save. */
    private static function safely(callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            Log::warning('Admin notification failed: '.$e->getMessage());
        }
    }
}
