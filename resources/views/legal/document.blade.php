@extends('legal.layout')
@section('content')
    @php
        $headings = [];
        $displayContent = preg_replace_callback('/<h2>(.*?)<\/h2>/s', function ($match) use (&$headings) {
            $id = 'section-'.(count($headings) + 1);
            $headings[$id] = html_entity_decode(strip_tags($match[1]), ENT_QUOTES, 'UTF-8');
            return '<h2 id="'.$id.'">'.$match[1].'</h2>';
        }, $content ?? '');
    @endphp
    <div class="hero">
        <div class="eyebrow">Your trust matters</div>
        <h1>{{ $title }}</h1>
        <p class="lead">@if($document->slug === 'privacy-policy')Your information. Your choices. A clear explanation of how {{ $settings->app_name }} handles your data.@else A shared understanding of how {{ $settings->app_name }} works, what to expect, and your responsibilities.@endif</p>
        @if($document->published_at && !$preview)
            <div class="meta"><span class="tag">Version {{ $document->version }}</span><span>Effective <time datetime="{{ $document->published_effective_on->toDateString() }}">{{ $document->published_effective_on->format('j F Y') }}</time></span><span>Published {{ $document->published_at->format('j F Y') }}</span></div>
        @endif
    </div>
    @if($preview)<div class="notice" role="status"><strong>Private draft preview.</strong> This is not the published policy. Confirm the business identity, Android permission behavior and retention schedule before publishing.</div>@endif
    @if(!$content)
        <div class="paper bottom-space"><div class="document-content"><h2>Not published yet</h2><p>The operator is reviewing this policy. Please check back after it is published or <a href="{{ route('legal.privacy-request') }}">send a question</a>.</p></div></div>
    @else
        <div class="reading-layout">
            <aside class="contents" aria-label="On this page">
                <div class="contents-label">On this page</div>
                <nav>@foreach($headings as $id => $heading)<a href="#{{ $id }}">{{ $heading }}</a>@endforeach</nav>
                <div class="help-note"><strong>Have a question?</strong><p>Our team can help with your account, data or a decision you'd like reviewed.</p><a href="{{ route('legal.privacy-request') }}">Get in touch →</a></div>
            </aside>
            <article class="paper" aria-label="{{ $title }}">
                <div class="document-content">{!! $displayContent !!}</div>
                @include('legal.contact')
            </article>
        </div>
    @endif
@endsection
