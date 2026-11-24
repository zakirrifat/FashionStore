import './bootstrap';
import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import { initMotion, initSmoothScroll, initHeader } from './motion';

window.Alpine = Alpine;
Alpine.plugin(collapse);

const body = document.body;
const money = (cents) => 'R ' + (cents / 100).toLocaleString('en-ZA', { minimumFractionDigits: cents % 100 ? 2 : 0 }).replace(/,/g, ' ');

/* ═════════════ Shared stores ═════════════ */

Alpine.store('wishlist', { count: Number(body?.dataset.wishlistCount ?? 0) });

/**
 * Light / dark theme. The visitor's choice is remembered on this device;
 * until they choose, the site follows their phone/computer setting.
 * (The <head> script in layout.blade.php applies it before the page is drawn.)
 */
Alpine.store('theme', {
    mode: document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light',
    get dark() {
        return this.mode === 'dark';
    },
    set(mode) {
        if (mode === this.mode) return;
        const apply = () => {
            this.mode = mode;
            document.documentElement.dataset.theme = mode;
            document.querySelector('meta[name="theme-color"]')?.setAttribute('content', mode === 'dark' ? '#121110' : '#faf9f6');
        };
        try {
            localStorage.setItem('theme', mode);
        } catch (e) {
            /* private mode: still switches for this page */
        }
        const root = document.documentElement;
        if (document.startViewTransition && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            root.classList.add('theme-wipe');
            document.startViewTransition(apply).finished.finally(() => root.classList.remove('theme-wipe'));
        } else {
            root.classList.add('theme-switching');
            apply();
            setTimeout(() => root.classList.remove('theme-switching'), 550);
        }
    },
    toggle() {
        this.set(this.dark ? 'light' : 'dark');
    },
});
// Follow the device setting until the visitor picks a theme themselves.
window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
    let saved = null;
    try {
        saved = localStorage.getItem('theme');
    } catch (err) {
        /* ignore */
    }
    if (saved !== 'light' && saved !== 'dark') {
        const mode = e.matches ? 'dark' : 'light';
        document.documentElement.dataset.theme = mode;
        Alpine.store('theme').mode = mode;
    }
});

Alpine.store('toast', {
    items: [],
    show(message, tone = 'ok') {
        const id = Date.now() + Math.random();
        this.items.push({ id, message, tone });
        setTimeout(() => (this.items = this.items.filter((t) => t.id !== id)), 3800);
    },
});

/** Slide-out bag available on every page. */
Alpine.store('bag', {
    count: Number(body?.dataset.bagCount ?? 0),
    open: false,
    loading: false,
    html: '',
    async show() {
        this.open = true;
        await this.load();
    },
    close() {
        this.open = false;
    },
    async load() {
        this.loading = true;
        try {
            const { data } = await window.axios.get('/bag/drawer');
            this.html = data.html;
            this.count = data.count;
        } finally {
            this.loading = false;
        }
    },
    async add(variantId, quantity = 1) {
        try {
            const { data } = await window.axios.post('/bag', { variant_id: variantId, quantity });
            this.count = data.count;
            if (data.ok) {
                await this.show();
            } else {
                Alpine.store('toast').show(data.message, 'warn');
            }
            return data;
        } catch (e) {
            Alpine.store('toast').show(e.response?.data?.message || 'Sorry, something went wrong. Please try again.', 'warn');
            return { ok: false };
        }
    },
    /** Quantity / remove forms inside the drawer, without leaving the page. */
    async submit(form) {
        const method = (form.querySelector('input[name=_method]')?.value || form.method || 'post').toLowerCase();
        const data = Object.fromEntries(new FormData(form));
        delete data._method;
        delete data._token;
        this.loading = true;
        try {
            await window.axios({ method, url: form.action, data, headers: { Accept: 'application/json' } });
        } catch (e) {
            /* fall through to reload */
        }
        await this.load();
    },
});

/**
 * "Ask for price" popup (one per page, in layout.blade.php).
 * open({ id, name, image, colourId, colour, variantId, size }) from any product card or the product page.
 */
