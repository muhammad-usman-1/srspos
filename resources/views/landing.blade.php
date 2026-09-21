<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} – Point of Sale that never stops selling</title>
    <meta name="description" content="Billing, stock, purchases and receipts in one place. Keeps working even when the internet goes down.">
    <link rel="icon" href="{{ asset('images/pwa-192.png') }}">
    <style>
        :root {
            --ink: #0f172a; --muted: #475569; --line: #e2e8f0; --bg: #ffffff; --soft: #f8fafc;
            --brand: #2563eb; --brand-dark: #1d4ed8; --accent: #16a34a;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body { font-family: "Source Sans Pro", system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; color: var(--ink); background: var(--bg); line-height: 1.6; }
        a { color: inherit; text-decoration: none; }
        .wrap { max-width: 1120px; margin: 0 auto; padding: 0 20px; }

        /* nav */
        header.nav { position: sticky; top: 0; z-index: 10; background: rgba(255,255,255,.92); backdrop-filter: blur(8px); border-bottom: 1px solid var(--line); }
        .nav .wrap { display: flex; align-items: center; justify-content: space-between; height: 64px; }
        .brand { display: flex; align-items: center; gap: 10px; font-weight: 700; font-size: 18px; }
        .brand img { height: 34px; width: auto; }
        .nav nav { display: flex; align-items: center; gap: 26px; font-size: 15px; color: var(--muted); }
        .nav nav a:hover { color: var(--ink); }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 11px 20px; border-radius: 10px; font-weight: 600; font-size: 15px; border: 1px solid transparent; cursor: pointer; transition: .15s; }
        .btn-primary { background: var(--brand); color: #fff; }
        .btn-primary:hover { background: var(--brand-dark); }
        .btn-ghost { border-color: var(--line); background: #fff; }
        .btn-ghost:hover { border-color: #cbd5e1; background: var(--soft); }

        /* hero */
        .hero { background: radial-gradient(1200px 500px at 80% -10%, #dbeafe 0%, transparent 60%), linear-gradient(180deg, #f8fafc, #fff); padding: 72px 0 56px; }
        .hero .wrap { display: grid; grid-template-columns: 1.05fr .95fr; gap: 48px; align-items: center; }
        .pill { display: inline-block; background: #dcfce7; color: #166534; font-size: 13px; font-weight: 600; padding: 4px 12px; border-radius: 999px; margin-bottom: 18px; }
        h1 { font-size: clamp(2rem, 4.4vw, 3.3rem); line-height: 1.12; letter-spacing: -.02em; margin-bottom: 18px; }
        h1 span { color: var(--brand); }
        .lead { font-size: 1.15rem; color: var(--muted); max-width: 540px; margin-bottom: 28px; }
        .cta { display: flex; flex-wrap: wrap; gap: 12px; }
        .note { margin-top: 14px; font-size: 13px; color: var(--muted); }

        /* mock POS */
        .mock { background: #fff; border: 1px solid var(--line); border-radius: 16px; box-shadow: 0 24px 60px -20px rgba(15,23,42,.25); overflow: hidden; }
        .mock .bar { display: flex; gap: 6px; padding: 10px 14px; background: #f1f5f9; border-bottom: 1px solid var(--line); align-items: center; }
        .mock .bar i { width: 10px; height: 10px; border-radius: 50%; background: #cbd5e1; display: block; }
        .mock .bar .st { margin-left: auto; font-size: 11px; font-weight: 700; color: #166534; background: #dcfce7; padding: 2px 8px; border-radius: 999px; }
        .mock .body { display: grid; grid-template-columns: 1.15fr 1fr; }
        .mock .tiles { padding: 14px; display: grid; grid-template-columns: 1fr 1fr; gap: 8px; align-content: start; }
        .tile { border: 1px solid var(--line); border-left: 4px solid var(--accent); border-radius: 8px; padding: 8px 10px; font-size: 12px; }
        .tile.low { border-left-color: #f59e0b; }
        .tile b { display: block; font-size: 12.5px; }
        .tile span { color: var(--brand); font-weight: 700; }
        .tile small { float: right; color: var(--muted); }
        .mock .cart { border-left: 1px solid var(--line); padding: 14px; background: var(--soft); font-size: 12.5px; }
        .mock .cart .row { display: flex; justify-content: space-between; padding: 4px 0; }
        .mock .cart .tot { border-top: 1px dashed #cbd5e1; margin-top: 8px; padding-top: 8px; font-weight: 700; font-size: 15px; }
        .mock .cart .pay { margin-top: 10px; background: var(--brand); color: #fff; text-align: center; padding: 9px; border-radius: 8px; font-weight: 700; }

        /* sections */
        section { padding: 72px 0; }
        section.alt { background: var(--soft); border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); }
        .eyebrow { color: var(--brand); font-weight: 700; font-size: 13px; letter-spacing: .08em; text-transform: uppercase; margin-bottom: 8px; }
        h2 { font-size: clamp(1.6rem, 3vw, 2.3rem); letter-spacing: -.01em; line-height: 1.2; margin-bottom: 12px; }
        .sub { color: var(--muted); max-width: 640px; font-size: 1.05rem; margin-bottom: 40px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 18px; }
        .card { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 22px; }
        .card .ic { width: 44px; height: 44px; border-radius: 10px; background: #eff6ff; color: var(--brand); display: flex; align-items: center; justify-content: center; margin-bottom: 14px; }
        .card svg { width: 22px; height: 22px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
        .card h3 { font-size: 1.05rem; margin-bottom: 6px; }
        .card p { color: var(--muted); font-size: .96rem; }

        .steps { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 24px; counter-reset: s; }
        .step { position: relative; padding-top: 52px; }
        .step::before { counter-increment: s; content: counter(s); position: absolute; top: 0; left: 0; width: 38px; height: 38px; border-radius: 50%; background: var(--ink); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; }
        .step h3 { margin-bottom: 4px; }
        .step p { color: var(--muted); }

        .offline { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; align-items: center; }
        .offline ul { list-style: none; display: grid; gap: 12px; margin-top: 8px; }
        .offline li { display: flex; gap: 10px; color: var(--muted); }
        .offline li::before { content: "✓"; color: var(--accent); font-weight: 800; }
        .flow { background: #0f172a; color: #e2e8f0; border-radius: 16px; padding: 26px; font-size: 14px; }
        .flow div { padding: 10px 14px; border-radius: 10px; background: #1e293b; margin-bottom: 10px; display: flex; justify-content: space-between; gap: 10px; }
        .flow div:last-child { margin-bottom: 0; }
        .flow em { font-style: normal; font-weight: 700; }
        .flow .off em { color: #fca5a5; } .flow .on em { color: #86efac; }

        .final { text-align: center; background: linear-gradient(135deg, #1d4ed8, #2563eb); color: #fff; }
        .final h2 { color: #fff; } .final .sub { color: #dbeafe; margin: 0 auto 26px; }
        .final .btn-primary { background: #fff; color: var(--brand-dark); }
        footer { padding: 26px 0; color: var(--muted); font-size: 14px; border-top: 1px solid var(--line); }
        footer .wrap { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 8px; }

        @media (max-width: 900px) {
            .hero .wrap, .offline { grid-template-columns: 1fr; }
            .nav nav .link { display: none; }
            .hero { padding-top: 40px; }
        }
    </style>
</head>
<body>
@php $loggedIn = auth()->check(); @endphp

<header class="nav">
    <div class="wrap">
        <a href="/" class="brand"><img src="{{ app_logo_url() }}" alt=""> {{ config('app.name') }}</a>
        <nav>
            <a class="link" href="#features">Features</a>
            <a class="link" href="#offline">Works offline</a>
            <a class="link" href="#how">How it works</a>
            @if($loggedIn)
                <a class="btn btn-primary" href="{{ route('home') }}">Open dashboard</a>
            @else
                <a class="btn btn-primary" href="{{ route('login') }}">Login</a>
            @endif
        </nav>
    </div>
</header>

<main>
    <div class="hero">
        <div class="wrap">
            <div>
                <span class="pill">Built for shops &amp; marts</span>
                <h1>Bill faster. Track every item. <span>Keep selling even offline.</span></h1>
                <p class="lead">One system for your counter, stock and purchases. Scan, bill, print and take payment in seconds, and if the internet drops, the sale still goes through.</p>
                <div class="cta">
                    @if($loggedIn)
                        <a class="btn btn-primary" href="{{ route('cart.index') }}">Open POS</a>
                        <a class="btn btn-ghost" href="{{ route('home') }}">Dashboard</a>
                    @else
                        <a class="btn btn-primary" href="{{ route('login') }}">Login to your store</a>
                        <a class="btn btn-ghost" href="#features">See features</a>
                    @endif
                </div>
                <p class="note">New stores are set up by the system owner.</p>
            </div>

            <div class="mock" aria-hidden="true">
                <div class="bar"><i></i><i></i><i></i><span class="st">● Online</span></div>
                <div class="body">
                    <div class="tiles">
                        <div class="tile"><b>Milk 1L</b><span>PKR 150</span><small>80 left</small></div>
                        <div class="tile"><b>White Bread</b><span>PKR 140</span><small>50 left</small></div>
                        <div class="tile"><b>Cooking Oil 1L</b><span>PKR 320</span><small>55 left</small></div>
                        <div class="tile low"><b>Notebook A5</b><span>PKR 120</span><small>8 left</small></div>
                        <div class="tile"><b>Tea Bags (100)</b><span>PKR 330</span><small>45 left</small></div>
                        <div class="tile"><b>Bath Soap</b><span>PKR 90</span><small>140 left</small></div>
                    </div>
                    <div class="cart">
                        <div class="row"><span>Milk 1L × 2</span><span>300</span></div>
                        <div class="row"><span>White Bread × 1</span><span>140</span></div>
                        <div class="row"><span>Tea Bags × 1</span><span>330</span></div>
                        <div class="row tot"><span>Total</span><span>PKR 770</span></div>
                        <div class="pay">Checkout</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <section id="features">
        <div class="wrap">
            <div class="eyebrow">Features</div>
            <h2>Everything a counter needs</h2>
            <p class="sub">From the first scan to the printed bill, and from stock arriving to stock running low.</p>
            <div class="grid">
                <div class="card"><div class="ic"><svg viewBox="0 0 24 24"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg></div><h3>Fast billing</h3><p>Scan barcodes or search, pick with the keyboard arrows, and check out with one key. Made for a busy counter.</p></div>
                <div class="card"><div class="ic"><svg viewBox="0 0 24 24"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg></div><h3>Hold &amp; resume bills</h3><p>Park a customer's bill, serve the next person, and bring the first one back when they return.</p></div>
                <div class="card"><div class="ic"><svg viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></div><h3>Every way to pay</h3><p>Cash, card, EasyPaisa, JazzCash and bank transfer, with change or balance due worked out for you.</p></div>
                <div class="card"><div class="ic"><svg viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg></div><h3>Your bill, your way</h3><p>Print 80&nbsp;mm receipts automatically with your logo, address, phone number and return policy.</p></div>
                <div class="card"><div class="ic"><svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg></div><h3>Full stock control</h3><p>Add, remove or set stock, and see a history of every change: sales, purchases and adjustments.</p></div>
                <div class="card"><div class="ic"><svg viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg></div><h3>Purchases &amp; suppliers</h3><p>Record what you buy from suppliers. Stock and cost prices update when a purchase is completed.</p></div>
                <div class="card"><div class="ic"><svg viewBox="0 0 24 24"><line x1="19" y1="5" x2="5" y2="19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg></div><h3>Discount &amp; tax</h3><p>Switch discounts and tax on or off from Settings. Amounts are worked out on the bill automatically.</p></div>
                <div class="card"><div class="ic"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div><h3>Separate for every store</h3><p>Each store has its own login, products, customers, sales and settings. Nothing is shared between stores.</p></div>
            </div>
        </div>
    </section>

    <section id="offline" class="alt">
        <div class="wrap offline">
            <div>
                <div class="eyebrow">Works offline</div>
                <h2>The internet drops. Your counter doesn't.</h2>
                <p class="sub" style="margin-bottom:8px">Install the POS on your shop computer. Sales are saved on the device and sent to the server on their own when the connection is back.</p>
                <ul>
                    <li>Keep scanning, billing and printing with no connection</li>
                    <li>Bills print with a temporary number and get their real number after upload</li>
                    <li>Each sale is saved once, even if the connection drops mid-upload</li>
                    <li>Installs like an app, with a desktop shortcut</li>
                </ul>
            </div>
            <div class="flow" aria-hidden="true">
                <div class="on"><span>Morning: open the POS</span><em>Online</em></div>
                <div><span>Products saved on the device</span><em>Ready</em></div>
                <div class="off"><span>Internet goes down</span><em>Offline</em></div>
                <div><span>3 sales billed and printed</span><em>Waiting to sync</em></div>
                <div class="on"><span>Connection returns</span><em>Synced ✓</em></div>
            </div>
        </div>
    </section>

    <section id="how">
        <div class="wrap">
            <div class="eyebrow">How it works</div>
            <h2>Up and running in three steps</h2>
            <p class="sub">No complicated setup for the shop staff.</p>
            <div class="steps">
                <div class="step"><h3>Get your store login</h3><p>The system owner creates your store and gives you an email and password.</p></div>
                <div class="step"><h3>Add products &amp; stock</h3><p>Enter your products, prices and opening stock, plus your address, phone and logo for the bill.</p></div>
                <div class="step"><h3>Start selling</h3><p>Open the POS, scan, take payment and print. Watch sales and low stock on your dashboard.</p></div>
            </div>
        </div>
    </section>

    <section class="final">
        <div class="wrap">
            <h2>Ready to run your counter?</h2>
            <p class="sub">Log in with your store account to open the POS.</p>
            @if($loggedIn)
                <a class="btn btn-primary" href="{{ route('home') }}">Open dashboard</a>
            @else
                <a class="btn btn-primary" href="{{ route('login') }}">Login</a>
            @endif
        </div>
    </section>
</main>

<footer>
    <div class="wrap">
        <span>© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</span>
        <span>Point of sale · Stock · Purchases</span>
    </div>
</footer>
</body>
</html>
