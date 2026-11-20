# Fashion E-commerce Store — Requirements (reference: acnestudios.com)

_Draft v0.1 · 2026-11-20 · Status: waiting for client answers (see section 9)_

> Note: we build a store that **works and feels like** the reference site (minimal, image-led luxury fashion).
> We use **your own brand, logo, photos and product text** — not Acne Studios' content, which is copyrighted.

---

## 1. What the reference site has (findings)

**Platform they use:** Salesforce Commerce Cloud (enterprise, very expensive). We can get the same features for far less.

**Look & feel:** black/white minimal design, large full-width photos/video, small uppercase text, thin top bar, lots of white space.

**Header:** WOMAN · MAN · BAGS · SCARVES · GIFTS (mega-menus) · Search · Help · Account · Wishlist · Bag (count).

**Mega menu structure (example — Woman):**
- Featured: New Arrivals, Gifts for Her, Winter Dressing, Seasonal Denim, Scarf Personalisation
- Clothing: Jeans, Outerwear, Sweatshirts, Knitwear, Trousers, T-shirts, Shirts, Dresses, Suit Jackets, Shorts, Skirts
- Shoes & Accessories: Scarves, Bags, Hats, Shoes, Small Leather Goods, Jewellery, Eyewear, Belts, Objects
- Collections: SS27, FW26, Denim, Scarves
- Bags and Scarves have their own lines (Camero, Musubi… / Cashmere, Wool, Silk…)

**Home page:** hero banners (Gift Guide, New Arrivals), "Discover more" tiles, a News/Editorial section (exhibitions, collaborations, collections, magazine).

**Category page:** title, sub-category chips (Straight, Loose, Baggy…), item count (e.g. "72 items"), FILTER panel, product grid, image swap on hover, "+ N colours", "Load more".

**Product page:**
- Image gallery (≈10+ photos per product), breadcrumb
- Name, price, short tagline ("Low waist, baggy leg")
- Colour selector (each colour is its own product with own photos — "+15 colours")
- Size selector (XXXS–XXL) + second option (Length: Regular / Petit)
- "Out of stock? Get notified" (back-in-stock email)
- Add to bag · Wishlist · Size guide
- Free delivery / complimentary gift-wrapping messages
- Description, details list, model size info, composition, care, style ID
- Recommended products
- SEO structured data (Google product rich results)

**Customer account:** register, sign in, overview, my information, orders & returns, wishlist, log out.

**Services / footer pages:** Contact us (phone, email), Order status (guest lookup), Register a return, FAQs, Shipping & returns, Size guide, Find a store (store locator), Pick up in store, Personalised items, Gift wrapping, Gift cards, Product care, About, Careers, Sustainability, Legal & Privacy, Cookie settings, social links.

**Other:** multi-country / multi-currency (country in URL `/emea/za/en/`), cookie consent banner (OneTrust), scarf personalisation (embroidery), gift cards.

---

## 2. Storefront requirements (customer side)

| # | Feature | Priority |
|---|---|---|
| S1 | Responsive minimal design (mobile first) | Must |
| S2 | Header with mega menu, search, account, wishlist, bag | Must |
| S3 | Home page built from admin-editable blocks (hero image/video, tiles, editorial) | Must |
| S4 | Category pages: grid, filters (size, colour, price, fit, material), sort, load more | Must |
| S5 | Product page: gallery + zoom, colour & size variants, size guide, stock status | Must |
| S6 | Cart (slide-out bag) + checkout (guest and logged-in) | Must |
| S7 | Payments (card + local methods) | Must |
| S8 | Customer accounts: register/login/forgot password, addresses, order history | Must |
| S9 | Wishlist | Must |
| S10 | Search with suggestions | Must |
| S11 | Order emails: confirmation, shipped (tracking), refund | Must |
| S12 | Content pages: About, FAQ, Shipping & Returns, Size guide, Legal, Privacy, Contact form | Must |
| S13 | Cookie consent banner + privacy compliance (POPIA / GDPR) | Must |
| S14 | SEO: clean URLs, meta tags, sitemap, product structured data | Must |
| S15 | Returns request form + guest order-status lookup | Should |
| S16 | Back-in-stock notification | Should |
| S17 | Recommended / "complete the look" products | Should |
| S18 | Newsletter signup | Should |
| S19 | Editorial / News / Lookbook section | Should |
| S20 | Gift wrapping option at checkout, gift cards, discount codes | Could |
| S21 | Multi-currency / multi-language / country selector | Could |
| S22 | Store locator, click & collect | Could |
| S23 | Personalisation (e.g. embroidery text on product) | Could |

## 3. Admin panel requirements (back office)

**Login & users**
- Secure admin login (email + password, 2-factor), password reset
- Staff roles: Owner/Admin, Product manager, Order/Customer service, Content editor
- Activity log (who changed what)

**Products**
- Create/edit/delete products: name, slug, description, details, composition, care, model info, tags, SEO fields
- Variants: colour × size × length, each with SKU, price, sale price, stock, barcode
- Upload many images per colour (drag & drop, reorder, auto-resize/optimise), video support
- Categories & sub-categories (tree), collections (SS27, Gift Guide…), assign product to many
- Draft / publish / schedule, bulk import/export (CSV), bulk price & stock edit
- Size guides per category
- Low-stock alerts

