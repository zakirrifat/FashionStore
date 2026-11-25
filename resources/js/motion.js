/**
 * Motion system: reveal-on-scroll, split headlines, parallax, smooth scroll,
 * hide-on-scroll header and drag rails. Everything is skipped when the visitor
 * has "reduce motion" switched on.
 */
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import Lenis from 'lenis';

gsap.registerPlugin(ScrollTrigger);

export const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
if (!reducedMotion) document.documentElement.classList.add('js-motion');

let lenis = null;

/** Gentle smooth scrolling on desktop mice/trackpads only (never on touch). */
export function initSmoothScroll() {
    if (reducedMotion || !window.matchMedia('(pointer: fine) and (min-width: 1024px)').matches) return;
    lenis = new Lenis({ duration: 1.1, easing: (t) => 1 - Math.pow(1 - t, 4), smoothWheel: true });
    lenis.on('scroll', ScrollTrigger.update);
    gsap.ticker.add((time) => lenis.raf(time * 1000));
    gsap.ticker.lagSmoothing(0);
    window.lenis = lenis;
    // Pause smooth scroll while a drawer or overlay is open.
    new MutationObserver(() => {
        document.documentElement.style.overflow === 'hidden' ? lenis.stop() : lenis.start();
    }).observe(document.documentElement, { attributes: true, attributeFilter: ['style'] });
}

/** Header: hides when scrolling down, returns when scrolling up; solid after the hero. */
export function initHeader() {
    const header = document.querySelector('[data-header]');
    if (!header) return;
    const overlay = header.hasAttribute('data-overlay');
    const zone = document.querySelector('[data-header-zone]');
    // Mouse/trackpad: header hides once you scroll and comes back when the pointer reaches the top.
    // Touch screens can't hover, so there it comes back when you scroll up a little.
    const canHover = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
    let last = window.scrollY;
    let hovering = false;
    let scrollUpShow = false;
    let hideTimer = null;

    const busy = () => header.classList.contains('is-open') || header.matches(':focus-within');
    const apply = () => {
        const y = window.scrollY;
        const scrolled = y > 80;
        if (!canHover) {
            if (y < last - 4) scrollUpShow = true;
            else if (y > last + 4) scrollUpShow = false;
        }
        const show = !scrolled || busy() || (canHover ? hovering : scrollUpShow);
        header.classList.toggle('-translate-y-full', !show);
        if (overlay) header.classList.toggle('is-solid', y > 40);
        zone?.classList.toggle('hidden', !(canHover && scrolled));
        last = y;
    };

    const enter = () => {
        clearTimeout(hideTimer);
        hovering = true;
        apply();
    };
    const leave = () => {
        hovering = false;
        hideTimer = setTimeout(apply, 300); // small delay so it doesn't flicker
    };
    header.addEventListener('mouseenter', enter);
    header.addEventListener('mouseleave', leave);
    zone?.addEventListener('mouseenter', enter);
    header.addEventListener('focusin', apply);
    header.addEventListener('focusout', () => setTimeout(apply, 0));
    new MutationObserver(apply).observe(header, { attributes: true, attributeFilter: ['class'] });
    window.addEventListener('scroll', apply, { passive: true });
    apply();
}

/** Run on page load and again on any new HTML (load more, colour switch…). */
export function initMotion(root = document) {
    fadeImages(root);
    staggerChildren(root);
    splitLines(root);
    revealOnScroll(root);
    parallax(root);
    rails(root);
}

function fadeImages(root) {
    root.querySelectorAll('img.img-fade:not(.is-loaded)').forEach((img) => {
        const done = () => img.classList.add('is-loaded');
        img.complete && img.naturalWidth ? done() : img.addEventListener('load', done, { once: true });
        img.addEventListener('error', done, { once: true });
    });
}

function staggerChildren(root) {
    root.querySelectorAll('[data-stagger]').forEach((parent) => {
        const step = Number(parent.dataset.stagger) || 70;
        [...parent.children].forEach((child, i) => {
            if (!child.hasAttribute('data-reveal')) child.setAttribute('data-reveal', '');
            if (!child.style.getPropertyValue('--d')) child.style.setProperty('--d', `${(i % 12) * step}ms`);
        });
    });
}

/**
 * Wrap each visual line of a headline so lines can rise one after another.
 * Lines are measured only once the web fonts have loaded (the fallback font has
 * different widths, which would break lines in the wrong place), and measured
 * again when the window width changes.
 */
const splitEls = new Set();
function splitLines(root) {
    const els = [...root.querySelectorAll('[data-split]:not([data-split-done])')];
    if (!els.length) return;
    els.forEach((el) => {
        const text = el.textContent.trim().replace(/\s+/g, ' ');
        el.dataset.splitText = text;
        el.setAttribute('aria-label', text);
        el.setAttribute('data-split-done', '');
        if (!el.hasAttribute('data-reveal')) el.setAttribute('data-reveal', 'fade');
        splitEls.add(el);
    });
    const run = () => els.forEach(layoutLines);
    document.fonts && document.fonts.status !== 'loaded' ? document.fonts.ready.then(run) : run();
}

