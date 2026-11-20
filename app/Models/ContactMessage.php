<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    public const SUBJECTS = [
        'Order enquiry', 'Delivery', 'Returns & refunds', 'Product question', 'Sizing advice', 'Other',
    ];

    protected $fillable = ['name', 'email', 'phone', 'subject', 'order_number', 'message', 'ip', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}
