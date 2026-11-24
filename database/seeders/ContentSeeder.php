<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Starter pages and FAQs. All text is PLACEHOLDER — edit it in Admin › Content.
 * Only creates missing items, so it never overwrites your edits.
 */
class ContentSeeder extends Seeder
{
    private const NOTE = '<p><em>Placeholder text — replace this in Admin › Content › Pages.</em></p>';

    public function run(): void
    {
        $pages = [
            ['shipping-returns', 'Shipping & Returns', 'client_services', 1, self::NOTE.'
                <h2>Delivery</h2>
                <ul><li>South Africa: 2–5 working days.</li><li>International: 5–10 working days. Duties and taxes may apply on arrival.</li></ul>
                <h2>Returns</h2>
                <p>You can return unworn items with their original tags within 14 days of delivery.</p>
                <h2>Refunds</h2>
                <p>Refunds are made to the original payment method within 10 working days of us receiving your return.</p>'],
            ['size-guide', 'Size Guide', 'client_services', 2, self::NOTE.'
                <p>Example measurements in centimetres. Replace with your own size chart.</p>
                <table><tbody>
                <tr><th>Size</th><th>XS</th><th>S</th><th>M</th><th>L</th><th>XL</th></tr>
                <tr><td>Chest</td><td>84</td><td>90</td><td>96</td><td>102</td><td>108</td></tr>
                <tr><td>Waist</td><td>68</td><td>74</td><td>80</td><td>86</td><td>92</td></tr>
                <tr><td>Hip</td><td>90</td><td>96</td><td>102</td><td>108</td><td>114</td></tr>
                </tbody></table>'],
            ['product-care', 'Product Care', 'client_services', 3, self::NOTE.'
                <p>Follow the care label on each garment. Wash denim inside out on cold, and dry flat.</p>'],
            ['about', 'About', 'company', 1, self::NOTE.'
                <p>Tell your brand story here: where you started, what you make and what you stand for.</p>'],
            ['careers', 'Careers', 'company', 2, self::NOTE.'
                <p>We’re not hiring right now. Send your CV to the email on our contact page.</p>'],
            ['terms', 'Terms & Conditions', 'legal', 1, self::NOTE.'
                <p>Your terms of sale go here. Have them reviewed by a lawyer before launch.</p>'],
            ['privacy', 'Privacy Policy', 'legal', 2, self::NOTE.'
                <p>Explain what personal information you collect, why, and how customers can access or delete it (POPIA / GDPR).</p>'],
            ['cookies', 'Cookie Policy', 'legal', 3, self::NOTE.'
                <p>Explain which cookies the site uses and how visitors can change their choice.</p>'],
        ];

        foreach ($pages as [$slug, $title, $group, $sort, $body]) {
            Page::firstOrCreate(['slug' => $slug], [
                'title' => $title,
                'footer_group' => $group,
                'sort_order' => $sort,
                'body' => preg_replace('/\s*\n\s*/', '', $body),
                'team_section' => $slug === Page::ABOUT_SLUG ? Page::TEAM_SECTION_DEFAULTS : null,
            ]);
        }

        if (Faq::exists()) {
            return;
        }

        $faqs = [
            ['Orders', 'How do I track my order?', 'Once your order ships we email you a tracking link. You can also check it under Order status.'],
            ['Orders', 'Can I change or cancel my order?', 'Contact us as soon as possible. We can change or cancel orders that have not shipped yet.'],
            ['Delivery', 'Do you ship internationally?', 'Yes. Delivery times and costs are shown at checkout. Duties and taxes may apply on arrival.'],
            ['Delivery', 'How long does delivery take?', 'South Africa: 2–5 working days. International: 5–10 working days.'],
            ['Returns & refunds', 'What is your returns policy?', 'Unworn items with original tags can be returned within 14 days of delivery.'],
            ['Payments', 'Which payment methods do you accept?', 'Visa and Mastercard credit and debit cards.'],
            ['Products & care', 'How do I find my size?', 'Use the Size guide link on each product page, or contact us for advice.'],
        ];

        foreach ($faqs as $i => [$topic, $q, $a]) {
            Faq::create(['topic' => $topic, 'question' => $q, 'answer' => "<p>{$a}</p>", 'sort_order' => $i]);
        }
    }
}