**Orders**
- Order list with filters/status (paid, processing, shipped, delivered, cancelled, refunded)
- Order detail, print invoice/packing slip, add tracking number (emails customer)
- Refunds & returns handling, notes

**Customers**
- Customer list, order history, addresses, export; newsletter subscribers

**Content (CMS)**
- Home page builder (banners, tiles, product carousels), menu editor
- Static pages & FAQ editor, editorial/news posts
- Announcement bar ("Free delivery…")

**Marketing & settings**
- Discount codes, free-shipping rules, gift cards
- Shipping zones & rates, tax/VAT settings, currencies
- Payment gateway settings, email templates
- Reports: sales, best sellers, stock, abandoned carts; Google Analytics / Meta Pixel

## 4. Integrations needed

| Area | Options (South Africa examples) |
|---|---|
| Payments | Paystack, PayFast, Peach Payments, Yoco, Stripe (international) |
| Shipping/couriers | The Courier Guy, Bob Go, Aramex, DHL Express (international) |
| Email | Resend / Postmark / SendGrid (transactional); Klaviyo / Mailchimp (marketing) |
| Image storage & CDN | Cloudinary, Cloudflare Images or S3 + CloudFront |
| Search | Built-in DB search → Algolia / Meilisearch when catalogue grows |
| Analytics | Google Analytics 4, Meta Pixel, Search Console |

## 5. Technology options

| Option | What it is | Pros | Cons | Rough cost |
|---|---|---|---|---|
| **A. Shopify + custom theme** | Hosted platform; admin, payments, orders all included | Fastest, reliable, best admin, low maintenance | Monthly fee + app fees; less freedom | ~R600–R7,000/mo + theme build |
| **B. Headless: Next.js + Medusa (or Saleor)** | Custom storefront + open-source commerce engine with admin | Full control, no platform fees, pixel-perfect design | More dev work, you host & maintain | Hosting ~R500–R3,000/mo + bigger build |
| **C. WordPress + WooCommerce** | Common CMS + shop plugin | Cheap, many plugins | Slower, plugin maintenance, harder to look this premium | Hosting ~R200–R1,000/mo |

**Decision: PHP (latest) — chosen by client.** Proposed stack:

| Layer | Choice |
|---|---|
| Language | PHP 8.4+ (latest stable) |
| Framework | Laravel 12 |
| Admin panel | Filament 4 (admin login, roles, product/order/customer/content screens) |
| Storefront | Laravel Blade + Livewire + Alpine.js + Tailwind CSS (fast, SEO-friendly, no separate JS app) |
| Database | MySQL 8 / MariaDB |
| Cache & queues | Redis + Laravel Horizon (emails, image processing) |
| Images | Spatie Media Library → auto-resize to WebP/AVIF, stored on S3/Cloudflare R2 + CDN |
| Search | Laravel Scout + Meilisearch (fits 50–500 products) |
| Auth | Laravel Fortify / Breeze (customers), separate admin guard + 2FA |
| Hosting | VPS (e.g. Hetzner/DigitalOcean/AWS Cape Town) via Laravel Forge or Ploi, Cloudflare in front |

Alternative to consider: **Bagisto** (open-source Laravel e-commerce with admin already built) — faster start, but harder to make look exactly like the reference design.

## 6. Non-functional requirements
- Fast: Lighthouse ≥ 90, images in WebP/AVIF, lazy loading, CDN
- Secure: HTTPS, PCI handled by the payment gateway (no card data stored), admin 2FA, daily backups
- Accessible (WCAG 2.1 AA basics: keyboard nav, alt text, contrast)
- Legal: POPIA (SA) / GDPR if selling to EU, Terms, Privacy, Returns policy, Cookie policy

## 7. What you (the business) must provide
- Brand name, logo, fonts/colours preference, domain name
- Product photos (consistent studio shots, several angles per colour) + campaign images/video
- Product data: names, descriptions, sizes, colours, prices, stock, SKUs
- Policies: shipping, returns, privacy, terms
- Business details: company registration, VAT number, bank account for payment gateway
- Contact info, social media links

## 8. Suggested phases
1. **Phase 1 – Launch (MVP):** S1–S14 + full admin products/orders/customers/content.
2. **Phase 2:** returns portal, back-in-stock, recommendations, newsletter, editorial, discounts, gift cards.
3. **Phase 3:** multi-currency/language, store locator, click & collect, personalisation, mobile app.

## 9. Decisions so far
- **Platform:** PHP latest → Laravel 12 + Filament admin (custom build)
- **Market:** South Africa + international → multi-currency (ZAR + USD/EUR/GBP), international shipping (DHL/Aramex), VAT 15% in SA, duties messaging for overseas
- **Catalogue size:** 50–500 styles → needs filters, search, CSV bulk import
- **Phase 1 extras:** discount codes + gift cards (and gift wrapping) move into Phase 1

- **Cards:** Visa (+ Mastercard) must be accepted → needs a gateway; proposed **Paystack** (SA + international cards, ZAR/USD), code built gateway-agnostic so PayFast/Peach can be added
- **Design:** same minimal style as reference, with client's own brand, logo, photos
- **Next step:** start the Laravel build (products, categories, admin login first)

## 10. Still open
- Confirm payment gateway account (Paystack recommended)
- Hosting / domain ready?
- Product photos & brand assets ready?
- Languages: English only?
- Who builds and maintains (in-house dev, me, agency)? Timeline and budget