Alpine.store('enquiry', {
    isOpen: false,
    product: null,
    form: { name: '', phone: '', email: '', message: '', website: '' },
    errors: {},
    sending: false,
    sent: false,
    thanks: '',
    open(product) {
        this.product = product;
        this.errors = {};
        this.sent = false;
        this.form.message = '';
        this.isOpen = true;
    },
    close() {
        this.isOpen = false;
    },
    async submit() {
        if (this.sending) return;
        this.sending = true;
        this.errors = {};
        try {
            const { data } = await window.axios.post('/enquiry', {
                product_id: this.product.id,
                colour_id: this.product.colourId || null,
                variant_id: this.product.variantId || null,
                ...this.form,
            });
            this.thanks = data.message;
            this.sent = true;
        } catch (e) {
            const status = e.response?.status;
            if (status === 422) {
                this.errors = Object.fromEntries(Object.entries(e.response.data.errors || {}).map(([k, v]) => [k, v[0]]));
            } else if (status === 429) {
                this.errors = { form: 'You’ve sent several requests already. Please try again in a few minutes.' };
            } else if (status === 404) {
                this.errors = { form: 'Sorry, this product is no longer available.' };
            } else {
                this.errors = { form: 'Sorry, something went wrong. Please try again.' };
            }
        } finally {
            this.sending = false;
        }
    },
});

/* ═════════════ Components ═════════════ */

/** Heart / wishlist button. */
Alpine.data('wishlistButton', (productId, colourId, saved) => ({
    saved,
    busy: false,
    async toggle() {
        if (this.busy) return;
        this.busy = true;
        try {
            const { data } = await window.axios.post('/wishlist/toggle', { product_id: productId, colour_id: colourId });
            this.saved = data.added;
            Alpine.store('wishlist').count = data.count;
            this.$el.querySelector('[data-heart]')?.classList.remove('pop');
            void this.$el.offsetWidth;
            this.$el.querySelector('[data-heart]')?.classList.add('pop');
            Alpine.store('toast').show(data.added ? 'Saved to your wishlist' : 'Removed from your wishlist');
        } catch (e) {
            Alpine.store('toast').show('Sorry, the wishlist could not be updated.', 'warn');
        } finally {
            this.busy = false;
        }
    },
}));

/**
 * Home page banner slideshow (any number of slides).
 * Auto-advances every `seconds`; pauses on hover and when the tab is hidden;
 * swipe on phones, arrows/keys on desktop. No autoplay for "reduce motion" visitors.
 */
Alpine.data('heroSlider', (cfg) => ({
    index: 0,
    count: cfg.count,
    seconds: cfg.seconds,
    paused: false,
    running: false,
    timer: null,
    startedAt: 0,
    remaining: 0,
    touchX: 0,
    touchY: 0,
    reduced: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
    init() {
        if (this.count < 2) return;
        this.running = !this.reduced;
        this.restart();
        document.addEventListener('visibilitychange', () => (document.hidden ? this.pause() : this.resume()));
    },
    /** Start the full timer for the current slide, and get the next slide ready. */
    restart() {
        this.remaining = this.seconds * 1000;
        this.prepareNext();
        this.schedule();
    },
    /** Get the next and previous slides ready, so either direction shows a loaded image. */
    prepareNext() {
        this.prepare((this.index + 1) % this.count);
        this.prepare((this.index - 1 + this.count) % this.count);
    },
    /** Make sure a slide's image is decoded and its video is buffering before it appears. */
    prepare(i) {
        const next = this.$el.querySelector(`[data-slide="${i}"]`);
        if (!next) return;
        const img = next.querySelector('img');
        if (img) {
            img.loading = 'eager';
            img.decode?.().catch(() => {});
        }
        const video = next.querySelector('video');
        if (video && video.preload !== 'auto') {
            video.preload = 'auto';
            video.load();
        }
    },
    schedule() {
        clearTimeout(this.timer);
        if (!this.running || this.paused || document.hidden) return;
        this.startedAt = Date.now();
        this.timer = setTimeout(() => this.next(), this.remaining);
    },
    pause() {
        if (this.paused || this.count < 2) return;
        this.paused = true;
        clearTimeout(this.timer);
        this.remaining = Math.max(400, this.remaining - (Date.now() - this.startedAt));
    },
    resume() {
        if (!this.paused) return;
        this.paused = false;
        this.schedule();
    },
    go(i) {
        const target = (i + this.count) % this.count;
        if (target === this.index) return;
        this.index = target;
        this.syncMedia();
        this.replayText();
        this.restart();
    },
    next() {
        this.go(this.index + 1);
    },
    prev() {
        this.go(this.index - 1);
    },
    touchStart(e) {
        this.touchX = e.touches[0].clientX;
        this.touchY = e.touches[0].clientY;
    },
    touchEnd(e) {
        const dx = e.changedTouches[0].clientX - this.touchX;
        const dy = e.changedTouches[0].clientY - this.touchY;
        if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy)) dx < 0 ? this.next() : this.prev();
    },
    /** Only the visible slide's video plays. */
    syncMedia() {
        this.$el.querySelectorAll('[data-slide]').forEach((slide) => {
            const video = slide.querySelector('video');
            if (!video) return;
            if (Number(slide.dataset.slide) === this.index) {
                video.currentTime = 0;
                video.play().catch(() => {});
            } else {
                video.pause();
            }
        });
    },
    /** Re-run the headline / button entrance on the slide that just appeared. */
    replayText() {
        const slide = this.$el.querySelector(`[data-slide="${this.index}"] [data-slide-text]`);
        if (!slide || this.reduced) return;
        const items = slide.querySelectorAll('[data-reveal], [data-split]');
        items.forEach((el) => el.classList.remove('is-in'));
        void slide.offsetWidth;
        setTimeout(() => items.forEach((el) => el.classList.add('is-in')), 250);
    },
}));

