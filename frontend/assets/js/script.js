// SV Auto Landing Page JavaScript

// Navigation scroll effect + section scroll spy
const siteHeader = document.getElementById('siteHeader');
const nav = document.getElementById('nav');

function getScrollOffset() {
    var section = document.querySelector('section[id]:not(#home)');
    if (section) {
        var margin = parseFloat(window.getComputedStyle(section).scrollMarginTop);
        if (!isNaN(margin) && margin > 0) return margin;
    }
    var headerEl = siteHeader || nav;
    return (headerEl ? headerEl.offsetHeight : 74) + 24;
}

function scrollToSection(target) {
    if (!target) return;
    var top = target.getBoundingClientRect().top + window.pageYOffset - getScrollOffset();
    window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
}

function setActiveNav(sectionId) {
    if (!sectionId) return;
    document.querySelectorAll('.nl a[href^="#"], .mn-link[href^="#"]').forEach(function (link) {
        link.classList.toggle('active', link.getAttribute('href') === '#' + sectionId);
    });
}

function updateActiveNav() {
    var sections = Array.from(document.querySelectorAll('section[id]'));
    if (!sections.length) return;

    var line = getScrollOffset();
    var current = sections[0].getAttribute('id');
    var matched = false;

  // Prefer the section that contains the nav line (most accurate while reading)
    for (var i = 0; i < sections.length; i++) {
        var rect = sections[i].getBoundingClientRect();
        if (rect.top <= line + 2 && rect.bottom > line + 2) {
            current = sections[i].getAttribute('id');
            matched = true;
            break;
        }
    }

    // Between sections or at page bottom: use the last section whose top passed the line
    if (!matched) {
        for (var j = sections.length - 1; j >= 0; j--) {
            var passed = sections[j].getBoundingClientRect();
            if (passed.top <= line + 2) {
                current = sections[j].getAttribute('id');
                break;
            }
        }
    }

    setActiveNav(current);
}

function initHashScroll() {
    var hash = window.location.hash;
    if (!hash || hash === '#') return;
    var target = document.querySelector(hash);
    if (!target) return;
    var id = hash.slice(1);
    if (id) setActiveNav(id);
    scrollToSection(target);
    window.setTimeout(updateActiveNav, 100);
    window.setTimeout(updateActiveNav, 450);
}

function initHashScrollInstant() {
    var hash = window.location.hash;
    if (!hash || hash === '#') return;
    var target = document.querySelector(hash);
    if (!target) return;
    var top = target.getBoundingClientRect().top + window.pageYOffset - getScrollOffset();
    window.scrollTo(0, Math.max(0, top));
    var id = hash.slice(1);
    if (id) setActiveNav(id);
    updateActiveNav();
}

function onScroll() {
    if (nav) nav.classList.toggle('s', window.scrollY > 30);
    if (siteHeader) siteHeader.classList.toggle('s', window.scrollY > 30);
    updateActiveNav();
}

window.addEventListener('scroll', onScroll, { passive: true });
window.addEventListener('load', function () {
    if (window.location.hash && window.location.hash !== '#') {
        initHashScrollInstant();
    } else {
        updateActiveNav();
    }
});
window.addEventListener('hashchange', function () {
    initHashScroll();
});

document.querySelectorAll('.nl a[href^="#"], .mn-link[href^="#"]').forEach(function (link) {
    link.addEventListener('click', function () {
        var id = (link.getAttribute('href') || '').slice(1);
        if (id) setActiveNav(id);
    });
});

updateActiveNav();

// Mobile menu toggle
let mo = false;
function tmn() {
    mo = !mo;
    var mn = document.getElementById('mn');
    var hb = document.querySelector('.hb');
    if (mn) {
        mn.classList.toggle('open', mo);
        mn.setAttribute('aria-hidden', mo ? 'false' : 'true');
    }
    if (hb) hb.setAttribute('aria-expanded', mo ? 'true' : 'false');
    document.body.classList.toggle('menu-open', mo);
    document.body.style.overflow = mo ? 'hidden' : '';
}

function cmn() {
    mo = false;
    var mn = document.getElementById('mn');
    var hb = document.querySelector('.hb');
    if (mn) {
        mn.classList.remove('open');
        mn.setAttribute('aria-hidden', 'true');
    }
    if (hb) hb.setAttribute('aria-expanded', 'false');
    document.body.classList.remove('menu-open');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && mo) cmn();
});

// Modal functions
function om(t) {
    document.getElementById('modal').classList.add('open');
    document.body.style.overflow = 'hidden';
    sw(t || 'login');
}

function cm() {
    document.getElementById('modal').classList.remove('open');
    document.body.style.overflow = '';
}

function ovc(e) {
    if (e.target === document.getElementById('modal')) cm();
}

function sw(t) {
    document.getElementById('lp').classList.toggle('active', t === 'login');
    document.getElementById('rp').classList.toggle('active', t === 'register');
    document.getElementById('lt').classList.toggle('active', t === 'login');
    document.getElementById('rt').classList.toggle('active', t === 'register');
}

// Close modal on Escape key
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') cm();
});

// Handle login - redirect to actual staff login page
function handleLogin() {
    window.location.href = 'Admin/login.php';
}

// Smooth scroll for anchor links
document.querySelectorAll('a[href^="#"]').forEach(function (a) {
    a.addEventListener('click', function (e) {
        var href = this.getAttribute('href');
        if (!href || href === '#') return;
        var t = document.querySelector(href);
        if (t) {
            e.preventDefault();
            var id = href.slice(1);
            if (id) setActiveNav(id);
            scrollToSection(t);
        }
    });
});

// Intersection Observer for reveal animations
const obs = new IntersectionObserver(entries => {
    entries.forEach(e => {
        if (e.isIntersecting) {
            e.target.classList.add('in');
            obs.unobserve(e.target);
        }
    });
}, { threshold: .1 });

document.querySelectorAll('.rv').forEach(el => obs.observe(el));
