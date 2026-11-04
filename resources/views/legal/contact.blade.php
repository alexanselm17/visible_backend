<section class="contact-card" aria-label="Operator and privacy contact">
    <h2>Let's talk</h2>
    @if($settings->operator_name)<p><strong>{{ $settings->operator_name }}</strong> · Operator of {{ $settings->app_name }}</p>@endif
    @if($settings->support_email)<p>Support: <a href="mailto:{{ $settings->support_email }}">{{ $settings->support_email }}</a></p>@endif
    @if($settings->privacy_email || $settings->support_email)<p>Privacy: <a href="mailto:{{ $settings->privacy_email ?: $settings->support_email }}">{{ $settings->privacy_email ?: $settings->support_email }}</a></p>@endif
    @if($settings->support_phone)<p>{{ $settings->support_phone }}</p>@endif
    @if($settings->postal_address)<address>{{ $settings->postal_address }}</address>@endif
    <div class="contact-links"><a href="{{ route('legal.privacy-request') }}">Send a request</a><a href="{{ route('legal.delete-account') }}">Request account deletion</a></div>
</section>