const RECENT_SEARCHES_KEY = 'recent-searches';

/** Recent searches stay in this browser only. */
function readRecentSearches() {
    try {
        const saved = JSON.parse(localStorage.getItem(RECENT_SEARCHES_KEY));
        return Array.isArray(saved) ? saved.filter((s) => typeof s === 'string').slice(0, 5) : [];
    } catch {
        return [];
    }
}

function saveRecentSearches(list) {
    try {
        if (list.length) localStorage.setItem(RECENT_SEARCHES_KEY, JSON.stringify(list));
        else localStorage.removeItem(RECENT_SEARCHES_KEY);
    } catch {
        // Private browsing can block storage; search still works.
    }
}

/** Search panel: suggestions before typing, live results while typing, keyboard navigation. */
Alpine.data('search', () => ({
    open: false,
    q: '',
    results: [],
    categories: [],
    featured: [],
    featuredLabel: 'Featured',
    discoverLoaded: false,
    recent: [],
    loading: false,
    failed: false,
    searched: null,
    requestId: 0,
    timer: null,
    previousOverflow: '',
    init() {
        this.recent = readRecentSearches();
        // Coming back with the browser's Back button: don't restore an open panel.
        window.addEventListener('pageshow', (e) => {
            if (e.persisted) this.close(false);
        });
    },
    get term() {
        return this.q.trim();
    },
    get idle() {
        return this.term.length < 2;
    },
    get hasMatches() {
        return !this.idle && (this.results.length > 0 || this.categories.length > 0);
    },
    get noResults() {
        return !this.idle && !this.loading && !this.failed && this.searched === this.term && !this.hasMatches;
    },
    get showDiscover() {
        return this.idle || this.noResults;
    },
    toggle() {
        if (this.open) this.close();
        else this.show();
    },
    show() {
        this.open = true;
        this.recent = readRecentSearches();
        this.previousOverflow = document.documentElement.style.overflow;
        document.documentElement.style.overflow = 'hidden';
        this.loadDiscover();
        this.$nextTick(() => this.$refs.input?.focus({ preventScroll: true }));
    },
    close(restoreFocus = true) {
        // Esc with the price popup on top closes only the popup.
        if (!this.open || Alpine.store('enquiry').isOpen) return;
        this.open = false;
        document.documentElement.style.overflow = this.previousOverflow;
        if (restoreFocus) this.$refs.trigger?.focus({ preventScroll: true });
    },
    async loadDiscover() {
        if (this.discoverLoaded) return;
        try {
            const { data } = await window.axios.get('/search/suggest');
            this.featured = data.featured ?? [];
            this.featuredLabel = data.featured_label ?? 'Featured';
            this.discoverLoaded = true;
        } catch {
            // Categories still show; try again next time the panel opens.
        }
    },
    resetResults() {
        this.requestId++;
        this.results = [];
        this.categories = [];
        this.searched = null;
        this.failed = false;
        this.loading = false;
    },
    type() {
        clearTimeout(this.timer);
        this.failed = false;
        if (this.idle) {
            this.resetResults();
            return;
        }
        const term = this.term;
        const id = ++this.requestId;
        this.timer = setTimeout(async () => {
            this.loading = true;
            try {
                const { data } = await window.axios.get('/search/suggest', { params: { q: term } });
                if (id !== this.requestId) return; // a newer search has started
                this.results = data.results ?? [];
                this.categories = data.categories ?? [];
                this.searched = term;
            } catch {
                if (id !== this.requestId) return;
                this.results = [];
                this.categories = [];
                this.failed = true;
            } finally {
                if (id === this.requestId) this.loading = false;
            }
        }, 220);
    },
    clearQuery() {
        clearTimeout(this.timer);
        this.q = '';
        this.resetResults();
        this.$refs.input?.focus();
    },
    submit(event) {
        if (!this.term) {
            event.preventDefault();
            this.$refs.input?.focus();
            return;
        }
        this.remember();
    },
    remember(value = this.term) {
        const term = value.trim();
        if (term.length < 2) return;
        const lower = term.toLowerCase();
        this.recent = [term, ...this.recent.filter((s) => s.toLowerCase() !== lower)].slice(0, 5);
        saveRecentSearches(this.recent);
    },
    clearRecent() {
        this.recent = [];
        saveRecentSearches([]);
        this.$refs.input?.focus();
    },
    /** Up/Down moves through the visible links; typing returns to the field. */
    nav(event) {
        const input = this.$refs.input;
        const current = document.activeElement;

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            if (event.key === 'ArrowUp' && current === input) return;
            const options = [...this.$refs.panel.querySelectorAll('[data-search-option]')].filter((el) => el.offsetParent !== null);
            if (!options.length) return;
            event.preventDefault();
            const index = options.indexOf(current);
            if (event.key === 'ArrowDown') {
                options[index < 0 ? 0 : Math.min(index + 1, options.length - 1)].focus();
            } else if (index <= 0) {
                input.focus();
            } else {
                options[index - 1].focus();
            }
            return;
        }

        if (event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey && current?.tagName === 'A') {
            input.focus();
        }
    },
}));

