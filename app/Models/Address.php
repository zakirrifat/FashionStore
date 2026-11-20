<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Intl\Countries;

class Address extends Model
{
    public const ZA_PROVINCES = [
        'Eastern Cape', 'Free State', 'Gauteng', 'KwaZulu-Natal', 'Limpopo',
        'Mpumalanga', 'North West', 'Northern Cape', 'Western Cape',
    ];

    protected $fillable = [
        'first_name', 'last_name', 'company', 'line1', 'line2', 'city',
        'province', 'postal_code', 'country', 'phone', 'is_default',
    ];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    protected static function booted(): void
    {
        // Keep exactly one default address per customer, wherever it is saved from.
        static::saved(function (Address $address) {
            if ($address->is_default) {
                static::where('user_id', $address->user_id)->whereKeyNot($address->id)
                    ->where('is_default', true)->update(['is_default' => false]);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** ISO code => English name, South Africa first. */
    public static function countries(): array
    {
        $all = Countries::getNames('en');

        return ['ZA' => $all['ZA']] + $all;
    }

    public function countryName(): string
    {
        return Countries::exists($this->country) ? Countries::getName($this->country, 'en') : $this->country;
    }

    /** Lines for display, empty parts removed. */
    public function lines(): array
    {
        return array_values(array_filter([
            trim($this->first_name.' '.$this->last_name),
            $this->company,
            $this->line1,
            $this->line2,
            trim($this->city.', '.$this->province, ', '),
            $this->postal_code,
            $this->countryName(),
            $this->phone,
        ]));
    }
}
