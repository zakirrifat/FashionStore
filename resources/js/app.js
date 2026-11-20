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

/** Full-screen search with live results. */
Alpine.data('search', () => ({
    open: false,
    q: '',
    results: [],
    loading: false,
    timer: null,
    toggle() {
        this.open = !this.open;
        if (this.open) this.$nextTick(() => this.$refs.input?.focus());
    },
    type() {
        clearTimeout(this.timer);
        if (this.q.trim().length < 2) {
            this.results = [];
            return;
        }
        this.timer = setTimeout(async () => {
            this.loading = true;
            try {
                const { data } = await window.axios.get('/search/suggest', { params: { q: this.q } });
                this.results = data.results;
            } finally {
                this.loading = false;
            }
        }, 220);
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