/** Product page: size choice, add to bag, image zoom. */
Alpine.data('productBuy', (cfg) => ({
    variants: cfg.variants,
    size: null,
    length: cfg.length,
    hasLengths: cfg.hasLengths,
    adding: false,
    message: '',
    find(size) {
        return this.variants.find((v) => v.size === size && (!this.hasLengths || v.length === this.length));
    },
    inStock(size) {
        const v = this.find(size);
        return v && v.stock > 0;
    },
    get selected() {
        return this.size ? this.find(this.size) : null;
    },
    async add() {
        if (!this.selected) {
            this.message = 'Please select a size';
            this.$refs.sizes?.animate([{ transform: 'translateX(0)' }, { transform: 'translateX(-6px)' }, { transform: 'translateX(6px)' }, { transform: 'translateX(0)' }], { duration: 320 });
            return;
        }
        this.adding = true;
        this.message = '';
        await Alpine.store('bag').add(this.selected.id, 1);
        this.adding = false;
    },
    /** Open "Ask for price" with the chosen colour and (optional) size. */
    ask(product) {
        const v = this.selected;
        Alpine.store('enquiry').open({
            ...product,
            variantId: v?.id ?? null,
            size: v ? [v.size, v.length].filter(Boolean).join(' ') : null,
        });
    },
}));

/** Zoomable full-screen gallery. */
Alpine.data('lightbox', (images) => ({
    open: false,
    index: 0,
    zoom: false,
    images,
    show(i) {
        this.index = i;
        this.zoom = false;
        this.open = true;
        document.documentElement.style.overflow = 'hidden';
    },
    close() {
        this.open = false;
        document.documentElement.style.overflow = '';
    },
    next(step) {
        this.zoom = false;
        this.index = (this.index + step + this.images.length) % this.images.length;
    },
    pan(e) {
        if (!this.zoom) return;
        const r = e.currentTarget.getBoundingClientRect();
        const x = ((e.clientX - r.left) / r.width) * 100;
        const y = ((e.clientY - r.top) / r.height) * 100;
        e.currentTarget.querySelector('img').style.transformOrigin = `${x}% ${y}%`;
    },
}));