function layoutLines(el) {
    if (!el.isConnected) return splitEls.delete(el);
    const text = el.dataset.splitText;
    el.innerHTML = text.split(' ').map((w) => `<span class="inline-block" aria-hidden="true">${escapeHtml(w)}</span>`).join(' ');
    const lines = [];
    [...el.children].forEach((w) => {
        const top = w.offsetTop;
        const line = lines.find((l) => Math.abs(l.top - top) < 4);
        line ? line.words.push(w.textContent) : lines.push({ top, words: [w.textContent] });
    });
    el.innerHTML = lines
        .map((l, i) => `<span class="line-mask" aria-hidden="true"><span class="line-inner" style="--d:${i * 110 + (Number(el.dataset.delay) || 0)}ms">${escapeHtml(l.words.join(' '))}</span></span>`)
        .join('');
    el.dataset.splitWidth = el.clientWidth;
}

let splitResizeTimer;
window.addEventListener('resize', () => {
    clearTimeout(splitResizeTimer);
    splitResizeTimer = setTimeout(() => {
        splitEls.forEach((el) => {
            if (!el.isConnected) return splitEls.delete(el);
            if (Number(el.dataset.splitWidth) !== el.clientWidth) layoutLines(el);
        });
    }, 150);
});

let io;
function revealOnScroll(root) {
    const items = root.querySelectorAll('[data-reveal]:not(.is-in), [data-split]:not(.is-in)');
    if (reducedMotion || !('IntersectionObserver' in window)) {
        items.forEach((el) => el.classList.add('is-in'));
        return;
    }
    io ??= new IntersectionObserver(
        (entries) => entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-in');
                io.unobserve(entry.target);
            }
        }),
        { rootMargin: '0px 0px -8% 0px', threshold: 0.08 },
    );
    items.forEach((el) => io.observe(el));
}

/** data-parallax="0.15" moves the element slower than the page. */
function parallax(root) {
    if (reducedMotion) return;
    root.querySelectorAll('[data-parallax]:not([data-parallax-done])').forEach((el) => {
        const amount = Number(el.dataset.parallax) || 0.12;
        gsap.fromTo(el, { yPercent: -amount * 50 }, {
            yPercent: amount * 50,
            ease: 'none',
            scrollTrigger: { trigger: el.parentElement, start: 'top bottom', end: 'bottom top', scrub: true },
        });
        el.setAttribute('data-parallax-done', '');
    });
}

/** Horizontal product rows: drag with the mouse, progress bar underneath, optional prev/next arrows. */
function rails(root) {
    root.querySelectorAll('[data-rail]:not([data-rail-done])').forEach((rail) => {
        const bar = rail.parentElement.querySelector('[data-rail-progress]');
        const group = rail.closest('[data-rail-group]');
        const prev = group?.querySelector('[data-rail-prev]');
        const next = group?.querySelector('[data-rail-next]');
        const arrows = group?.querySelector('[data-rail-arrows]');
        const step = (dir) => rail.scrollBy({ left: dir * rail.clientWidth * 0.8, behavior: reducedMotion ? 'auto' : 'smooth' });
        prev?.addEventListener('click', () => step(-1));
        next?.addEventListener('click', () => step(1));
        const update = () => {
            const max = rail.scrollWidth - rail.clientWidth;
            if (prev) prev.disabled = rail.scrollLeft <= 2;
            if (next) next.disabled = rail.scrollLeft >= max - 2;
            if (arrows) arrows.style.visibility = max > 4 ? 'visible' : 'hidden';
            if (bar) {
                bar.style.width = `${Math.max(12, (rail.clientWidth / rail.scrollWidth) * 100)}%`;
                bar.style.transform = `translateX(${max > 0 ? (rail.scrollLeft / max) * ((rail.scrollWidth / rail.clientWidth) - 1) * 100 : 0}%)`;
                bar.parentElement.style.visibility = max > 4 ? 'visible' : 'hidden';
            }
        };
        let down = false, startX = 0, startLeft = 0, moved = false;
        rail.addEventListener('pointerdown', (e) => {
            if (e.pointerType !== 'mouse') return;
            down = true; moved = false; startX = e.clientX; startLeft = rail.scrollLeft;
        });
        window.addEventListener('pointermove', (e) => {
            if (!down) return;
            const dx = e.clientX - startX;
            if (Math.abs(dx) > 4) { moved = true; rail.classList.add('is-dragging'); }
            rail.scrollLeft = startLeft - dx;
        });
        window.addEventListener('pointerup', () => {
            if (!down) return;
            down = false;
            setTimeout(() => rail.classList.remove('is-dragging'), 0);
        });
        rail.addEventListener('click', (e) => { if (moved) { e.preventDefault(); e.stopPropagation(); } }, true);
        rail.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);
        update();
        rail.setAttribute('data-rail-done', '');
    });
}

function escapeHtml(s) {
    return s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
}
