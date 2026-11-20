<?php

return [
    'currency' => 'ZAR',

    /*
     * Default values for Admin › Settings › Store settings.
     * Once saved in the admin, the database values are used instead.
     * Placeholder text — replace with your real details and policies.
     */
    'defaults' => [
        'contact_email' => 'hello@example.com',
        'contact_phone' => '',
        'contact_hours' => 'Monday to Friday, 9am – 5pm SAST',
        'contact_address' => '',

        'announcement_enabled' => false,
        'announcement_text' => '',
        'announcement_link' => '',

        'country_label' => 'South Africa (ZAR)',
        'low_stock_threshold' => 3,

        // Checkout
        'order_prefix' => 'FS',
        'vat_rate' => 15,
        'vat_free_exports' => true,   // orders shipped outside South Africa pay prices without VAT
        'gift_wrap_price' => 0,       // cents; 0 = free
        'payment_instructions' => '', // shown after ordering and in the confirmation email (e.g. bank details)
        'seo_description' => 'Shop the official online store.',

        // Short lines under the "Add to bag" button.
        'product_notes' => [
            'Free delivery in South Africa on orders over R 1 500',
            'Complimentary gift wrapping at checkout',
        ],
        'shipping_returns' => 'Delivery in 2–5 working days within South Africa. International shipping available. Free returns within 14 days.',

        'social_instagram' => '',
        'social_facebook' => '',
        'social_tiktok' => '',
        'social_x' => '',
        'social_youtube' => '',
        'social_pinterest' => '',
    ],
];