/** Swipe gallery dots on phones. */
Alpine.data('swipeGallery', () => ({
    active: 0,
    onScroll(el) {
        this.active = Math.round(el.scrollLeft / el.clientWidth);
    },
    go(i) {
        this.$refs.track.scrollTo({ left: i * this.$refs.track.clientWidth, behavior: 'smooth' });
    },
}));

/** Category grid: 2 / 3 / 4 per row (remembered on this device) + "Load more". */
Alpine.data('listing', () => ({
    cols: (() => {
        try {
            return Number(localStorage.getItem('grid-cols')) || 4;
        } catch (e) {
            return 4;
        }
    })(),
    filters: false,
    loadingMore: false,
    setCols(n) {
        this.cols = n;
        try {
            localStorage.setItem('grid-cols', String(n));
        } catch (e) {
            /* private mode */
        }
    },
    async more(url) {
        this.loadingMore = true;
        try {
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
            const grid = this.$refs.grid;
            [...doc.querySelectorAll('[data-grid] > *')].forEach((el, i) => {
                el.style.setProperty('--d', `${(i % 8) * 60}ms`);
                grid.appendChild(el);
            });
            const nextMore = doc.querySelector('[data-more]');
            const mine = this.$el.querySelector('[data-more]');
            nextMore ? mine.replaceWith(nextMore) : mine?.remove();
            history.replaceState(null, '', url);
            initMotion(grid);
        } finally {
            this.loadingMore = false;
        }
    },
}));

/** Product colour switch without a full page load. */
document.addEventListener('click', async (e) => {
    const link = e.target.closest('a[data-swap]');
    if (!link || e.metaKey || e.ctrlKey || e.shiftKey) return;
    const root = document.getElementById('product-root');
    if (!root) return;
    e.preventDefault();
    root.style.opacity = '0.35';
    root.style.transition = 'opacity 250ms';
    try {
        const res = await fetch(link.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
        const fresh = doc.getElementById('product-root');
        if (!fresh) throw new Error('missing');
        const swap = () => {
            root.replaceWith(fresh);
            document.title = doc.title;
            history.pushState({ swap: true }, '', link.href);
            initMotion(fresh);
        };
        document.startViewTransition ? document.startViewTransition(swap) : swap();
    } catch (err) {
        window.location.href = link.href;
    }
});
window.addEventListener('popstate', (e) => {
    if (e.state?.swap || document.getElementById('product-root')) window.location.reload();
});

/** Checkout: live delivery options + summary, saved-address picker. */
Alpine.data('checkout', (cfg) => ({
    country: cfg.country,
    method: cfg.method || null,
    giftWrap: cfg.giftWrap,
    methods: cfg.methods,
    addresses: cfg.addresses,
    loading: false,
    placing: false,
    async refresh() {
        this.loading = true;
        try {
            const { data } = await window.axios.get(cfg.quoteUrl, {
                params: { country: this.country, shipping_method_id: this.method, gift_wrap: this.giftWrap ? 1 : 0 },
            });
            this.methods = data.methods;
            this.method = data.selected;
            this.$refs.summary.innerHTML = data.html;
        } finally {
            this.loading = false;
        }
    },
    useAddress(id) {
        const a = this.addresses.find((x) => String(x.id) === String(id));
        if (!a) return;
        for (const key of ['first_name', 'last_name', 'company', 'line1', 'line2', 'city', 'postal_code', 'phone']) {
            const input = this.$root.querySelector(`[name="${key}"]`);
            if (input) input.value = a[key] ?? '';
        }
        this.country = a.country;
        this.$nextTick(() => {
            const za = this.$root.querySelector('select[name="province"]');
            const text = this.$root.querySelector('input[name="province"]');
            if (za) za.value = a.country === 'ZA' ? a.province ?? '' : '';
            if (text) text.value = a.country !== 'ZA' ? a.province ?? '' : '';
            this.refresh();
        });
    },
}));

window.money = money;

Alpine.start();

initSmoothScroll();
initHeader();
initMotion(document);
