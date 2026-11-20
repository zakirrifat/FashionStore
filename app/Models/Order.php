<?php

namespace App\Models;

use App\Models\Concerns\LogsAdminActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Symfony\Component\Intl\Countries;

class Order extends Model
{
    use LogsAdminActivity;

    /** Order life cycle. Payment is by bank transfer arranged by the shop. */
    public const STATUSES = [
        'awaiting_payment' => 'Awaiting payment',
        'paid' => 'Paid',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
    ];

    public const STATUS_COLOURS = [
        'awaiting_payment' => 'warning',
        'paid' => 'info',
        'shipped' => 'primary',
        'delivered' => 'success',
        'cancelled' => 'gray',
    ];

    protected $fillable = [
        'number', 'access_token', 'user_id', 'status', 'email', 'phone', 'shipping_address', 'shipping_method',
        'currency', 'subtotal', 'discount_total', 'shipping_total', 'gift_wrap_total', 'total', 'vat_total',
        'vat_exempt', 'discount_code', 'gift_wrap', 'gift_message', 'customer_note', 'admin_note',
        'courier', 'tracking_number', 'tracking_url', 'paid_at', 'shipped_at', 'delivered_at', 'cancelled_at', 'ip',
    ];

    protected $hidden = ['access_token'];

    protected function casts(): array
    {
        return [
            'shipping_address' => 'array',
            'vat_exempt' => 'boolean',
            'gift_wrap' => 'boolean',
            'paid_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** Activity log: only what the shop changes — never the customer's address or the access token. */
    public function getActivitylogOptions(): \Spatie\Activitylog\Support\LogOptions
    {
        return \Spatie\Activitylog\Support\LogOptions::defaults()
            ->logOnly(['number', 'status', 'courier', 'tracking_number', 'tracking_url', 'admin_note'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function customerName(): string
    {
        return trim(($this->shipping_address['first_name'] ?? '').' '.($this->shipping_address['last_name'] ?? ''));
    }

    /** Address lines for display / emails. */
    public function addressLines(): array
    {
        $a = $this->shipping_address ?? [];
        $country = $a['country'] ?? '';

        return array_values(array_filter([
            $this->customerName(),
            $a['company'] ?? null,
            $a['line1'] ?? null,
            $a['line2'] ?? null,
            trim(($a['city'] ?? '').', '.($a['province'] ?? ''), ', '),
            $a['postal_code'] ?? null,
            Countries::exists($country) ? Countries::getName($country, 'en') : $country,
        ]));
    }

    /** Link a guest can use to see their order (no account needed). */
    public function confirmationUrl(): string
    {
        return route('order.show', ['number' => $this->number, 'token' => $this->access_token]);
    }

    public function itemCount(): int
    {
        return (int) $this->items->sum('quantity');
    }
}
