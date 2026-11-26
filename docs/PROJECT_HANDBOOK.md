# OMR TEX FASHIONS — Project Handbook

> **Purpose of this file:** a complete record of what has been built, how it works, the decisions made and what is left,
> so that work can continue in a new chat (paste or attach this file) or by another developer.
>
> Last updated: **21 November 2026** · Status: steps 1–6 + design done, step 7 partly done, step 8 (going live) waiting for hosting/domain.

---

## Contents
1. [Summary](#1-summary)
2. [Decisions made by the owner](#2-decisions-made-by-the-owner)
3. [Tech stack](#3-tech-stack)
4. [Running it on this PC](#4-running-it-on-this-pc)
5. [Folder map](#5-folder-map)
6. [Database](#6-database)
7. [Customer website (storefront)](#7-customer-website-storefront)
8. [Admin panel](#8-admin-panel)
9. [Business rules](#9-business-rules)
10. [Design system & motion](#10-design-system--motion)
11. [Settings & configuration](#11-settings--configuration)
12. [Tests](#12-tests)
13. [Git status](#13-git-status)
14. [What is left (roadmap)](#14-what-is-left-roadmap)
15. [Going live checklist (step 8)](#15-going-live-checklist-step-8)
16. [Gotchas learned while building](#16-gotchas-learned-while-building)
17. [How to continue in a new chat](#17-how-to-continue-in-a-new-chat)

---

## 1. Summary

An international-level fashion e-commerce site **inspired by** the layout and feel of acnestudios.com
(minimal, image-led, luxury) — **with the owner's own brand, photos and text** (no Acne Studios content is copied).

- **Customer website:** home page builder, menus, categories, search, product pages, wishlist, accounts,
  bag and checkout, order tracking pages, content pages, FAQ, contact form, animated luxury design.
- **Admin panel** (`/admin`): products (colours, photos, sizes, stock), categories, collections, size guides,
  CSV import/export, orders, customers, team, discount codes, delivery options, home page editor,
  pages, FAQs, messages, store settings, dashboard, two-step sign-in, activity log.
- **Payments:** no online payment for now. Orders are placed, then the shop contacts the customer and is paid
  by **bank transfer**; staff mark orders as paid in the admin.
- **Market:** South Africa (ZAR) + international shipping.

Original requirements and the analysis of the reference site: [`docs/REQUIREMENTS.md`](REQUIREMENTS.md)
(a duplicate copy also sits at the project root as `REQUIREMENTS.md` — one can be deleted).

---

## 2. Decisions made by the owner

| Topic | Decision |
|---|---|
| Platform | PHP (latest) → **Laravel 12 + Filament 4** admin, custom build |
| Market | **South Africa + international** |
| Catalogue size | 50–500 styles |
| Phase 1 extras | Discount codes (+ gift wrapping). Gift cards **not built** (don't suit bank-transfer payment) |
| Payment | **No online payment yet** — order first, staff arrange **bank transfer**, then mark paid |
| VAT | Prices include **15% SA VAT**. Orders shipped **outside SA pay prices without VAT** (zero-rated export). Confirm with accountant |
| Delivery | Placeholder rates (editable): SA Standard R100 (free ≥ R1 500), SA Express R180, International R650 |
| Guest checkout | **Allowed** |
| Design | Same minimal style as reference, **own brand**; free fonts **Fraunces + Inter Tight**; **warm** off-white palette |
| Step 5 (cookie banner, newsletter, currencies, SEO extras) | **Skipped for now** |
| Step 8 (hosting/domain) | **Later**, after hosting and domain details are available |
| Product range | **Clothing only.** Shoes & Accessories, Bags and Scarves categories were removed (21 Nov 2026) and are not in the seeder |
| Admin timezone | Bangladesh (`ADMIN_TIMEZONE=Asia/Dhaka` locally; default `Africa/Johannesburg`) |
| **Catalogue mode** (22 Nov 2026) | **No prices in the shop, for all products.** Each product has an **"Ask for price"** button (over the photo on hover, on the product page and in the wishlist). Customers leave **name + phone (required), email + message (optional)**; the request is saved in **Admin › People › Price enquiries** (New / Contacted / Closed + internal note) and emailed to the Store settings contact email. **Bag, checkout, customer orders/addresses and admin Sales pages are switched off** (`SHOP_CHECKOUT_ENABLED=false`, see `config/shop.php`); the code is kept and still tested. No WhatsApp button. Product price is optional in admin (internal records only) |

---

## 3. Tech stack

| Layer | Choice (installed version) |
|---|---|
| Language | PHP **8.5** (via Laravel Herd on Windows) |
| Framework | **Laravel 12.73** |
| Admin | **Filament 4.16** (Livewire 3.8) |
| Storefront | Blade + **Alpine.js 3** (+ `@alpinejs/collapse`) + **Tailwind CSS 4** (Vite 6) |
| Motion | **GSAP 3** + ScrollTrigger, **Lenis** smooth scroll (desktop), browser **View Transitions** |
| Fonts | `@fontsource-variable/fraunces`, `@fontsource-variable/inter-tight` (self-hosted, no Google requests) |
| Images | **Intervention Image 3** (GD) → resized **WebP** on demand |
| Countries | `symfony/intl` |
| Activity log | `spatie/laravel-activitylog` **v5** |
| Two-step sign-in | Filament app authentication + `pragmarx/google2fa-qrcode`, `bacon/bacon-qr-code` |
| Database | **SQLite** locally (`database/database.sqlite`) → **MySQL 8** on the live server |
| Sessions / cache | `file` locally (SQLite locking made parallel admin requests slow) |
| Mail | `log` locally (emails are written to `storage/logs/laravel.log`) — real service needed for live |

---

## 4. Running it on this PC

- Project folder: `E:\FashionStore` · Local URL: **http://fashion-store.test** · Admin: **http://fashion-store.test/admin**
- Served by **Laravel Herd** (`herd link fashion-store` was run in the project folder).
- First admin login: email `admin@fashion-store.test`; the password is `ADMIN_PASSWORD` in `.env`
  (change it in Admin › Profile). **Never commit `.env`.**

### Fresh setup (new PC / after cloning)
```bash
composer install
npm install
cp .env.example .env          # then set ADMIN_EMAIL / ADMIN_PASSWORD etc.
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed     # admin user, category tree, starter pages/FAQs, delivery options
php artisan storage:link
npm run build
herd link fashion-store        # or: php artisan serve
php artisan view:cache && php artisan filament:optimize   # makes first page loads fast on Windows
```

### Everyday commands
| Task | Command |
|---|---|
| Rebuild CSS/JS after changes | `npm run build` (or `npm run dev` while editing) |
| Run all tests | `php artisan test` |
| Pre-make resized images | `php artisan images:warm` |
| Clear caches | `php artisan optimize:clear` and `php artisan filament:optimize-clear` |
| Pre-build templates (Windows is slow on first render) | `php artisan view:cache` + `php artisan filament:optimize` |

### Local machine changes made
- Herd PHP `php.ini` (`C:\Users\Miaki\.config\herd\bin\php85\php.ini`): `upload_max_filesize=64M`, `post_max_size=64M`,
  `memory_limit=512M` (backup: `php.ini.bak-before-uploads`). **The live server needs the same upload limits.**
- `config/livewire.php`: temporary uploads up to 50 MB (hero videos).
- Git identity for this repo only: `git config user.name/user.email` set to the owner's GitHub account.

### Renaming / moving the project folder
Nothing in the code depends on the folder name. After renaming: `herd unlink fashion-store` (before) →
rename → in the new folder `herd link fashion-store`, `rmdir public\storage`, `php artisan storage:link`,
`php artisan optimize:clear`, `php artisan filament:optimize-clear`, `php artisan view:cache`.

### Viewing on another device
`fashion-store.test` only works on this PC. Options: same-Wi-Fi access via the PC's IP (needs photo URLs made
relative first — `public` disk URL is built from `APP_URL`), `herd share` (temporary public link — turn off
`APP_DEBUG` and require 2FA while sharing), or real hosting (step 8).

---

## 5. Folder map

```
app/
  Console/Commands/WarmImages.php        images:warm
  Filament/
    Pages/ManageSettings.php             Settings › Store settings
    Pages/ManageHomePage.php             Content › Home page (block builder)
    Resources/                           Products, Categories, Collections, SizeGuides, Orders, ShippingMethods,
                                         DiscountCodes, Customers (+Addresses/Wishlist tabs), Users (Team),
                                         Pages, Faqs, ContactMessages, ActivityLogs
    Widgets/                             StoreStats, LowStock, MostWishlisted, NewCustomers (dashboard)
  Http/Controllers/
    ShopController.php                   home, category, collection, search, suggest, product
    ContentController.php                pages, FAQ, contact
    CartController.php                   bag page, drawer, add/update/remove, discount codes
    CheckoutController.php               checkout, live quote, place order, confirmation
    AccountController.php                overview, orders, profile, password, addresses
    WishlistController.php, ImageController.php (resizer), Auth/AuthController, Auth/PasswordResetController
  Mail/                                  OrderPlaced, NewOrderForShop, OrderStatusChanged
  Models/                                Product, ProductColour, ProductVariant, ProductImage, Category, Collection,
                                         SizeGuide, Page, Faq, ContactMessage, Setting, User, Address, WishlistItem,
                                         Cart, CartItem, Order, OrderItem, ShippingMethod, DiscountCode
    Concerns/LogsAdminActivity.php       activity-log trait used by catalogue/content models
  Services/OrderService.php              place order, mark paid/shipped/delivered, cancel (+restock), emails
  Services/ProductCsv.php                product CSV import/export/template
  Support/                               Money, Img (WebP resizer), Csv, ShoppingCart, CheckoutTotals, Wishlist
  Providers/AppServiceProvider.php       shared view data (menu, settings, counts), sign-in logging, admin timezone
  Providers/Filament/AdminPanelProvider.php
config/shop.php                          default store settings (overridden by Admin › Store settings)
database/seeders/                        DatabaseSeeder, CatalogueSeeder, ContentSeeder, ShippingSeeder
resources/css/app.css                    design system (tokens, components, motion classes)
resources/js/app.js                      Alpine stores/components (bag, toast, search, product, checkout…)
resources/js/motion.js                   reveals, split headlines, parallax, Lenis, header, drag rails
resources/views/shop/                    storefront templates (layout, home, listing, product, bag, checkout, order…)
resources/views/shop/account/, auth/     customer account + sign-in templates
resources/views/components/shop/input.blade.php   floating-label input
resources/views/mail/                    order emails (Markdown)
resources/views/errors/404.blade.php     designed 404
routes/web.php                           all storefront routes
tests/Feature/                           77 tests (see §12)
docs/                                    REQUIREMENTS.md, this handbook
```

---

## 6. Database

Migrations (in order): users/cache/jobs (Laravel) → `catalog_tables` → `activity_log` → `content_tables` →
`account_tables` → `admin_security_and_size_guides` → `checkout_tables`.

| Table | Key columns / notes |
|---|---|
| `users` | `role` = customer \| staff \| admin; `phone`, `marketing_opt_in`; 2FA secret + recovery codes (encrypted) |
| `categories` | tree via `parent_id`; `slug` unique (e.g. `woman-jeans`), `image`, `is_active`, `sort_order`, `size_guide_id` |
| `collections` | SS27, Gift Guide…; `image` banner |
| `products` | `style_code` unique (SKU base), `slug`, `gender`, `price`/`compare_at_price` **in cents**, `status` draft\|active\|archived, `published_at`, `is_featured`, SEO, `size_guide_id`, soft deletes |
| `product_colours` | per product: `name`, `code` (SKU part), `hex` |
| `product_images` | per colour; `path` on `public` disk under `products/` |
| `product_variants` | per colour: `size`, `length` (Regular/Petit/Long), `sku` (auto `STYLE-COLOUR-SIZE-LEN`), `barcode`, `price` override, `stock` |
| `category_product`, `collection_product` | pivots |
| `size_guides` | rich-text chart; attached to categories (inherited downwards) or products |
| `pages`, `faqs`, `contact_messages` | content; `pages.footer_group` = client_services \| company \| legal |
| `settings` | key → JSON value (see §11) |
| `addresses` | customer addresses; exactly one default per user (model hook) |
| `wishlist_items` | user + product + colour |
| `carts`, `cart_items` | bag; guests by UUID token (session + 30-day cookie), merged into account on sign-in |
| `orders` | `number` (OMR-100001), `access_token` (guest link), `status`, contact, `shipping_address` JSON snapshot, totals in cents, `vat_total`, `vat_exempt`, gift wrap/message, notes, courier/tracking, timestamps per status |
| `order_items` | snapshot of name/colour/size/sku/image/price at time of order |
| `shipping_methods` | `zone` za \| international, `price`, `free_over`, `estimate` |
| `discount_codes` | percent \| fixed, `min_subtotal`, dates, `max_uses`, `used_count` |
| `activity_log` | spatie: who changed what (old → new) |

**All money is stored as integer cents (ZAR).** Format with `App\Support\Money::format($cents)` → `R 1 250`.

---

## 7. Customer website (storefront)

| URL | What |
|---|---|
| `/` | Home: built from Admin › Content › Home page blocks; falls back to automatic (featured hero + new arrivals + collections) |
| `/c/{category-slug}` | Category (includes all sub-categories), filters (size in stock, colour), sort, 2/3/4 grid, load more |
| `/collections/{slug}` | Collection page (banner image if set) |
| `/search?q=` · `/search/suggest?q=` | Search results · live JSON for the search overlay |
| `/p/{product-slug}?colour={code}` | Product page |
| `/bag` · `/bag/drawer` | Bag page · HTML for the slide-out bag |
| `/checkout` · `/checkout/quote` | Checkout · live delivery/VAT summary |
| `/order/{number}/{token}` | Order confirmation / status (private link, also for guests) |
| `/wishlist` | Wishlist (guests: session; signed-in: database) |
| `/login`, `/register`, `/forgot-password`, `/reset-password/{token}` | Customer auth |
| `/account`, `/account/orders`, `/account/details`, `/account/addresses` | My account |
| `/pages/{slug}` · `/faq` · `/contact` | Content pages · FAQ · contact form (saved + emailed) |
| `/img/{width}/{path}` | Makes a resized WebP on first request (then served as a static file) |

Starter content (all **placeholder** — replace): pages Shipping & Returns, Size Guide, Product Care, About,
Careers, Terms, Privacy, Cookie Policy; 7 FAQs; category tree **Woman › Clothing** and **Man › Clothing** only (clothing business — no shoes, bags or accessories); collections
New Arrivals, Gift Guide, Winter Dressing, SS27, FW26.

Current sample products (entered by owner): *Leather Jacket* (R100 — probably meant more), *Women Sweater*.

---

## 8. Admin panel

`/admin` — only users with role **staff** or **admin**. Customers get 403.

| Menu | Screens | Who |
|---|---|---|
| Dashboard | Live products, stock alerts, customers, unread messages, wishlist saves, sales this month; "Stock to reorder", "Most wishlisted", "Newest customers" | all |
| Catalogue | Products (tabs: Details, Description, Colours/photos/sizes, SEO; **Spreadsheet** menu: import/export/template), Categories, Collections, Size guides | all |
| Sales | **Orders** (tabs: Awaiting payment, To ship, Shipped, Completed, Cancelled; actions: Mark as paid, Mark as shipped + tracking, Mark delivered, Cancel (restock), internal note, resend email, export CSV), Delivery options, Discount codes | all |
| Content | Home page (block builder: **Banner slideshow** (1–3 slides, seconds per slide 3–15, crossfade+zoom or slide, pause on hover, swipe/arrows, line indicators), Large banner/video, Two banners, Product row, Image tiles, Text), Pages, FAQs | all |
| People | Customers (edit, addresses, wishlist, export CSV; delete/role change admin-only), Messages (contact form), **Team** (staff/admins, reset 2FA) | Team: admin only |
| Settings | Store settings, Activity log | admin only |
| Profile (user menu) | Name, email, password, **two-step sign-in** set-up | all |

---

## 9. Business rules

**Prices & VAT**
- Shop prices include VAT (rate setting, default 15%).
- Delivery country ≠ ZA (and setting "VAT-free exports" on) → each item price ÷ 1.15; no VAT line; international delivery only.
- ZA orders show "Includes VAT of R x" = total × 15/115.

**Stock**
- Add-to-bag and bag quantities are capped by stock (max 10 per line).
- **Stock is reserved (deducted) when the order is placed** (rows locked to prevent overselling).
- Cancelling an order puts stock back. Unpaid orders do **not** expire automatically (staff cancel them).

**Order life cycle**
`awaiting_payment` → (staff: Mark as paid) `paid` → (Mark as shipped + courier/tracking) `shipped` → `delivered`; or `cancelled`.
Emails: customer confirmation + shop "new order" on placing; customer emails on paid / shipped / cancelled.
Payment instructions (bank details) come from Store settings and appear on the confirmation page/email.

**Discount codes** — percent or fixed; checks active, start/end dates, max uses, minimum spend; `used_count` increments per order.

**Delivery** — methods per zone; `free_over` threshold uses subtotal after discount.

**Visibility** — product visible only if `status = active` and `published_at` empty or past. Hidden products can't be bagged or wishlisted.

**Security**
- Customers can never set their own role (registration/profile build fields explicitly).
- Login rate limits (shop: 5/min per email+IP; admin: Filament's 5/min). Contact form: 5 per 10 min + honeypot.
- Two-step sign-in **required** for staff/admin when `ADMIN_REQUIRE_2FA=true` (default; set `false` only locally).
- Activity log records catalogue/content/order/team changes, settings changes, staff sign-ins and failed attempts.
- CSV exports protect against spreadsheet formula injection; rich text is sanitised on output.

**Images** — originals kept; WebP copies at 200/400/800/1200/1600/2400 px made on first view in `storage/app/public/_resized/…`; deleted with the photo.

---

## 10. Design system & motion

- **Fonts:** Fraunces (serif, headlines: `.display`, `.h1`, `.h2`) + Inter Tight (UI). Labels: `.label`, `.eyebrow`.
- **Light / dark theme:** switch in the header (sun/moon), phone menu and footer. Choice saved per device (`localStorage` key `theme`); until chosen it follows the device setting. A small script in `<head>` applies it before drawing (no flash). Dark colours are in `:root[data-theme='dark']` in `app.css`. Use `paper/ink/mute/line/line-strong` (they swap) for normal UI; use the fixed `day`/`night` colours only for text on photos and dark photo backgrounds. The footer follows the theme.
- **Colours** (`resources/css/app.css` `@theme`): paper `#faf9f6`, paper-2 `#f2f0eb`, ink `#141412`, mute `#77746d`, line `#e3e0d8`, sale `#a3321f`, ok `#2f5d46`.
- **Components:** `.btn-black`, `.btn-line`, `.btn-light`, `.btn-dark-outline` (fill sweeps up on hover), `.link-u`, `.link-draw`,
  `.field`/`.field-input`/`.field-label` (floating labels via `x-shop.input`), `.check`, `.radio`, `.chip` (sizes), `.drawer`, `.scrim`, `.rail` (drag rows), `.skeleton`, `.rich`.
- **Motion attributes** (handled by `resources/js/motion.js`):
  - `data-reveal` (rise + fade), `data-reveal="fade"`, `data-reveal="mask"` (wipe + image zoom-out), `style="--d: 200ms"` delay
  - `data-split` (headline lines rise one by one; `data-delay`), `data-stagger="70"` (children appear one after another)
  - `data-parallax="0.15"`, `.kenburns` (hero zoom), `data-rail` + `data-rail-progress` (drag scroll)
  - `img.img-fade` (fade in when loaded), page transitions via `@view-transition`
  - All motion disabled for "reduce motion" users; Lenis only on desktop with a mouse.
- **Layout flags in Blade:** `@section('header-overlay', '1')` = transparent header over a hero; `@section('minimal-chrome', '1')` = checkout header, no footer.
- **Alpine stores:** `$store.bag` (count, open, add, show), `$store.wishlist`, `$store.toast.show(msg)`.
- **Logo:** uploaded in Admin › Store settings › **Brand** (current file: `storage/app/public/brand/uploads/omr-logo.jpg`, "OMR CLOTHING" with hanger). `App\Support\Brand` removes the white background, trims it and makes: dark logo, white logo (over photos / dark footer / dark admin), icons 32/180/512 px, 1200×630 share image (files in `storage/app/public/brand/generated/`). Shown via `<x-shop.logo>` in header, checkout, footer, admin (`brandLogo`) and emails (`resources/views/vendor/mail/html/header.blade.php`). Without a logo the store name (`APP_NAME`) shows as text; `public/favicon.svg` is the fallback icon.

---

## 11. Settings & configuration

**Admin › Store settings** (stored in `settings` table; defaults in `config/shop.php` → `defaults`):
**brand (logo, square icon, remove-background toggle)**, announcement bar, contact email/phone/hours/address, product-page notes, shipping & returns text, social links,
country label, default SEO description, low-stock threshold, **payment instructions**, order prefix (OMR),
VAT rate, VAT-free exports, gift-wrap price.

**`.env` keys added for this project** (see `.env.example`):

| Key | Meaning |
|---|---|
| `APP_NAME` | Store name (wordmark, emails, page titles, admin) — **"OMR TEX FASHIONS"** |
| `APP_URL` | `http://fashion-store.test` locally; real domain when live |
| `ADMIN_EMAIL`, `ADMIN_PASSWORD` | First admin account created by `db:seed` (never overwritten later) |
| `ADMIN_TIMEZONE` | Timezone for admin date pickers (DB stays UTC) |
| `ADMIN_REQUIRE_2FA` | `true` in production; `false` only on the dev PC |

---

## 12. Tests

`php artisan test` → **77 tests, all passing** (last run 21 Nov 2026). Uses in-memory SQLite (`phpunit.xml`, `ADMIN_REQUIRE_2FA=false`).

| File | Covers |
|---|---|
| `AdminCatalogueTest` | admin access by role, product create with colours/photos/sizes, SKU generation |
| `StorefrontTest` | home, categories incl. children, hidden/future products, product page/SEO, filters, search, collections |
| `ContentTest` | pages, footer, sanitising, FAQ, contact form + bot trap, store settings |
| `AccountTest` | register/sign-in/rate-limit/reset password, profile, addresses, wishlist (guest merge) |
| `HomeAndImagesTest` | WebP resizer (security, sizes), home page blocks, link validation |
| `AdminToolsTest` | customers/team, dashboard widgets, size guides, CSV import/export, activity log, 2FA |
| `CheckoutTest` | bag, discount codes, SA + international checkout/VAT, stock locking, guest links, admin order actions, emails |

---

## 13. Git status

- Repo initialised in the project folder; branch **`feature/admin-catalog`** (no `main` yet).
- **One local commit** `0dc42db` (everything up to the redesign). **Not pushed** — no GitHub remote yet.
- Owner asked to **hold further commits for now** (this handbook and later changes are uncommitted).
- To push later: create an empty **private** repo on GitHub (no README), then
  `git remote add origin <url>` and `git push -u origin feature/admin-catalog` (Git Credential Manager opens a browser sign-in).
  GitHub CLI (`gh`) is **not installed**.
- `.gitignore` already excludes `.env`, `database/*.sqlite`, uploaded files, `vendor`, `node_modules`, `public/build`.

---

## 14. What is left (roadmap)

**Step 5 — skipped for now:** cookie consent banner (POPIA/GDPR), real newsletter (Mailchimp/Klaviyo — footer form is a placeholder),
currency switcher (USD/EUR/GBP display), `sitemap.xml`/`robots.txt`/OG images, Google Analytics + Meta Pixel,
back-in-stock alerts, (search suggestions **done**).

**Step 7 — remaining parts:** guest order-status lookup (by email + order number), online returns request,
admin: customer's orders tab on the customer page, refunds record, invoices/packing slips (PDF).

**Other ideas / open items**
- Automatic cancel of unpaid orders after N days (stock is held until staff cancel).
- Gift cards (not suited to bank transfer; revisit if online payment is added).
- Online card payments (Paystack recommended for SA + international) when the owner is ready.
- Brand done: logo uploaded (icons and share image generated). Logo reads "OMR CLOTHING" while the store name is "OMR TEX FASHIONS" — owner to confirm. An SVG logo would be even sharper. Order numbers use the OMR prefix (OMR-100001).
- Replace all placeholder text, size charts and policies; legal review of Terms/Privacy.
- Upload high-resolution photos (banners ≥ 2400 px, products ≥ 1600 px) and build the home page in the admin.
- Same-Wi-Fi preview: make `public` disk URLs relative (they use `APP_URL`).

---

## 15. Going live checklist (step 8)

Needs from owner: **hosting details** (VPS recommended, e.g. Hetzner/DigitalOcean/AWS Cape Town; or Laravel Forge/Ploi), **domain**, **email service** (Resend/Postmark/SendGrid or hosting SMTP).

1. Server: PHP 8.4/8.5 with gd, intl, pdo_mysql, zip, bcmath, fileinfo; Nginx; **MySQL 8**; Redis optional; Composer; Node for building.
2. `upload_max_filesize` / `post_max_size` = 64M; `memory_limit` = 512M.
3. `.env` (production): `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://domain`, new `APP_KEY`, MySQL credentials,
   `MAIL_*` for the email service, `MAIL_FROM_ADDRESS`, `ADMIN_REQUIRE_2FA=true`, `ADMIN_TIMEZONE`, `SESSION_DRIVER`/`CACHE_STORE` (database or redis), `QUEUE_CONNECTION`.
4. Deploy: `composer install --no-dev -o`, `npm ci && npm run build`, `php artisan migrate --force`, `php artisan db:seed --force` (first time only),
   `php artisan storage:link`, `php artisan optimize`, `php artisan filament:optimize`, `php artisan images:warm`.
5. HTTPS (Let's Encrypt), HSTS; scheduler/queue worker if queued mail is enabled.
6. Daily database + `storage/app/public` backups; error monitoring (e.g. Sentry/Flare).
7. Fill Store settings (contact email, **payment instructions/bank details**, social links), real delivery prices, policies.
8. Every team member sets up two-step sign-in; change the seeded admin password.
9. Test on real iPhone/Android + Safari; Lighthouse ≥ 90.

### Live server (set up 26 November 2026)
- **https://omrtexfashions.com** — VPS `194.31.53.88` (Ubuntu 24.04, 1 CPU, 4 GB + 1 GB swap). DNS at Namecheap: A records `@` and `www` → the IP.
- PHP 8.4-FPM, MySQL 8.0, Nginx, Composer, Let's Encrypt (auto-renews). **No Node** on the server — CSS/JS are built on the PC.
- App in `/var/www/fashionstore` (branch `main`). SSH: `ssh -i ~/.ssh/fashionstore_ed25519 root@194.31.53.88`.
- Secrets live only on the server: `.env`, `/root/.fashionstore-db.cnf` (database login).
- Content: the local SQLite data + `storage/app/public` were copied to the live server on 26 November 2026 (backup of the
  server before that: `/root/backups/`). **From now on the live admin is the real data** — don't copy local data over it again.
- Still to do: email service (`MAIL_MAILER=log` for now), backups, firewall + deploy user + SSH hardening.

### Deploying updates
1. Merge the work into `main` on GitHub.
2. On the PC: `git checkout main; git pull`
3. `powershell -ExecutionPolicy Bypass -File scripts\deploy.ps1`

The script refuses to run with uncommitted changes or when the folder isn't exactly at `origin/main` (the CSS must match the
server's code). It builds CSS/JS from `E:\` (upper-case — building from `e:\` leaves the fonts out of the CSS), uploads them,
puts the site in maintenance mode for a few seconds, pulls `main`, runs [`scripts/server-deploy.sh`](../scripts/server-deploy.sh)
(composer, migrate, caches, **PHP-FPM reload — opcache never re-reads changed files on its own**), and checks the live site answers.

---

## 16. Gotchas learned while building

- **Windows PowerShell 5:** `Set-Content -Encoding UTF8` adds a BOM → PHP files break ("namespace must be first statement"). Write files without BOM.
- **Composer on Windows** (`composer.bat`): `^` in version constraints gets eaten — use `~4.0` or quote carefully.
- **Tailwind 4:** you cannot `@apply` your own component classes (e.g. `@apply btn`) — use grouped selectors (`:is(.btn, .btn-black)`) instead.
- **Filament 4:** `modifyQueryUsing(fn ($query) => …)` — the parameter **must be named `$query`** (injection by name).
- **Filament simple repeaters** don't fill well from tests; use a normal repeater with a `text` field and map to a list.
- **Windows + Herd first render is slow** after clearing caches (template compile) → run `php artisan view:cache` + `filament:optimize`.
- **Dashboard widgets** are not lazy (`$isLazy = false`) to avoid many parallel requests on small servers.
- **Arrow functions** capture by value — use `function () use (&$errors)` when appending to an outer array (CSV import bug fixed).
- **Test client doesn't keep cookies** → guest bag token also stored in the session.
- **Admin dates:** DB is UTC; Filament shows/enters in `ADMIN_TIMEZONE`.
- **New admin page doesn't appear / "Route [filament.admin.resources.…] not defined":** Filament's component cache (`bootstrap/cache/filament`) is stale. Run `php artisan filament:optimize` again **after adding any new Resource/Page/Widget** (and on every deploy). Tests don't catch this — they never use the cache.
- **Catalogue mode tests:** checkout/account tests switch it back on with `config(['shop.checkout_enabled' => true])` in `setUp()`; `PriceEnquiryTest` covers the default (off).

---

## 17. How to continue in a new chat

1. Open the project folder in Claude Code and attach/paste this file (and `docs/REQUIREMENTS.md` if useful).
2. Say what to do next, e.g. *"Read docs/PROJECT_HANDBOOK.md. Continue with step 7: guest order lookup and returns."*
   or *"Here are my hosting and domain details — do step 8."*
3. Ground rules the owner prefers:
   - Explain in plain language; keep summaries short.
   - **Don't waste tokens re-testing after every small change** — build everything, then test once at the end.
   - **Ask before committing or pushing** (owner asked to hold commits for now).
   - Keep everything in the existing style (§10) and patterns (§5).
