<?php
session_start();
require_once __DIR__ . '/../backend/config/functions.php';
$isLoggedIn = isset($_SESSION['user_id']);
$roleName = $isLoggedIn ? ucfirst($_SESSION['role_name'] ?? 'User') : '';

$contactPhoneTel = '+264814469962';
$contactPhoneDisplay = '+264 81 446 9962';
$contactPhone2Tel = '+264812860173';
$contactPhone2Display = '+264 81 286 0173';
$contactWhatsApp = '264814469962';
$contactWhatsAppText = rawurlencode('Hi SV Auto, I would like to enquire about a repair or quote.');
$contactEmail = 'svautotruckrepair@gmail.com';

if (empty($_SESSION['contact_csrf'])) {
    $_SESSION['contact_csrf'] = bin2hex(random_bytes(32));
}
$contactCsrf = $_SESSION['contact_csrf'];
$contactFlash = $_SESSION['contact_flash'] ?? null;
unset($_SESSION['contact_flash']);

if (isset($_GET['contact']) && $_GET['contact'] === 'sent' && !$contactFlash) {
    $contactFlash = [
        'type' => 'success',
        'message' => 'Thank you — your message was sent. We will get back to you soon.',
    ];
}
$authLogos = publicAuthLogoUrls();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <script src="assets/js/site-theme-init.js"></script>
    <title>SV Auto | Expert Truck & Auto Repair — Windhoek</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/site-theme.css?v=7">
    <style>
        html { color-scheme: light; }
        html.site-theme-dark { color-scheme: dark; }
        :root {
            --orange: #ea580c;
            --orange-hover: #c2410c;
            --orange-glow: rgba(234, 88, 12, 0.28);
            --black: #000000;
            --white: #ffffff;
            --beige: #f5f3ee;
            --beige-deep: #e8e4dc;
            --cream: #faf8f4;
            --grey-bg: #f3f4f6;
            --text-muted: #3f464d;
            --text-soft: #5c6570;
            --font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
            --font-regular: 400;
            --font-medium: 500;
            --font-semibold: 600;
            --font-light: 400;
            --text-base: 1.0625rem;
            --text-sm: 0.9375rem;
            --text-xs: 0.8125rem;
            --leading: 1.65;
            --leading-relaxed: 1.75;
            --line: rgba(0, 0, 0, 0.08);
            --radius: 24px;
            --radius-sm: 16px;
            --radius-lg: 32px;
            --radius-xl: 40px;
            --radius-pill: 999px;
            --shadow: 0 24px 56px rgba(0, 0, 0, 0.08);
            --shadow-soft: 0 8px 28px rgba(0, 0, 0, 0.05);
            --nav-h: 74px;
            --header-h: var(--nav-h);
            --header-pad: 16px;
            --max: min(1520px, 96vw);
            --pad-x: clamp(1rem, 2.5vw, 2rem);
            --btn-h: 48px;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body {
            font-family: var(--font-family);
            font-size: var(--text-base);
            font-weight: var(--font-regular);
            color: var(--black);
            line-height: var(--leading);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
            overflow-x: hidden;
        }
        h1, h2, h3, h4, strong, .sn, .tn, .btn-signin, .btn-getstarted, .bg, .bp, .hero-cta, .bsub {
            font-weight: var(--font-semibold);
        }
        .sd, .sp, .te, .hero-lead, .fbr p, .hero-proof-text em,
        .clb, .cv, .fg label, .fg input, .fg textarea, .fg select, .fc a, .fbot p,
        .contact-form-note, .nl a {
            font-weight: var(--font-regular);
        }
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }
        /* Shared buttons — orange, white, black only */
        .btn-primary,
        .btn-getstarted,
        .bg,
        .bp,
        .hero-cta,
        .bsub {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: var(--btn-h);
            padding: 0 1.5rem;
            font-family: var(--font-family);
            font-size: var(--text-sm);
            font-weight: var(--font-medium);
            color: #ffffff;
            background: var(--orange);
            border: 2px solid var(--orange);
            border-radius: var(--radius-pill);
            box-shadow: 0 6px 20px var(--orange-glow);
            transition: background 0.2s, border-color 0.2s, transform 0.15s, box-shadow 0.2s;
        }
        .btn-primary:hover,
        .btn-getstarted:hover,
        .bg:hover,
        .bp:hover,
        .hero-cta:hover,
        .bsub:hover {
            background: var(--orange-hover);
            border-color: var(--orange-hover);
            transform: translateY(-2px);
            box-shadow: 0 10px 26px var(--orange-glow);
        }
        .btn-text {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: var(--btn-h);
            padding: 0 0.85rem;
            font-family: var(--font-family);
            font-size: var(--text-sm);
            font-weight: var(--font-medium);
            color: var(--black);
            background: transparent;
            border: none;
            border-radius: var(--radius-pill);
            transition: color 0.2s;
        }
        .btn-text:hover { color: var(--orange); }
        .btn-signin {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: var(--btn-h);
            padding: 0 1.35rem;
            font-family: var(--font-family);
            font-size: var(--text-sm);
            font-weight: var(--font-medium);
            color: var(--white);
            background: var(--black);
            border: 2px solid var(--black);
            border-radius: var(--radius-pill);
            cursor: pointer;
            transition: background 0.2s, border-color 0.2s, transform 0.15s, box-shadow 0.2s;
        }
        .btn-signin:hover {
            background: #1a1a1a;
            border-color: #1a1a1a;
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.18);
        }
        body > * { position: relative; z-index: 1; }
        .page-shell {
            width: 100%;
            max-width: var(--max);
            margin-left: auto;
            margin-right: auto;
            padding-left: var(--pad-x);
            padding-right: var(--pad-x);
        }
        img { max-width: 100%; display: block; }
        a { color: inherit; text-decoration: none; }
        button { font-family: inherit; cursor: pointer; border: none; }

        /* —— Site header (Orvion-style bar) —— */
        .site-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            padding: var(--header-pad) var(--pad-x) 0;
            background: transparent;
            pointer-events: none;
        }
        .site-header > * { pointer-events: auto; }
        #nav {
            height: var(--nav-h);
            max-width: var(--max);
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.98);
            border: 1px solid rgba(15, 23, 42, 0.05);
            border-radius: var(--radius-pill);
            box-shadow: var(--shadow-soft);
            transition: box-shadow 0.3s ease, transform 0.3s ease;
        }
        .site-header.s #nav {
            box-shadow: 0 12px 40px rgba(15, 23, 42, 0.11);
        }
        .nav-wrap {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.25rem;
            width: 100%;
            max-width: var(--max);
            height: 100%;
            margin: 0 auto;
            padding: 0 clamp(1rem, 2vw, 1.75rem);
        }
        .nl {
            display: flex;
            list-style: none;
            justify-content: flex-start;
            align-items: center;
            gap: clamp(0.85rem, 1.8vw, 1.65rem);
            flex: 1;
            min-width: 0;
        }
        .nav-right {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            flex-shrink: 0;
        }
        .nl a {
            font-size: var(--text-sm);
            font-weight: var(--font-medium);
            letter-spacing: 0;
            text-transform: none;
            color: var(--black);
            opacity: 0.85;
            padding: 0.35rem 0;
            transition: color 0.2s, opacity 0.2s;
        }
        .nl a:hover { color: var(--black); opacity: 1; }
        .nl a.active {
            color: var(--black);
            opacity: 1;
            font-weight: var(--font-semibold);
        }
        .nl a.active::after {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            bottom: -6px;
            height: 2px;
            background: var(--orange);
            border-radius: 2px;
        }
        .nl a { position: relative; }
        .nr {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            flex-shrink: 0;
        }
        .nav-phone { display: none; }
        .nr .role-tag {
            font-size: var(--text-sm);
            font-weight: var(--font-regular);
            color: var(--black);
        }
        .nav-auth {
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }
        .btn-getstarted { padding: 0 1.4rem; }
        .nav-brand {
            display: flex;
            align-items: center;
            flex-shrink: 0;
            line-height: 0;
            text-decoration: none;
            margin-right: 0.5rem;
        }
        .nav-brand__img {
            height: clamp(34px, 8vw, 40px);
            width: auto;
            max-width: min(148px, 42vw);
            object-fit: contain;
            object-position: left center;
            display: block;
        }
        .nav-brand__img--dark { display: none; }
        html.site-theme-dark .nav-brand__img--light { display: none; }
        html.site-theme-dark .nav-brand__img--dark { display: block; }
        .hb {
            display: none;
            flex-direction: column;
            justify-content: center;
            gap: 5px;
            background: none;
            padding: 8px;
            border-radius: var(--radius-sm);
            transition: background 0.2s;
        }
        .hb:hover { background: rgba(0, 0, 0, 0.05); }
        .hb[aria-expanded="true"] span:nth-child(1) {
            transform: translateY(7px) rotate(45deg);
        }
        .hb[aria-expanded="true"] span:nth-child(2) {
            opacity: 0;
        }
        .hb[aria-expanded="true"] span:nth-child(3) {
            transform: translateY(-7px) rotate(-45deg);
        }
        .hb span {
            display: block;
            width: 22px;
            height: 2px;
            background: var(--black);
            border-radius: 1px;
            transition: transform 0.25s ease, opacity 0.2s;
        }

        /* Mobile menu — slide-in panel */
        .mn {
            position: fixed;
            inset: 0;
            z-index: 200;
            pointer-events: none;
            visibility: hidden;
        }
        .mn.open {
            pointer-events: auto;
            visibility: visible;
        }
        .mn-backdrop {
            position: absolute;
            inset: 0;
            border: none;
            padding: 0;
            cursor: pointer;
            background: rgba(17, 24, 39, 0.42);
            opacity: 0;
            transition: opacity 0.32s ease;
        }
        .mn.open .mn-backdrop { opacity: 1; }
        .mn-panel {
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            width: min(320px, 88vw);
            display: flex;
            flex-direction: column;
            background: linear-gradient(180deg, var(--beige-deep) 0%, var(--beige) 22%, var(--beige) 100%);
            border-left: 1px solid var(--line);
            box-shadow: -16px 0 48px rgba(17, 24, 39, 0.14);
            transform: translateX(100%);
            transition: transform 0.34s cubic-bezier(0.4, 0, 0.2, 1);
            padding: calc(env(safe-area-inset-top, 0px) + 1rem) 1.25rem calc(env(safe-area-inset-bottom, 0px) + 1.25rem);
        }
        .mn.open .mn-panel { transform: translateX(0); }
        .mn-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 1.15rem;
            padding-bottom: 0.85rem;
            border-bottom: 1px solid var(--line);
            flex-shrink: 0;
        }
        .mn-head .nav-brand { margin-right: 0; }
        .mn-head .nav-brand__img {
            height: 40px;
            max-width: 160px;
        }
        .mn-close {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            border: 1px solid var(--line);
            border-radius: 50%;
            background: var(--white);
            color: var(--black);
            font-size: 1.35rem;
            line-height: 1;
            flex-shrink: 0;
            transition: background 0.2s, border-color 0.2s;
        }
        .mn-close:hover {
            background: var(--grey-bg);
            border-color: rgba(0, 0, 0, 0.12);
        }
        .mn-nav {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }
        .mn-link {
            display: flex;
            align-items: center;
            min-height: 48px;
            padding: 0 0.85rem;
            font-size: 1.0625rem;
            font-weight: var(--font-medium);
            color: var(--black);
            border-radius: var(--radius-sm);
            transition: background 0.2s, color 0.2s;
        }
        .mn-link:hover {
            background: rgba(255, 255, 255, 0.55);
        }
        .mn-link.active {
            background: var(--white);
            color: var(--black);
            font-weight: var(--font-semibold);
            box-shadow: var(--shadow-soft);
        }
        .mn-link.active::before {
            content: '';
            width: 3px;
            height: 1.1rem;
            margin-right: 0.65rem;
            margin-left: -0.15rem;
            background: var(--orange);
            border-radius: 2px;
            flex-shrink: 0;
        }
        .mn-foot {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 0.75rem;
            margin-top: auto;
            padding-top: 1.25rem;
            border-top: 1px solid var(--line);
        }
        body.menu-open .site-header .nav-brand {
            visibility: hidden;
        }
        body.menu-open .sticky-contact {
            display: none !important;
        }
        .mn-theme { justify-content: center; }
        .mn-cta {
            width: 100%;
            justify-content: center;
        }
        .mn-login {
            width: 100%;
        }
        body.menu-open { overflow: hidden; }

        /* —— Hero (full-width photo) —— */
        #home {
            position: relative;
            min-height: 100svh;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            padding: calc(var(--header-h) + var(--header-pad) + 1rem) var(--pad-x) clamp(1.5rem, 3vh, 2.25rem);
            background: transparent;
            overflow: hidden;
            width: 100%;
            max-width: none;
            box-sizing: border-box;
        }
        .hero-shell {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            width: 100%;
            max-width: var(--max);
            margin: 0 auto;
            padding: 0;
            background: transparent;
            border: none;
            border-radius: 0;
            box-shadow: none;
            min-height: 0;
        }
        .hero-stage {
            position: relative;
            width: 100%;
            aspect-ratio: 1051 / 1080;
            max-height: calc(100dvh - var(--header-h) - var(--header-pad) - 4rem);
            min-height: clamp(360px, 52vh, 520px);
            flex: 0 0 auto;
            border-radius: var(--radius-lg);
            overflow: hidden;
            border: 1px solid rgba(15, 23, 42, 0.06);
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.05);
        }
        @media (prefers-reduced-motion: reduce) {
            .mn-panel,
            .mn-backdrop { transition: none; }
        }
        @media (prefers-reduced-motion: reduce) {
            .hero-stage > img { transition: none; }
        }
        .hero-stage > img {
            position: absolute;
            top: 50%;
            left: 50%;
            min-width: 100%;
            min-height: 100%;
            width: auto;
            height: auto;
            max-width: none;
            object-fit: cover;
            object-position: center center;
            transform: translate(-50%, -50%);
        }
        .hero-stage::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(
                95deg,
                rgba(0, 0, 0, 0.82) 0%,
                rgba(0, 0, 0, 0.55) 38%,
                rgba(0, 0, 0, 0.22) 62%,
                rgba(0, 0, 0, 0.45) 100%
            );
            pointer-events: none;
            z-index: 0;
        }
        .hero-orvion-overlay {
            position: absolute;
            inset: 0;
            z-index: 1;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: clamp(2rem, 4vw, 3.25rem) clamp(1.75rem, 3.5vw, 3rem);
            max-width: min(40rem, 92%);
            margin-right: auto;
            pointer-events: none;
        }
        .hero-orvion-overlay a,
        .hero-orvion-overlay button {
            pointer-events: auto;
        }
        .hero-copy-block {
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
        }
        .hero-kicker {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            font-size: var(--text-xs);
            font-weight: var(--font-semibold);
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.92);
            margin-bottom: 1rem;
        }
        .hero-kicker::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--orange);
            flex-shrink: 0;
        }
        .hero-title {
            font-size: clamp(1.9rem, 3.9vw, 2.95rem);
            font-weight: var(--font-semibold);
            line-height: 1.12;
            letter-spacing: -0.035em;
            color: #ffffff;
            margin-bottom: 0;
        }
        .hero-divider {
            width: 100%;
            max-width: 12rem;
            height: 1px;
            border: none;
            background: rgba(255, 255, 255, 0.35);
            margin: 1.15rem 0 1.15rem;
        }
        .hero-lead {
            font-size: clamp(1rem, 1.2vw, 1.125rem);
            font-weight: var(--font-regular);
            color: rgba(255, 255, 255, 0.92);
            line-height: var(--leading-relaxed);
            margin-bottom: 0;
            max-width: 28rem;
        }
        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 1rem 1.5rem;
            margin-top: 1.75rem;
            padding-top: 0.25rem;
        }
        .hero-cta {
            min-width: 10.5rem;
            padding: 0 1.75rem;
        }
        .hero-cta-ghost {
            font-size: var(--text-sm);
            font-weight: var(--font-medium);
            color: #ffffff;
            opacity: 0.95;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0;
            border-bottom: 1px solid transparent;
            transition: color 0.2s, opacity 0.2s, border-color 0.2s;
        }
        .hero-cta-ghost:hover {
            color: #ffffff;
            opacity: 1;
            border-bottom-color: var(--orange);
        }
        .hero-cta-ghost i {
            font-size: 0.8rem;
            transition: transform 0.2s;
        }
        .hero-cta-ghost:hover i { transform: translateX(3px); }
        .hero-proof {
            display: flex;
            align-items: center;
            gap: 0.9rem;
            padding-top: 1.5rem;
            margin-top: 0;
            border-top: 1px solid rgba(255, 255, 255, 0.18);
        }
        .hero-proof-text {
            font-size: var(--text-sm);
            color: #ffffff;
            line-height: var(--leading);
            max-width: 18rem;
        }
        .hero-proof-text strong {
            display: block;
            font-weight: var(--font-semibold);
            color: #ffffff;
            font-size: var(--text-sm);
            letter-spacing: -0.01em;
        }
        .hero-proof-text em {
            font-style: normal;
            font-weight: var(--font-regular);
            color: rgba(255, 255, 255, 0.82);
        }
        .hero-avatars {
            display: flex;
            flex-shrink: 0;
        }
        .hero-avatars span {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, 0.95);
            background: var(--orange);
            margin-left: -11px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            color: var(--white);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        .hero-avatars span:first-child { margin-left: 0; background: var(--black); }
        .hero-avatars span:nth-child(2) { background: var(--orange); }
        .hero-avatars span:nth-child(3) { background: var(--black); opacity: 0.88; }

        /* —— Sections shared —— */
        section {
            padding: clamp(2rem, 3.5vw, 3rem) var(--pad-x);
            width: 100%;
            max-width: none;
            margin: 0;
        }
        section#home {
            padding-left: 0;
            padding-right: 0;
        }
        section > .section-inner,
        #about,
        #contact,
        #services {
            max-width: var(--max);
            margin-left: auto;
            margin-right: auto;
        }
        section[id]:not(#home) {
            scroll-margin-top: calc(var(--header-h) + var(--header-pad) + 12px);
        }
        #about {
            min-height: calc(100dvh - var(--header-h) - var(--header-pad) - 0.5rem);
            box-sizing: border-box;
        }
        .sl {
            display: inline-block;
            font-size: var(--text-xs);
            font-weight: var(--font-semibold);
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--orange);
            margin-bottom: 0.65rem;
        }
        section h2 {
            font-size: clamp(1.75rem, 2.8vw, 2.4rem);
            font-weight: var(--font-semibold);
            letter-spacing: -0.02em;
            color: var(--black);
            margin-bottom: 0.65rem;
            line-height: 1.2;
        }
        .sd {
            font-size: var(--text-base);
            font-weight: var(--font-regular);
            color: var(--text-muted);
            line-height: var(--leading-relaxed);
            max-width: 560px;
        }
        .sh-center {
            text-align: center;
            margin-bottom: 1.25rem;
        }
        .sh-center .sd { margin-bottom: 0; }
        .sh-center .sd { margin-left: auto; margin-right: auto; }

        /* Services + Contact — cream frame, two white panels */
        #services,
        #contact {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1.08fr);
            gap: clamp(1.5rem, 3vw, 2.25rem);
            align-items: stretch;
            background: var(--cream);
            border: 1px solid var(--line);
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
            padding: clamp(1rem, 2vw, 1.25rem) var(--pad-x) clamp(2rem, 3.5vw, 3rem);
            width: 100%;
            box-shadow: var(--shadow);
        }
        .contact-info-panel,
        .contact-form-panel,
        .services-info-panel,
        .services-offer-panel {
            background: var(--white);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: clamp(1.35rem, 2.5vw, 1.75rem) clamp(1.75rem, 3vw, 2.25rem) clamp(1.75rem, 3vw, 2.25rem);
            box-shadow: var(--shadow-soft);
            min-width: 0;
        }
        .contact-info-panel .sl,
        .services-info-panel .sl { margin-bottom: 0.35rem; }
        .contact-info-panel h2,
        .services-info-panel h2 {
            font-size: clamp(1.75rem, 3vw, 2.1rem);
            font-weight: var(--font-semibold);
            letter-spacing: -0.02em;
            margin-bottom: 0.5rem;
        }
        #contact h2::after,
        #services h2::after {
            content: '';
            display: block;
            width: 48px;
            height: 3px;
            background: var(--orange);
            border-radius: 2px;
            margin-top: 0.75rem;
        }
        .contact-info-panel .sd,
        .services-info-panel .sd {
            margin-bottom: 1.35rem;
            max-width: none;
        }
        .services-orbit-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 0.5rem 0 1.25rem;
            margin-bottom: 1.25rem;
            border-bottom: 1px solid var(--line);
        }
        .services-quote-selected {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
            padding: 1rem;
            background: var(--cream);
            border: 1px solid var(--line);
            border-radius: var(--radius-sm);
            font-size: var(--text-sm);
            color: var(--text-muted);
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .services-quote-selected.is-visible {
            border-color: rgba(234, 88, 12, 0.25);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
            color: var(--black);
        }
        .services-quote-selected__icon {
            flex-shrink: 0;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: rgba(234, 88, 12, 0.1);
            color: var(--orange);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
            opacity: 0.45;
        }
        .services-quote-selected.is-visible .services-quote-selected__icon {
            opacity: 1;
            background: var(--orange);
            color: #fff;
        }
        .services-quote-selected__body {
            flex: 1;
            min-width: 0;
        }
        .services-quote-selected__label {
            display: block;
            font-size: var(--text-xs);
            font-weight: var(--font-semibold);
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 0.25rem;
        }
        .services-quote-selected.is-visible .services-quote-selected__label {
            color: var(--orange);
        }
        #servicesQuoteSelectedText {
            display: block;
            font-weight: var(--font-medium);
            color: inherit;
            line-height: 1.35;
        }
        .services-quote-cta {
            display: inline-flex;
            width: 100%;
            justify-content: center;
            gap: 0.5rem;
            min-height: var(--btn-h);
            font-size: var(--text-sm);
        }
        .services-orbit-badge {
            text-align: center;
            padding-bottom: 0.25rem;
        }
        .services-orbit {
            position: relative;
            width: clamp(168px, 20vw, 188px);
            aspect-ratio: 1;
            margin: 0 auto;
        }
        .services-orbit-rings {
            position: absolute;
            inset: 0;
            pointer-events: none;
        }
        .services-orbit-ring {
            position: absolute;
            inset: 6%;
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 50%;
            pointer-events: none;
        }
        .services-orbit-ring:nth-child(2) {
            inset: 20%;
            opacity: 0.6;
        }
        .services-orbit-ring:nth-child(3) {
            inset: 34%;
            opacity: 0.35;
            border-style: dashed;
        }
        .services-orbit-rotator {
            position: absolute;
            inset: 0;
            z-index: 3;
            transition: transform 0.85s cubic-bezier(0.45, 0, 0.2, 1);
            will-change: transform;
        }
        a.services-orbit-node,
        .services-orbit-node.is-hub {
            position: absolute;
            left: 50%;
            top: 50%;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            z-index: 3;
        }
        a.services-orbit-node {
            --orbit-slot: 0deg;
            --orbit-r: 62px;
            width: 42px;
            height: 42px;
            margin-left: -21px;
            margin-top: -21px;
            background: var(--white);
            border: 1px solid rgba(15, 23, 42, 0.1);
            color: var(--text-muted);
            font-size: 0.82rem;
            box-shadow: 0 4px 16px rgba(15, 23, 42, 0.08);
            transform: rotate(var(--orbit-slot)) translateY(calc(-1 * var(--orbit-r))) rotate(calc(-1 * var(--orbit-slot)));
            transition: background 0.35s, color 0.35s, border-color 0.35s, box-shadow 0.35s, transform 0.35s;
        }
        a.services-orbit-node.is-active {
            background: var(--orange);
            border-color: var(--orange-hover);
            color: #ffffff;
            z-index: 5;
            transform: rotate(var(--orbit-slot)) translateY(calc(-1 * var(--orbit-r))) rotate(calc(-1 * var(--orbit-slot))) scale(1.1);
            box-shadow: 0 8px 24px rgba(234, 88, 12, 0.35);
            animation: orbit-node-glow 2s ease-in-out infinite;
        }
        @keyframes orbit-node-glow {
            0%, 100% { box-shadow: 0 8px 24px rgba(234, 88, 12, 0.35); }
            50% { box-shadow: 0 8px 28px rgba(234, 88, 12, 0.55), 0 0 0 6px rgba(234, 88, 12, 0.12); }
        }
        a.services-orbit-node:hover,
        a.services-orbit-node:focus-visible {
            color: var(--orange);
            background: var(--white);
            box-shadow: 0 8px 20px rgba(234, 88, 12, 0.15);
            outline: none;
        }
        a.services-orbit-node.is-active:hover,
        a.services-orbit-node.is-active:focus-visible {
            color: #ffffff;
            background: var(--orange-hover);
        }
        .services-orbit-node.is-hub {
            width: 54px;
            height: 54px;
            margin-left: -27px;
            margin-top: -27px;
            transform: none;
            background: linear-gradient(145deg, #1a1a1a 0%, #000 100%);
            color: var(--white);
            border: 2px solid rgba(255, 255, 255, 0.15);
            font-size: 1.05rem;
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.25);
            z-index: 4;
            pointer-events: none;
        }
        .services-orbit-active-label {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }
        .services-orbit-caption {
            margin: 1rem auto 0;
            max-width: 12rem;
        }
        .services-orbit-caption span {
            display: inline-block;
            padding: 0.4rem 0.85rem;
            font-size: 0.75rem;
            font-weight: var(--font-semibold);
            letter-spacing: 0.02em;
            color: var(--orange);
            background: rgba(234, 88, 12, 0.08);
            border: 1px solid rgba(234, 88, 12, 0.18);
            border-radius: var(--radius-pill);
            transition: background 0.35s, color 0.35s;
        }
        .services-orbit-caption.is-active span {
            background: var(--orange);
            color: #fff;
            border-color: var(--orange);
        }
        @media (prefers-reduced-motion: reduce) {
            .services-orbit-rotator { transition: none; }
            a.services-orbit-node.is-active { animation: none; }
        }
        #services .sg {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1.15rem;
        }
        #services a.sc {
            display: flex;
            flex-direction: column;
            min-height: 100%;
            text-decoration: none;
            color: inherit;
            background: var(--cream);
            border: 1px solid var(--line);
            border-radius: var(--radius-sm);
            padding: 1.25rem;
            transition: border-color 0.2s, box-shadow 0.2s, transform 0.2s;
            cursor: pointer;
        }
        #services a.sc:hover,
        #services a.sc.is-selected {
            border-color: rgba(234, 88, 12, 0.25);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
            transform: translateY(-2px);
            background: var(--cream);
        }
        #services a.sc.is-selected {
            border-color: rgba(234, 88, 12, 0.4);
            box-shadow: 0 6px 20px rgba(234, 88, 12, 0.1);
        }
        #services .si {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: rgba(234, 88, 12, 0.1);
            border: none;
            color: var(--orange);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
            margin-bottom: 0.85rem;
            transition: background 0.25s, color 0.25s;
        }
        #services a.sc:hover .si,
        #services a.sc.is-selected .si {
            background: var(--orange);
            color: var(--white);
            transform: scale(1.05);
        }
        #services .sn {
            font-size: 1.05rem;
            font-weight: var(--font-semibold);
            letter-spacing: -0.02em;
            color: var(--black);
            margin-bottom: 0.4rem;
            line-height: 1.3;
        }
        #services .sp {
            font-size: 0.875rem;
            color: var(--text-muted);
            line-height: 1.55;
            margin-bottom: 0;
            flex: 1;
        }
        #services .sc-card-foot {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            margin-top: 1.15rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(15, 23, 42, 0.06);
        }
        #services .stag {
            font-size: var(--text-xs);
            font-weight: var(--font-semibold);
            letter-spacing: 0.1em;
            text-transform: uppercase;
            padding: 0;
            background: transparent;
            color: var(--text-muted);
            border: none;
            border-radius: 0;
        }
        #services a.sc:hover .stag,
        #services a.sc.is-selected .stag {
            background: rgba(234, 88, 12, 0.1);
            color: var(--orange);
        }
        #services .sc-quote {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.8125rem;
            font-weight: var(--font-semibold);
            color: var(--orange);
            white-space: nowrap;
        }
        #services a.sc:hover .sc-quote {
            text-decoration: none;
        }
        #services a.sc:hover .sc-quote i {
            transform: translateX(3px);
        }
        #services .sc-quote i {
            font-size: 0.7rem;
            transition: transform 0.2s;
        }
        @media (max-width: 640px) {
            #services .sg {
                grid-template-columns: 1fr;
            }
        }

        /* About */
        section#about {
            margin-bottom: clamp(1rem, 2vw, 1.5rem);
        }
        #about {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: clamp(2rem, 4vw, 3.5rem);
            align-items: center;
            background: var(--white);
            border: 1px solid var(--line);
            border-radius: var(--radius-lg);
            padding: clamp(2rem, 4vw, 3rem);
            box-shadow: var(--shadow-soft);
        }
        #about > .rv.d1 {
            padding: 0.25rem 0;
        }
        #about > .rv:first-child {
            position: relative;
            padding: 14px 14px 0 0;
        }
        #about > .rv:first-child::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 14px;
            border-radius: var(--radius);
            background: linear-gradient(135deg, rgba(234, 88, 12, 0.14) 0%, rgba(234, 88, 12, 0.04) 100%);
            border: 2px solid rgba(234, 88, 12, 0.18);
            z-index: 0;
            pointer-events: none;
        }
        #about > .rv:first-child::after {
            content: '';
            position: absolute;
            top: -8px;
            right: 20px;
            width: 72px;
            height: 72px;
            border-radius: var(--radius-sm);
            background: var(--orange);
            opacity: 0.12;
            z-index: 0;
            pointer-events: none;
        }
        #about img {
            position: relative;
            z-index: 1;
            width: 100%;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
        }

        /* Contact */
        .contact-cards {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin-bottom: 1.35rem;
        }
        .contact-card {
            display: flex;
            gap: 0.75rem;
            align-items: flex-start;
            padding: 1rem;
            background: var(--cream);
            border: 1px solid var(--line);
            border-radius: var(--radius-sm);
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .contact-card:hover {
            border-color: rgba(234, 88, 12, 0.25);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
        }
        .contact-card--wide { grid-column: 1 / -1; }
        .cic {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: rgba(234, 88, 12, 0.1);
            color: var(--orange);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 0.95rem;
        }
        .clb {
            font-size: var(--text-xs);
            font-weight: var(--font-semibold);
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 0.25rem;
        }
        .cv { font-size: var(--text-sm); color: var(--black); line-height: var(--leading); font-weight: var(--font-regular); }
        .cv a {
            color: inherit;
            text-decoration: none;
            transition: color 0.2s;
        }
        .cv a:hover { color: var(--orange); }
        .contact-actions-primary {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.65rem;
            margin-bottom: 0.75rem;
        }
        .contact-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            min-height: 48px;
            padding: 0 1rem;
            font-size: var(--text-sm);
            font-weight: var(--font-medium);
            border-radius: var(--radius-pill);
            text-decoration: none;
            transition: background 0.2s, border-color 0.2s, transform 0.15s, box-shadow 0.2s;
        }
        .contact-action--call {
            background: var(--black);
            color: var(--white);
            border: 2px solid var(--black);
        }
        .contact-action--call:hover {
            background: #1a1a1a;
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.15);
        }
        .contact-action--wa {
            background: #25d366;
            color: var(--white);
            border: 2px solid #25d366;
        }
        .contact-action--wa:hover {
            background: #1fb855;
            border-color: #1fb855;
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(37, 211, 102, 0.28);
        }
        .contact-map-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
            width: 100%;
            min-height: 44px;
            font-size: var(--text-sm);
            font-weight: var(--font-medium);
            color: var(--black);
            background: var(--white);
            border: 1px solid var(--line);
            border-radius: var(--radius-pill);
            text-decoration: none;
            transition: border-color 0.2s, background 0.2s;
        }
        .contact-map-btn:hover {
            border-color: var(--orange);
            background: rgba(234, 88, 12, 0.05);
            color: var(--orange);
        }
        .contact-form-head {
            text-align: center;
            margin-bottom: 1.15rem;
        }
        .contact-form-head .sl {
            display: block;
            margin-bottom: 0.35rem;
        }
        .contact-form-head h3 {
            font-size: 1.4rem;
            font-weight: var(--font-semibold);
            letter-spacing: -0.02em;
            margin: 0.25rem 0 0;
            color: var(--black);
        }
        .contact-form-note {
            font-size: var(--text-sm);
            font-weight: var(--font-regular);
            color: var(--text-muted);
            margin-top: 0.45rem;
            line-height: var(--leading);
        }
        .contact-service-chip {
            display: none;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            padding: 0.55rem 0.85rem;
            background: rgba(234, 88, 12, 0.08);
            border: 1px solid rgba(234, 88, 12, 0.25);
            border-radius: var(--radius-pill);
            font-size: var(--text-sm);
            color: var(--black);
        }
        .contact-service-chip.is-visible { display: inline-flex; }
        .contact-service-chip button {
            margin-left: auto;
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 1rem;
            line-height: 1;
            padding: 0.15rem;
        }
        .contact-service-chip button:hover { color: var(--black); }
        .fr { display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; }
        .fg { margin-bottom: 0.95rem; }
        .fg label {
            display: block;
            font-size: var(--text-sm);
            font-weight: var(--font-medium);
            color: var(--black);
            margin-bottom: 0.4rem;
            padding-left: 0.1rem;
        }
        .fg label .req { color: var(--orange); }
        .fg input,
        .fg textarea,
        .fg select {
            width: 100%;
            min-height: 48px;
            padding: 0 1rem;
            border: 1px solid transparent;
            border-radius: var(--radius-pill);
            font-family: inherit;
            font-size: var(--text-sm);
            font-weight: var(--font-regular);
            color: var(--black);
            background: var(--grey-bg);
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
        }
        .fg textarea {
            min-height: 120px;
            padding: 0.85rem 1rem;
            border-radius: var(--radius-sm);
            resize: vertical;
        }
        .fg select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%23666' d='M1 1l5 5 5-5'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            padding-right: 2.25rem;
        }
        .fg input:hover,
        .fg textarea:hover,
        .fg select:hover {
            background: #eceef0;
            border-color: var(--line);
        }
        .fg input:focus,
        .fg textarea:focus,
        .fg select:focus {
            outline: none;
            border-color: var(--black);
            background: var(--white);
            box-shadow: 0 0 0 1px var(--black), 0 0 0 4px rgba(234, 88, 12, 0.12);
        }
        .contact-form-panel .bsub {
            width: 100%;
            margin-top: 0.35rem;
            min-height: 52px;
        }
        .contact-alert {
            padding: 0.85rem 1rem;
            border-radius: var(--radius-sm);
            font-size: var(--text-sm);
            font-weight: var(--font-regular);
            margin-bottom: 1.15rem;
            border: 1px solid var(--line);
        }
        .contact-alert--success {
            background: rgba(234, 88, 12, 0.08);
            border-color: rgba(234, 88, 12, 0.35);
            color: var(--black);
        }
        .contact-alert--error {
            background: var(--grey-bg);
            border-color: var(--black);
        }
        .hp-field {
            position: absolute;
            left: -9999px;
            width: 1px;
            height: 1px;
            overflow: hidden;
        }
        .sticky-contact {
            display: none;
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 90;
            padding: 0.55rem var(--pad-x) calc(0.55rem + env(safe-area-inset-bottom, 0px));
            background: rgba(245, 243, 238, 0.96);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-top: 1px solid var(--line);
            box-shadow: 0 -8px 28px rgba(0, 0, 0, 0.08);
        }
        .sticky-contact-inner {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.65rem;
            max-width: var(--max);
            margin: 0 auto;
        }
        .sticky-contact a {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            min-height: 44px;
            padding: 0 0.5rem;
            font-size: var(--text-xs);
            font-weight: var(--font-medium);
            color: var(--black);
            background: var(--white);
            border: 1px solid var(--line);
            border-radius: var(--radius-pill);
            text-decoration: none;
        }
        .sticky-contact a.sticky-wa {
            background: #25d366;
            border-color: #25d366;
            color: var(--white);
        }
        .sticky-contact a.sticky-wa:hover {
            background: #1fb855;
        }

        /* Footer — orange, white, black only */
        footer {
            background: #000000;
            color: #ffffff;
            padding: 0;
            border-top: 3px solid var(--orange);
        }
        .footer-shell {
            max-width: var(--max);
            margin: 0 auto;
            padding: clamp(2.5rem, 5vw, 3.25rem) var(--pad-x) clamp(1.5rem, 3vw, 2rem);
        }
        .fg2 {
            display: grid;
            grid-template-columns: minmax(200px, 1.45fr) repeat(3, minmax(120px, 1fr));
            gap: clamp(1.75rem, 4vw, 3rem);
            align-items: start;
            padding-bottom: clamp(2rem, 4vw, 2.75rem);
            border-bottom: 1px solid #ffffff;
            border-bottom-color: rgba(255, 255, 255, 0.12);
        }
        .fbr {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 0;
        }
        .footer-brand-name {
            display: inline-block;
            font-size: 1.2rem;
            font-weight: var(--font-semibold);
            color: #ffffff;
            margin-bottom: 1rem;
            letter-spacing: -0.02em;
        }
        .footer-brand-name:hover {
            color: var(--orange);
        }
        .fbr p {
            font-size: var(--text-sm);
            color: #ffffff;
            opacity: 0.88;
            line-height: var(--leading);
            margin: 0 0 1.25rem;
            max-width: 300px;
        }
        .footer-social {
            display: flex;
            gap: 0.6rem;
            margin-top: 0.15rem;
        }
        .footer-social a {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            border: 1px solid #ffffff;
            border-color: rgba(255, 255, 255, 0.35);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-size: 0.85rem;
            transition: background 0.2s, border-color 0.2s, color 0.2s;
        }
        .footer-social a:hover {
            background: var(--orange);
            border-color: var(--orange);
            color: #ffffff;
        }
        .fc {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }
        .fc h4 {
            font-size: var(--text-xs);
            font-weight: var(--font-semibold);
            letter-spacing: 0.12em;
            text-transform: uppercase;
            margin: 0 0 1.1rem;
            padding-bottom: 0.65rem;
            color: #ffffff;
            opacity: 0.78;
            border-bottom: 2px solid var(--orange);
            width: 100%;
            max-width: 8rem;
        }
        .fc ul {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 0.55rem;
        }
        .fc li { margin: 0; }
        .fc a {
            font-size: var(--text-sm);
            font-weight: var(--font-regular);
            color: #ffffff;
            opacity: 0.92;
            transition: color 0.2s, opacity 0.2s;
        }
        .fc a:hover {
            color: var(--orange);
            opacity: 1;
        }
        .footer-meta {
            padding: clamp(1.35rem, 3vw, 1.75rem) 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
        }
        .footer-meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem 2rem;
            max-width: 52rem;
            margin: 0 auto;
            align-items: center;
        }
        .footer-meta-item {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.55rem;
            font-size: var(--text-sm);
            font-weight: var(--font-regular);
            color: #ffffff;
            opacity: 0.88;
            text-align: center;
        }
        .footer-meta-item i {
            color: var(--orange);
            font-size: 0.9rem;
            flex-shrink: 0;
        }
        .footer-meta-item a {
            color: #ffffff;
            opacity: 0.88;
            transition: color 0.2s;
        }
        .footer-meta-item a:hover {
            color: var(--orange);
            opacity: 1;
        }
        .fbot {
            padding-top: clamp(1.25rem, 2.5vw, 1.65rem);
            text-align: center;
            font-size: var(--text-xs);
            font-weight: var(--font-regular);
            line-height: var(--leading);
            max-width: 52rem;
            margin: 0 auto;
        }
        .fbot p {
            margin: 0;
            color: #ffffff;
            opacity: 0.72;
        }

        /* Scroll reveal */
        .rv {
            opacity: 0;
            transform: translateY(24px);
            transition: opacity 0.6s ease, transform 0.6s ease;
        }
        .rv.vis {
            opacity: 1;
            transform: translateY(0);
        }
        .d1 { transition-delay: 0.1s; }
        .d2 { transition-delay: 0.2s; }

        @media (max-width: 1024px) {
            .hero-stage {
                min-height: clamp(340px, 48vh, 480px);
            }
            .hero-orvion-overlay {
                max-width: 100%;
            }
            #about, #contact, #services { grid-template-columns: 1fr; }
            .fg2 { grid-template-columns: 1fr 1fr; gap: 2rem; }
            .footer-meta-grid { grid-template-columns: 1fr; gap: 0.85rem; }
        }
        @media (max-width: 900px) {
            .nl { display: none; }
            .nav-wrap { justify-content: space-between; }
            .nav-brand { display: flex; }
            .site-theme-toggle { display: none; }
            .sticky-contact { display: block; }
            body { padding-bottom: calc(4.75rem + env(safe-area-inset-bottom, 0px)); }
            .hero-orvion-overlay {
                padding-bottom: clamp(2.75rem, 7vw, 3.5rem);
            }
            .hero-proof { margin-bottom: 0.35rem; }
        }
        @media (min-width: 901px) {
            .nav-brand { display: flex; }
        }
        @media (max-width: 768px) {
            :root { --nav-h: 64px; --header-pad: 10px; --pad-x: 1.25rem; }
            .nav-wrap { padding: 0 1rem; }
            .hb { display: flex; }
            #home {
                min-height: auto;
                padding-bottom: 1.25rem;
            }
            .hero-stage {
                min-height: clamp(400px, 58vh, 560px);
            }
            .hero-stage::after {
                background: linear-gradient(
                    180deg,
                    rgba(0, 0, 0, 0.52) 0%,
                    rgba(0, 0, 0, 0.28) 32%,
                    rgba(0, 0, 0, 0.68) 68%,
                    rgba(0, 0, 0, 0.92) 100%
                );
            }
            .hero-orvion-overlay {
                padding: 1.35rem 1.35rem 3.25rem;
                max-width: 100%;
            }
            .hero-proof {
                max-width: 100%;
                flex-wrap: wrap;
                background: rgba(0, 0, 0, 0.42);
                backdrop-filter: blur(8px);
                -webkit-backdrop-filter: blur(8px);
                padding: 0.85rem 1rem;
                border-radius: 14px;
                border-top: none;
                margin-top: 0.75rem;
            }
            .hero-copy-block { justify-content: flex-end; }
            .hero-kicker,
            .hero-title,
            .hero-lead,
            .hero-proof-text {
                text-shadow: 0 2px 14px rgba(0, 0, 0, 0.65);
            }
            .hero-title {
                font-size: clamp(1.55rem, 6.5vw, 1.85rem);
                line-height: 1.15;
            }
            .hero-lead {
                font-size: 0.9375rem;
                color: rgba(255, 255, 255, 0.96);
                max-width: none;
            }
            .hero-cta-ghost {
                justify-content: center;
                min-height: 48px;
                padding: 0.65rem 1.25rem;
                background: rgba(0, 0, 0, 0.45);
                border: 1px solid rgba(255, 255, 255, 0.42);
                border-radius: var(--radius-pill);
                backdrop-filter: blur(8px);
                -webkit-backdrop-filter: blur(8px);
                opacity: 1;
            }
            .hero-cta-ghost:hover {
                background: rgba(0, 0, 0, 0.58);
                border-bottom-color: rgba(255, 255, 255, 0.42);
            }
            .hero-proof-text em {
                color: rgba(255, 255, 255, 0.9);
            }
            #services .sg { grid-template-columns: 1fr; }
            .tg { grid-template-columns: 1fr; }
            #contact { margin-bottom: 1.5rem; }
            .contact-actions-primary { grid-template-columns: 1fr; }
            .fr { grid-template-columns: 1fr; }
            .fg2 { grid-template-columns: 1fr; gap: 2rem; }
            .fc h4 { max-width: none; }
            .footer-meta-grid { grid-template-columns: 1fr; }
            .footer-meta-item { justify-content: flex-start; text-align: left; }
        }
        @media (max-width: 480px) {
            .hero-actions {
                flex-direction: column;
                align-items: stretch;
                width: 100%;
                gap: 0.85rem;
            }
            .hero-cta {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

<div class="mn" id="mn" aria-hidden="true">
    <button type="button" class="mn-backdrop" onclick="cmn()" aria-label="Close menu"></button>
    <div class="mn-panel" role="dialog" aria-modal="true" aria-label="Site menu">
        <div class="mn-head">
            <a href="#home" class="nav-brand" onclick="cmn()" aria-label="SV Auto Truck Repair — Home">
                <img class="nav-brand__img nav-brand__img--light" src="<?= htmlspecialchars($authLogos['light'], ENT_QUOTES, 'UTF-8') ?>" alt="SV Auto Truck Repair">
                <img class="nav-brand__img nav-brand__img--dark" src="<?= htmlspecialchars($authLogos['dark'], ENT_QUOTES, 'UTF-8') ?>" alt="">
            </a>
            <button type="button" class="mn-close" onclick="cmn()" aria-label="Close menu">&times;</button>
        </div>
        <nav class="mn-nav" aria-label="Mobile">
            <a href="#home" class="mn-link" onclick="cmn()">Home</a>
            <a href="#services" class="mn-link" onclick="cmn()">Services</a>
            <a href="#about" class="mn-link" onclick="cmn()">About</a>
            <a href="#contact" class="mn-link" onclick="cmn()">Contact</a>
        </nav>
        <div class="mn-foot">
            <div class="site-theme-toggle mn-theme" role="group" aria-label="Theme">
                <button type="button" class="site-theme-btn" data-site-theme="light" aria-label="Light mode" title="Light"><i class="fas fa-sun" aria-hidden="true"></i></button>
                <button type="button" class="site-theme-btn" data-site-theme="dark" aria-label="Dark mode" title="Dark"><i class="fas fa-moon" aria-hidden="true"></i></button>
            </div>
            <?php if ($isLoggedIn): ?>
            <button type="button" class="bp mn-cta" onclick="window.location.href='Admin/dashboard.php';cmn()">Open Dashboard</button>
            <button type="button" class="btn-signin mn-login" onclick="window.location.href='Admin/Auth/logout.php';cmn()">Sign Out</button>
            <?php else: ?>
            <a href="Admin/Auth/register.php" class="hero-cta btn-primary mn-cta">Get Started</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<header class="site-header" id="siteHeader">
<nav id="nav">
    <div class="nav-wrap">
    <a href="#home" class="nav-brand" aria-label="SV Auto Truck Repair — Home">
        <img class="nav-brand__img nav-brand__img--light" src="<?= htmlspecialchars($authLogos['light'], ENT_QUOTES, 'UTF-8') ?>" alt="SV Auto Truck Repair">
        <img class="nav-brand__img nav-brand__img--dark" src="<?= htmlspecialchars($authLogos['dark'], ENT_QUOTES, 'UTF-8') ?>" alt="">
    </a>
    <ul class="nl">
        <li><a href="#home">Home</a></li>
        <li><a href="#services">Services</a></li>
        <li><a href="#about">About</a></li>
        <li><a href="#contact">Contact</a></li>
    </ul>
    <div class="nav-right">
        <div class="nr">
            <div class="nav-auth">
            <div class="site-theme-toggle" role="group" aria-label="Theme">
                <button type="button" class="site-theme-btn" data-site-theme="light" aria-label="Light mode" title="Light"><i class="fas fa-sun" aria-hidden="true"></i></button>
                <button type="button" class="site-theme-btn" data-site-theme="dark" aria-label="Dark mode" title="Dark"><i class="fas fa-moon" aria-hidden="true"></i></button>
            </div>
        <?php if ($isLoggedIn): ?>
        <span class="role-tag"><?= htmlspecialchars($roleName) ?></span>
        <button class="bg btn-primary" onclick="window.location.href='Admin/Auth/logout.php'">Sign Out</button>
        <?php else: ?>
        <button type="button" class="btn-signin" onclick="window.location.href='login.php'">Login</button>
        <?php endif; ?>
            </div>
        </div>
        <button type="button" class="hb" onclick="tmn()" aria-label="Menu" aria-expanded="false" aria-controls="mn">
            <span></span><span></span><span></span>
        </button>
    </div>
    </div>
</nav>
</header>

<section id="home">
    <div class="hero-shell">
    <div class="hero-stage">
            <img src="<?= htmlspecialchars(publicAssetUrl('assets/images/svlanding.jpeg'), ENT_QUOTES, 'UTF-8') ?>" alt="SV Auto fleet and workshop — Windhoek">
            <div class="hero-orvion-overlay">
                <div class="hero-copy-block">
                    <span class="hero-kicker">Windhoek · Namibia</span>
                    <h1 class="hero-title">Expert truck &amp; auto repair for your fleet</h1>
                    <hr class="hero-divider" aria-hidden="true">
                    <p class="hero-lead">Professional electrical, mechanical, A/C, and diagnostics — from inspection to road-ready. Trusted workshop service for trucks, fleets, and passenger vehicles.</p>
                    <div class="hero-actions">
                        <?php if ($isLoggedIn): ?>
                        <a href="Admin/dashboard.php" class="hero-cta btn-primary">Open Dashboard</a>
                        <?php else: ?>
                        <a href="Admin/Auth/register.php" class="hero-cta btn-primary">Get Started</a>
                        <a href="#services" class="hero-cta-ghost">View services <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="hero-proof">
                    <div class="hero-avatars" aria-hidden="true">
                        <span><i class="fas fa-truck" aria-hidden="true"></i></span>
                        <span><i class="fas fa-wrench" aria-hidden="true"></i></span>
                        <span><i class="fas fa-shield-alt" aria-hidden="true"></i></span>
                    </div>
                    <p class="hero-proof-text">
                        <strong>Trusted in Windhoek &amp; Namibia</strong>
                        <em>Professional fleet &amp; auto workshop service</em>
                    </p>
                </div>
            </div>
    </div>
    </div>
</section>

<section id="services">
    <aside class="services-info-panel rv">
        <span class="sl">Services</span>
        <h2>Professional workshop services</h2>
        <p class="sd">Electrical, mechanical, A/C, and fitments for trucks, fleets, and passenger vehicles in Windhoek.</p>
        <div class="services-orbit-wrap">
            <div class="services-orbit-badge">
                <div class="services-orbit" id="servicesOrbit" role="group" aria-label="Core workshop services">
                    <div class="services-orbit-rings" aria-hidden="true">
                        <div class="services-orbit-ring"></div>
                        <div class="services-orbit-ring"></div>
                        <div class="services-orbit-ring"></div>
                    </div>
                    <span class="services-orbit-active-label" id="servicesOrbitActiveLabel" aria-live="polite"></span>
                    <span class="services-orbit-node is-hub" aria-hidden="true"><i class="fas fa-truck"></i></span>
                    <div class="services-orbit-rotator" id="servicesOrbitRotator">
                        <a href="#contact" class="services-orbit-node" style="--orbit-slot: 0deg" data-service="Auto Electric / Electronics" data-label="Auto Electric" data-icon="fa-bolt" title="Auto Electric / Electronics"><i class="fas fa-bolt" aria-hidden="true"></i><span class="sr-only">Auto Electric</span></a>
                        <a href="#contact" class="services-orbit-node" style="--orbit-slot: 90deg" data-service="Mechanical Services" data-label="Mechanical" data-icon="fa-wrench" title="Mechanical Services"><i class="fas fa-wrench" aria-hidden="true"></i><span class="sr-only">Mechanical</span></a>
                        <a href="#contact" class="services-orbit-node" style="--orbit-slot: 180deg" data-service="Accessories &amp; Fitments" data-label="Accessories" data-icon="fa-plug" title="Accessories &amp; Fitments"><i class="fas fa-plug" aria-hidden="true"></i><span class="sr-only">Accessories</span></a>
                        <a href="#contact" class="services-orbit-node" style="--orbit-slot: 270deg" data-service="A/C — Trucks &amp; Cars" data-label="A/C" data-icon="fa-snowflake" title="A/C — Trucks &amp; Cars"><i class="fas fa-snowflake" aria-hidden="true"></i><span class="sr-only">A/C</span></a>
                    </div>
                </div>
                <p class="services-orbit-caption" id="servicesOrbitCaption"><span id="servicesOrbitCaptionText">Select a service</span></p>
            </div>
        </div>
        <div class="services-quote-selected" id="servicesQuoteSelected" aria-live="polite">
            <span class="services-quote-selected__icon" id="servicesQuoteSelectedIcon" aria-hidden="true"><i class="fas fa-hand-pointer"></i></span>
            <div class="services-quote-selected__body">
                <span class="services-quote-selected__label">Your selection</span>
                <span id="servicesQuoteSelectedText">No service selected yet</span>
            </div>
        </div>
        <a href="#contact" class="services-quote-cta btn-primary" id="servicesQuoteCta">Continue to contact <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
    </aside>
    <div class="services-offer-panel rv d1">
        <div class="contact-form-head">
            <span class="clb">Our services</span>
            <h3>Browse &amp; select a service</h3>
            <p class="contact-form-note">Tap a card to choose, then continue to contact when you are ready.</p>
        </div>
        <div class="sg">
        <a href="#contact" class="sc" data-service="Auto Electric / Electronics" data-icon="fa-bolt">
            <div class="si"><i class="fas fa-bolt"></i></div>
            <div class="sn">Auto Electric / Electronics</div>
            <p class="sp">Diagnostics and repair of electrical systems, wiring, sensors, and electronic components.</p>
            <div class="sc-card-foot">
            <span class="stag">Electrical</span>
            <span class="sc-quote">Get quote <i class="fas fa-arrow-right" aria-hidden="true"></i></span>
            </div>
        </a>
        <a href="#contact" class="sc" data-service="Mechanical Services" data-icon="fa-wrench">
            <div class="si"><i class="fas fa-wrench"></i></div>
            <div class="sn">Mechanical Services</div>
            <p class="sp">Engine, transmission, brakes, suspension, and routine maintenance.</p>
            <div class="sc-card-foot">
            <span class="stag">Mechanical</span>
            <span class="sc-quote">Get quote <i class="fas fa-arrow-right" aria-hidden="true"></i></span>
            </div>
        </a>
        <a href="#contact" class="sc" data-service="Accessories &amp; Fitments" data-icon="fa-plug">
            <div class="si"><i class="fas fa-plug"></i></div>
            <div class="sn">Accessories &amp; Fitments</div>
            <p class="sp">Installation of accessories, audio, alarms, and custom modifications.</p>
            <div class="sc-card-foot">
            <span class="stag">Accessories</span>
            <span class="sc-quote">Get quote <i class="fas fa-arrow-right" aria-hidden="true"></i></span>
            </div>
        </a>
        <a href="#contact" class="sc" data-service="A/C — Trucks &amp; Cars" data-icon="fa-snowflake">
            <div class="si"><i class="fas fa-snowflake"></i></div>
            <div class="sn">A/C — Trucks &amp; Cars</div>
            <p class="sp">Air conditioning repair, regas, and maintenance for all vehicle types.</p>
            <div class="sc-card-foot">
            <span class="stag">A/C</span>
            <span class="sc-quote">Get quote <i class="fas fa-arrow-right" aria-hidden="true"></i></span>
            </div>
        </a>
        </div>
    </div>
</section>

<section id="about">
    <div class="rv">
        <img src="<?= htmlspecialchars(publicAssetUrl('assets/images/about.jpeg'), ENT_QUOTES, 'UTF-8') ?>" alt="SV Auto workshop">
    </div>
    <div class="rv d1">
        <span class="sl">About Us</span>
        <h2>Your trusted truck &amp; auto repair experts</h2>
        <p class="sd">SV Auto provides professional repair and maintenance in Windhoek. Expert technicians, modern equipment, and quality service keep your vehicles running smoothly.</p>
    </div>
</section>

<section id="contact">
    <div class="contact-info-panel rv">
        <span class="sl">Get In Touch</span>
        <h2>Contact us</h2>
        <p class="sd">Visit our workshop or reach out — we respond to fleet enquiries, quotes, and general repair requests.</p>
        <div class="contact-cards">
            <article class="contact-card contact-card--wide">
                <div class="cic"><i class="fas fa-map-marker-alt" aria-hidden="true"></i></div>
                <div>
                    <div class="clb">Location</div>
                    <div class="cv">
                        <a href="https://www.google.com/maps/search/?api=1&query=Lafrenz+Industrial+Rensburger+Street+Windhoek+Namibia" target="_blank" rel="noopener">
                            Lafrenz Industrial, Rensburger Street, Erf 174LL, Unit 18<br>
                            P O Box 21292, Windhoek, Namibia
                        </a>
                    </div>
                </div>
            </article>
            <article class="contact-card">
                <div class="cic"><i class="fas fa-phone-alt" aria-hidden="true"></i></div>
                <div>
                    <div class="clb">Phone</div>
                    <div class="cv">
                        <a href="tel:<?= htmlspecialchars($contactPhoneTel, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($contactPhoneDisplay, ENT_QUOTES, 'UTF-8') ?></a><br>
                        <a href="tel:<?= htmlspecialchars($contactPhone2Tel, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($contactPhone2Display, ENT_QUOTES, 'UTF-8') ?></a>
                    </div>
                </div>
            </article>
            <article class="contact-card">
                <div class="cic"><i class="fas fa-clock" aria-hidden="true"></i></div>
                <div>
                    <div class="clb">Workshop hours</div>
                    <div class="cv">Mon – Fri: 08:00 – 17:00<br><span style="opacity:0.72">Sat – Sun: Closed</span></div>
                </div>
            </article>
            <article class="contact-card contact-card--wide">
                <div class="cic"><i class="fas fa-envelope" aria-hidden="true"></i></div>
                <div>
                    <div class="clb">Email</div>
                    <div class="cv">
                        <a href="mailto:<?= htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8') ?></a>
                    </div>
                </div>
            </article>
        </div>
        <div class="contact-actions-primary">
            <a href="tel:<?= htmlspecialchars($contactPhoneTel, ENT_QUOTES, 'UTF-8') ?>" class="contact-action contact-action--call">
                <i class="fas fa-phone" aria-hidden="true"></i> Call now
            </a>
            <a href="https://wa.me/<?= htmlspecialchars($contactWhatsApp, ENT_QUOTES, 'UTF-8') ?>?text=<?= $contactWhatsAppText ?>" class="contact-action contact-action--wa" target="_blank" rel="noopener noreferrer">
                <i class="fab fa-whatsapp" aria-hidden="true"></i> WhatsApp
            </a>
        </div>
        <a href="https://www.google.com/maps/search/?api=1&query=Lafrenz+Industrial+Rensburger+Street+Windhoek+Namibia" class="contact-map-btn" target="_blank" rel="noopener noreferrer">
            <i class="fas fa-directions" aria-hidden="true"></i> Open in Google Maps
        </a>
    </div>
    <div class="contact-form-panel rv d1">
        <div class="contact-form-head">
            <span class="sl">Send message</span>
            <h3>Request a quote or ask a question</h3>
            <p class="contact-form-note">We typically respond within one business day.</p>
        </div>
        <?php if ($contactFlash): ?>
        <div class="contact-alert contact-alert--<?= htmlspecialchars($contactFlash['type'], ENT_QUOTES, 'UTF-8') ?>" role="status">
            <?= htmlspecialchars($contactFlash['message'], ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php endif; ?>
        <div class="contact-service-chip" id="contactServiceChip">
            <i class="fas fa-wrench" aria-hidden="true"></i>
            <span id="contactServiceChipText"></span>
            <button type="button" id="contactServiceClear" aria-label="Clear selected service">&times;</button>
        </div>
        <form method="post" action="contact_send.php" id="contactForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($contactCsrf, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="service" id="contactService" value="">
            <div class="hp-field" aria-hidden="true">
                <label for="company">Company</label>
                <input type="text" name="company" id="company" tabindex="-1" autocomplete="off">
            </div>
            <div class="fr">
                <div class="fg">
                    <label for="fn">First name <span class="req" aria-hidden="true">*</span></label>
                    <input type="text" id="fn" name="first_name" placeholder="First name" required autocomplete="given-name">
                </div>
                <div class="fg">
                    <label for="ln">Last name <span class="req" aria-hidden="true">*</span></label>
                    <input type="text" id="ln" name="last_name" placeholder="Last name" required autocomplete="family-name">
                </div>
            </div>
            <div class="fr">
                <div class="fg">
                    <label for="em">Email <span class="req" aria-hidden="true">*</span></label>
                    <input type="email" id="em" name="email" placeholder="you@company.com" required autocomplete="email">
                </div>
                <div class="fg">
                    <label for="ph">Phone</label>
                    <input type="tel" id="ph" name="phone" placeholder="+264 81 234 5678" autocomplete="tel">
                </div>
            </div>
            <div class="fg">
                <label for="contactServiceSelect">Service interest</label>
                <select id="contactServiceSelect" name="service_select">
                    <option value="">Select a service (optional)</option>
                    <option value="Auto Electric / Electronics">Auto Electric / Electronics</option>
                    <option value="Mechanical Services">Mechanical Services</option>
                    <option value="Accessories &amp; Fitments">Accessories &amp; Fitments</option>
                    <option value="A/C — Trucks &amp; Cars">A/C — Trucks &amp; Cars</option>
                    <option value="General enquiry">General enquiry</option>
                </select>
            </div>
            <div class="fg">
                <label for="msg">Message <span class="req" aria-hidden="true">*</span></label>
                <textarea id="msg" name="message" placeholder="Tell us about your vehicle, fleet size, and what you need…" required></textarea>
            </div>
            <button type="submit" class="bsub btn-primary" id="contactSubmitBtn">Send message →</button>
            <p class="contact-form-note" style="text-align:center;margin-top:12px;">
                After sending, you can <a href="Admin/Auth/register.php">create an account</a> or <a href="login.php">sign in</a> to follow quotations, approvals, job cards, and invoices.
            </p>
        </form>
    </div>
</section>

<footer>
    <div class="footer-shell">
        <div class="fg2">
            <div class="fbr">
                <a href="#home" class="footer-brand-name">SV Auto Truck Repair</a>
                <p>Expert truck and auto repair in Windhoek. Electrical, mechanical, A/C, and accessories for all vehicles.</p>
                <div class="footer-social">
                    <a href="https://wa.me/<?= htmlspecialchars($contactWhatsApp, ENT_QUOTES, 'UTF-8') ?>?text=<?= $contactWhatsAppText ?>" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                    <a href="tel:<?= htmlspecialchars($contactPhoneTel, ENT_QUOTES, 'UTF-8') ?>" aria-label="Call us"><i class="fas fa-phone"></i></a>
                    <a href="mailto:<?= htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8') ?>" aria-label="Email"><i class="fas fa-envelope"></i></a>
                </div>
            </div>
            <nav class="fc" aria-label="Services">
                <h4>Services</h4>
                <ul>
                    <li><a href="#services">Auto Electric</a></li>
                    <li><a href="#services">Mechanical</a></li>
                    <li><a href="#services">A/C</a></li>
                    <li><a href="#services">Accessories</a></li>
                </ul>
            </nav>
            <nav class="fc" aria-label="Quick links">
                <h4>Quick links</h4>
                <ul>
                    <li><a href="#home">Home</a></li>
                    <li><a href="#about">About</a></li>
                    <li><a href="#services">Services</a></li>
                    <li><a href="#contact">Contact</a></li>
                </ul>
            </nav>
            <nav class="fc" aria-label="Contact">
                <h4>Contact</h4>
                <ul>
                    <li><a href="tel:+264814469962">+264 81 446 9962</a></li>
                    <li><a href="tel:+264812860173">+264 81 286 0173</a></li>
                    <li><a href="mailto:svautotruckrepair@gmail.com">Email us</a></li>
                    <li><a href="https://www.google.com/maps/search/?api=1&query=Lafrenz+Industrial+Rensburger+Street+Windhoek+Namibia" target="_blank" rel="noopener">Directions</a></li>
                </ul>
            </nav>
        </div>
        <div class="footer-meta">
            <div class="footer-meta-grid">
                <div class="footer-meta-item">
                    <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
                    <a href="https://www.google.com/maps/search/?api=1&query=Lafrenz+Industrial+Rensburger+Street+Windhoek+Namibia" target="_blank" rel="noopener">Lafrenz Industrial, Rensburger St, Windhoek</a>
                </div>
                <div class="footer-meta-item">
                    <i class="fas fa-clock" aria-hidden="true"></i>
                    <span>Mon – Fri: 08:00 – 17:00</span>
                </div>
            </div>
        </div>
        <div class="fbot">
            <p>© <?= date('Y') ?> SV Auto Truck Repair · Windhoek, Namibia · Reg No: cc/2015/13178 · VAT: 7098116-01-5</p>
        </div>
    </div>
</footer>

<nav class="sticky-contact" aria-label="Quick contact">
    <div class="sticky-contact-inner">
        <a href="tel:<?= htmlspecialchars($contactPhoneTel, ENT_QUOTES, 'UTF-8') ?>"><i class="fas fa-phone" aria-hidden="true"></i> Call</a>
        <a href="https://wa.me/<?= htmlspecialchars($contactWhatsApp, ENT_QUOTES, 'UTF-8') ?>?text=<?= $contactWhatsAppText ?>" class="sticky-wa" target="_blank" rel="noopener noreferrer"><i class="fab fa-whatsapp" aria-hidden="true"></i> WhatsApp</a>
    </div>
</nav>

<script src="assets/js/site-theme.js?v=1"></script>
<script src="assets/js/script.js?v=3.5"></script>
<script>
(function () {
    var obs = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
            if (e.isIntersecting) e.target.classList.add('vis');
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    document.querySelectorAll('.rv').forEach(function (el) { obs.observe(el); });
})();
(function () {
    var serviceInput = document.getElementById('contactService');
    var serviceSelect = document.getElementById('contactServiceSelect');
    var serviceChip = document.getElementById('contactServiceChip');
    var serviceChipText = document.getElementById('contactServiceChipText');
    var serviceClear = document.getElementById('contactServiceClear');
    var messageField = document.getElementById('msg');
    var servicesQuoteSelected = document.getElementById('servicesQuoteSelected');
    var servicesQuoteSelectedText = document.getElementById('servicesQuoteSelectedText');
    var servicesQuoteSelectedIcon = document.getElementById('servicesQuoteSelectedIcon');
    var servicesOrbitCaption = document.getElementById('servicesOrbitCaption');
    var params = new URLSearchParams(window.location.search);

    function iconForService(serviceName) {
        var value = (serviceName || '').trim();
        if (!value) return '';
        var found = '';
        document.querySelectorAll('#services [data-service]').forEach(function (el) {
            if (el.getAttribute('data-service') === value) {
                found = el.getAttribute('data-icon') || '';
            }
        });
        return found;
    }

    function syncServiceCardsHighlight(serviceName) {
        var value = (serviceName || '').trim();
        document.querySelectorAll('#services a.sc[data-service]').forEach(function (card) {
            card.classList.toggle('is-selected', card.getAttribute('data-service') === value);
        });
    }

    function updateServicesQuotePreview(serviceName, iconClass) {
        var value = (serviceName || '').trim();
        var icon = iconClass || iconForService(value);
        if (!servicesQuoteSelected || !servicesQuoteSelectedText) return;
        if (value) {
            servicesQuoteSelectedText.textContent = value;
            servicesQuoteSelected.classList.add('is-visible');
            if (servicesQuoteSelectedIcon && icon) {
                servicesQuoteSelectedIcon.innerHTML = '<i class="fas ' + icon + '" aria-hidden="true"></i>';
            }
            if (servicesOrbitCaption) servicesOrbitCaption.classList.add('is-active');
        } else {
            servicesQuoteSelectedText.textContent = 'No service selected yet';
            servicesQuoteSelected.classList.remove('is-visible');
            if (servicesQuoteSelectedIcon) {
                servicesQuoteSelectedIcon.innerHTML = '<i class="fas fa-hand-pointer" aria-hidden="true"></i>';
            }
            if (servicesOrbitCaption) servicesOrbitCaption.classList.remove('is-active');
        }
        syncServiceCardsHighlight(value);
    }

    /** Contact form only — does not change the services orbit preview. */
    function setContactService(serviceName) {
        var value = (serviceName || '').trim();
        if (serviceInput) serviceInput.value = value;
        if (serviceSelect) {
            var matched = false;
            Array.prototype.forEach.call(serviceSelect.options, function (opt) {
                if (opt.value === value) {
                    serviceSelect.value = value;
                    matched = true;
                }
            });
            if (!matched) serviceSelect.value = '';
        }
        if (serviceChip && serviceChipText) {
            if (value) {
                serviceChipText.textContent = 'Service interest: ' + value;
                serviceChip.classList.add('is-visible');
            } else {
                serviceChipText.textContent = '';
                serviceChip.classList.remove('is-visible');
            }
        }
    }

    /** User chose a service — sync contact form + optional message (not used by orbit auto-rotate). */
    function applyQuoteRequest(serviceName, updateMessage) {
        var value = (serviceName || '').trim();
        if (!value) return;
        setContactService(value);
        updateServicesQuotePreview(value);
        if (updateMessage !== false && messageField) {
            messageField.value = 'Hi, I would like a quote for: ' + value + '.\n\n';
        }
    }

    function clearQuoteRequest() {
        setContactService('');
        updateServicesQuotePreview('');
    }

    function prefillQuote(serviceName) {
        applyQuoteRequest(serviceName, true);
    }

    var quote = params.get('quote');
    if (quote) prefillQuote(decodeURIComponent(quote));

    document.querySelectorAll('#services a.sc[data-service]').forEach(function (card) {
        card.addEventListener('click', function () {
            prefillQuote(card.getAttribute('data-service') || '');
        });
    });

    var servicesQuoteCta = document.getElementById('servicesQuoteCta');
    if (servicesQuoteCta) {
        servicesQuoteCta.addEventListener('click', function () {
            var pending = servicesQuoteSelectedText ? servicesQuoteSelectedText.textContent.trim() : '';
            if (pending && pending !== 'No service selected yet') {
                applyQuoteRequest(pending, true);
            }
        });
    }

    if (serviceSelect) {
        serviceSelect.addEventListener('change', function () {
            setContactService(serviceSelect.value);
        });
    }

    if (serviceClear) {
        serviceClear.addEventListener('click', function () {
            clearQuoteRequest();
        });
    }

    var form = document.getElementById('contactForm');
    if (form) {
        form.addEventListener('submit', function () {
            if (serviceSelect && serviceSelect.value && serviceInput && !serviceInput.value) {
                serviceInput.value = serviceSelect.value;
            }
            var btn = document.getElementById('contactSubmitBtn');
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Sending…';
            }
        });
    }

    (function initServicesOrbitCarousel() {
        var rotator = document.getElementById('servicesOrbitRotator');
        var orbit = document.getElementById('servicesOrbit');
        if (!rotator || !orbit) return;

        var nodes = rotator.querySelectorAll('.services-orbit-node');
        var activeLabel = document.getElementById('servicesOrbitActiveLabel');
        var caption = document.getElementById('servicesOrbitCaptionText');
        var HOLD_MS = 8000;
        var TRANSITION_MS = 850;
        var index = 0;
        var timer = null;
        var paused = false;
        var userHoldUntil = 0;
        var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        function labelFor(node) {
            return node.getAttribute('data-label') || node.getAttribute('data-service') || '';
        }

        function show(i) {
            var count = nodes.length;
            if (!count) return;
            index = ((i % count) + count) % count;
            rotator.style.transform = 'rotate(' + (-index * 90) + 'deg)';
            Array.prototype.forEach.call(nodes, function (node, j) {
                node.classList.toggle('is-active', j === index);
            });
            var active = nodes[index];
            var name = labelFor(active);
            if (activeLabel) {
                activeLabel.textContent = active.getAttribute('data-service') || name;
            }
            if (caption) caption.textContent = name;
            updateServicesQuotePreview(active.getAttribute('data-service') || '', active.getAttribute('data-icon'));
        }

        function scheduleNext() {
            clearTimeout(timer);
            timer = setTimeout(function () {
                if (paused || Date.now() < userHoldUntil) {
                    scheduleNext();
                    return;
                }
                show(index + 1);
                scheduleNext();
            }, HOLD_MS + (reduceMotion ? 0 : TRANSITION_MS));
        }

        Array.prototype.forEach.call(nodes, function (node, i) {
            node.addEventListener('click', function () {
                show(i);
                userHoldUntil = Date.now() + HOLD_MS * 2;
                applyQuoteRequest(node.getAttribute('data-service') || '', true);
            });
        });

        orbit.addEventListener('mouseenter', function () { paused = true; });
        orbit.addEventListener('mouseleave', function () { paused = false; });
        orbit.addEventListener('focusin', function () { paused = true; });
        orbit.addEventListener('focusout', function (e) {
            if (!orbit.contains(e.relatedTarget)) paused = false;
        });

        if (reduceMotion) {
            show(0);
            return;
        }

        show(0);
        scheduleNext();
    })();
})();
</script>
</body>
</html>
