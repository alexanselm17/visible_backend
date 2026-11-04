<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $settings->app_name }} — {{ $title }}. Read our policies, understand your choices, and contact our team.">
    @if(($preview ?? false) || (isset($document) && !$document->published_at))
        <meta name="robots" content="noindex, nofollow">
    @else
        <link rel="canonical" href="{{ request()->url() }}">
    @endif
    <title>{{ $title }} · {{ $settings->app_name }}</title>
    <style>
        :root{--ink:#173d38;--muted:#576e68;--accent:#116f60;--paper:#fff;--line:#dce6df;--wash:#f5f7f2;--soft:#e8f1e9}
        *{box-sizing:border-box}body{margin:0;background:var(--wash);color:var(--ink);font:16px/1.7 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;-webkit-font-smoothing:antialiased}
        a{color:var(--accent);text-underline-offset:4px}a:hover{color:#074d40}a:focus-visible,button:focus-visible,input:focus-visible,select:focus-visible,textarea:focus-visible{outline:3px solid #dba94d;outline-offset:4px}
        .skip{position:absolute;top:-70px;left:20px;background:var(--ink);color:white;padding:10px 16px;z-index:9}.skip:focus{top:12px}
        .shell{width:min(1160px,calc(100% - 64px));margin:auto}.masthead{border-bottom:1px solid var(--line);background:#fcfdf9}.masthead .shell{display:flex;align-items:center;justify-content:space-between;gap:24px;min-height:88px}
        .brand{display:flex;gap:12px;align-items:center;text-decoration:none;color:var(--ink);font-size:21px;font-weight:750;letter-spacing:-.6px}.brand img{width:auto;max-width:160px;height:44px;object-fit:contain}.brand-mark{display:grid;place-items:center;width:42px;height:42px;background:var(--ink);border-radius:13px;color:#cce9bd;font-size:26px;font-weight:650}
        .nav{display:flex;gap:28px;align-items:center;flex-wrap:wrap}.nav a{font-size:14px;text-decoration:none;color:var(--muted)}.nav a[aria-current=page]{color:var(--ink);font-weight:700}.nav a:last-child{padding:8px 14px;border:1px solid #b5c9bb;border-radius:100px}
        .hero{padding:58px 0 38px}.eyebrow{display:flex;gap:9px;align-items:center;font-size:11px;font-weight:750;letter-spacing:2px;text-transform:uppercase;color:var(--accent)}.eyebrow::before{content:"";width:7px;height:7px;background:#348c69;border-radius:50%}h1{font-size:clamp(34px,5vw,58px);line-height:1.1;letter-spacing:-2px;margin:18px 0;color:var(--ink);font-weight:650}.lead{font-size:18px;max-width:660px;color:var(--muted);line-height:1.7}.meta{display:flex;gap:10px 22px;flex-wrap:wrap;font-size:12px;color:var(--muted);margin-top:24px}.meta span{display:inline-flex;gap:5px}.tag{border:1px solid #bdcec0;border-radius:50px;padding:1px 10px;color:var(--accent)}
        .reading-layout{display:grid;grid-template-columns:240px minmax(0,1fr);gap:38px;align-items:start;margin-bottom:60px}.contents{position:sticky;top:24px;padding:12px 0}.contents-label{font-size:11px;text-transform:uppercase;letter-spacing:1.6px;font-weight:750;margin-bottom:16px}.contents a{display:block;padding:7px 0;font-size:13px;text-decoration:none;color:var(--muted);line-height:1.5}.contents a:hover{color:var(--accent)}.help-note{margin-top:24px;padding:20px;border-radius:14px;background:var(--soft);font-size:13px;line-height:1.6}.help-note strong{display:block;margin-bottom:6px}.help-note p{margin:0 0 8px}
        .paper{background:var(--paper);border:1px solid var(--line);border-radius:24px;box-shadow:0 8px 36px #173d3805;overflow:hidden}.document-content{padding:42px 48px;overflow-wrap:anywhere}.document-content>p:first-child{font-size:18px;color:#39594e;margin-top:0;line-height:1.8}.document-content p{color:#425b53;margin:14px 0}.document-content h2{font-size:23px;line-height:1.4;letter-spacing:-.5px;margin:38px 0 14px;padding-top:24px;border-top:1px solid var(--line);scroll-margin-top:24px}.document-content h3{font-size:18px;margin-top:28px}.document-content li{padding-left:4px;margin:10px 0;color:#425b53}.document-content ul,.document-content ol{padding-left:23px}.document-content strong{color:var(--ink)}.document-content blockquote{border-left:3px solid #72a790;margin:20px 0;padding-left:20px}
        .contact-card{padding:28px 48px;background:#f7faf6;border-top:1px solid var(--line);font-size:14px}.contact-card h2{margin:0 0 12px;font-size:18px}.contact-card p{margin:5px 0}.contact-card address{font-style:normal;white-space:pre-line}.contact-links{display:flex;gap:12px 24px;flex-wrap:wrap;margin-top:12px}.notice{padding:18px 22px;border:1px solid #e4c877;background:#fff8e7;border-radius:14px;margin-bottom:22px;font-size:14px;color:#644f17}.success{background:#eaf5ec;border-color:#adcab5;color:var(--ink)}
        .request-layout{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(0,.8fr);gap:36px;margin-bottom:60px;align-items:start}.request-card{padding:32px 36px}.request-card h2{font-size:22px;letter-spacing:-.5px;margin:0 0 12px}.request-card p,.request-card li{color:var(--muted);font-size:14px}.field{margin:20px 0}.field label{display:block;font-weight:650;font-size:14px;margin-bottom:7px}.field small{display:block;color:var(--muted);font-size:12px;margin-top:7px}input,select,textarea{font:inherit;width:100%;background:#fcfdfb;border:1px solid #b9cbbf;border-radius:9px;padding:12px 14px;color:var(--ink)}textarea{resize:vertical;min-height:128px}.check{display:flex;gap:10px;align-items:flex-start;font-size:13px;line-height:1.7}.check input{width:17px;height:17px;flex-shrink:0;margin-top:4px;accent-color:var(--accent)}button{border:0;border-radius:10px;padding:14px 24px;background:var(--ink);color:white;font:inherit;font-weight:600;cursor:pointer}button:hover{background:var(--accent)}.error{color:#9d2929;font-size:13px;margin:5px 0}.trap{display:none}.steps{list-style:none;counter-reset:steps;padding:0}.steps li{counter-increment:steps;position:relative;padding:0 0 20px 38px}.steps li::before{content:counter(steps);position:absolute;left:0;top:0;width:25px;height:25px;background:var(--soft);border-radius:50%;color:var(--accent);text-align:center;font-weight:700}.steps strong{display:block;color:var(--ink)}.retention-summary{padding:20px;background:var(--soft);border-radius:12px;font-size:13px;white-space:pre-line}
        footer{border-top:1px solid var(--line);padding:26px 0 34px;font-size:12px;color:var(--muted)}footer .shell{display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap}footer a{margin-right:16px;text-decoration:none}.footer-label{letter-spacing:.5px}.bottom-space{margin-bottom:50px}
        @media(max-width:900px){.reading-layout{grid-template-columns:190px minmax(0,1fr);gap:24px}.document-content{padding:28px}.contact-card{padding:24px 28px}.nav{gap:14px}.request-layout{gap:20px}.request-card{padding:28px}}
        @media(max-width:680px){.shell{width:calc(100% - 32px)}.masthead .shell{padding:18px 0;display:block;min-height:auto}.nav{margin-top:18px;gap:20px}.nav a{font-size:12px}.nav a:last-child{padding:0;border:0}.hero{padding:36px 0 26px}h1{letter-spacing:-1.2px}.lead{font-size:16px}.reading-layout,.request-layout{grid-template-columns:1fr;gap:20px}.contents{position:static;padding:0 4px}.contents nav{display:flex;gap:4px 16px;flex-wrap:wrap}.contents nav a{font-size:12px}.contents .help-note{display:none}.contents-label{margin-bottom:6px}.paper{border-radius:16px}.document-content{padding:24px}.document-content h2{font-size:21px}.document-content>p:first-child{font-size:16px}.meta{font-size:11px;gap:8px 16px}.request-card{padding:24px}.contact-card{padding:24px}.reading-layout{margin-bottom:36px}}
        @media print{body{background:white;font-size:11pt}.masthead,.contents,footer,.notice{display:none}.shell{width:100%}.hero{padding:0 0 20px}h1{font-size:30pt}.reading-layout{display:block}.paper{border:0;box-shadow:none}.document-content,.contact-card{padding:0}.document-content h2{break-after:avoid}.contact-card{padding-top:20px;margin-top:20px}}
    </style>
</head>
<body>
<a class="skip" href="#main">Skip to content</a>
<header class="masthead">
    <div class="shell">
        <a class="brand" href="{{ route('legal.privacy') }}" aria-label="{{ $settings->app_name }} trust center">
            @if($settings->logoUrl())
                <img src="{{ $settings->logoUrl() }}" alt="{{ $settings->app_name }} logo">
            @else
                <span class="brand-mark" aria-hidden="true">{{ mb_substr($settings->app_name, 0, 1) }}</span>
            @endif
            <span>{{ $settings->app_name }}</span>
        </a>
        <nav class="nav" aria-label="Legal navigation">
            <a href="{{ route('legal.privacy') }}" @if(request()->routeIs('legal.privacy')) aria-current="page" @endif>Privacy</a>
            <a href="{{ route('legal.terms') }}" @if(request()->routeIs('legal.terms')) aria-current="page" @endif>Terms</a>
            <a href="{{ route('legal.delete-account') }}" @if(request()->routeIs('legal.delete-account')) aria-current="page" @endif>Account & data deletion</a>
        </nav>
    </div>
</header>
<main id="main" class="shell">
    @yield('content')
</main>
<footer><div class="shell">
    <span class="footer-label">© {{ now()->year }} {{ $settings->app_name }}@if($settings->operator_name) · {{ $settings->operator_name }}@endif</span>
    <span><a href="{{ route('legal.privacy-request') }}">Contact & privacy requests</a><a href="{{ route('legal.delete-account') }}">Delete account</a></span>
</div></footer>
</body>
</html>
