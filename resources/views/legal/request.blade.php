@extends('legal.layout')
@section('content')
    <div class="hero">
        <div class="eyebrow">You're in control</div>
        <h1>{{ $title }}</h1>
        <p class="lead">@if($deletion)Request deletion of your {{ $settings->app_name }} account and associated personal data—even if you've already uninstalled the app.@else Ask about your information, request a correction, or have a person review a decision.@endif</p>
    </div>
    @if(session('request_reference'))
        <div class="notice success" role="status"><strong>Request received.</strong> Your reference is <strong>{{ session('request_reference') }}</strong>. Keep this for follow-up. Our team will use the contact you provided to verify ownership and respond. No account has been deleted automatically.</div>
    @endif
    <div class="request-layout">
        <div class="paper">
            <div class="request-card">
                <h2>{{ $deletion ? 'Start a deletion request' : 'How can we help?' }}</h2>
                <p>No app login or reinstallation is required. Never include passwords, mobile-money PINs or OTP codes.</p>
                @if($errors->any())<div class="notice" role="alert">Please check the highlighted fields and try again.</div>@endif
                <form method="POST" action="{{ route('legal.request.store') }}">
                    @csrf
                    @if($deletion)
                        <input type="hidden" name="type" value="deletion">
                    @else
                        <div class="field"><label for="type">Request type</label><select name="type" id="type">
                            @foreach(['privacy' => 'Privacy / support question', 'access' => 'Access my information', 'correction' => 'Correct my information', 'review' => 'Human review of a decision', 'deletion' => 'Delete my account and data'] as $value => $label)<option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>@endforeach
                        </select>@error('type')<p class="error">{{ $message }}</p>@enderror</div>
                    @endif
                    <div class="field"><label for="contact">Account email or phone number</label><input id="contact" name="contact" value="{{ old('contact') }}" maxlength="254" required autocomplete="username" @if($errors->has('contact')) aria-invalid="true" @endif><small>Use a contact connected to your account so we can verify it belongs to you.</small>@error('contact')<p class="error">{{ $message }}</p>@enderror</div>
                    <div class="field"><label for="message">Anything we should know? <span style="font-weight:400">(optional)</span></label><textarea id="message" name="message" maxlength="2000">{{ old('message') }}</textarea><small>For example, your username or the decision you want reviewed. Only include information needed for this request.</small>@error('message')<p class="error">{{ $message }}</p>@enderror</div>
                    <div class="trap" aria-hidden="true"><label for="website">Leave empty</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
                    <div class="field"><label class="check"><input type="checkbox" name="confirmation" value="1" required @checked(old('confirmation'))><span>I am requesting help with my own account, or am authorized to act for the account holder. I understand that ownership must be verified before data is disclosed or deleted.</span></label>@error('confirmation')<p class="error">{{ $message }}</p>@enderror</div>
                    <button type="submit">{{ $deletion ? 'Request account & data deletion' : 'Send request' }} →</button>
                    <p>We use the details you submit to handle this request, as described in our <a href="{{ route('legal.privacy') }}">Privacy Policy</a>.</p>
                </form>
            </div>
            @include('legal.contact')
        </div>
        <aside class="paper request-card">
            <h2>What happens next</h2>
            <ol class="steps"><li><strong>We receive your request</strong>You'll get a reference on this page. The team reviews requests in the admin inbox.</li><li><strong>We confirm it's you</strong>We contact you using a registered contact. This protects your account from someone else's request.</li><li><strong>We take action and explain</strong>For deletion, we remove or anonymize information no longer needed and explain any limited records that must be retained.</li></ol>
            <h2>What deletion covers</h2>
            <p>Your account profile, authentication access and associated personal information and uploaded media that are no longer lawfully needed. Merely disabling an account is not deletion.</p>
            <p>Limited transaction, payout, dispute or security records may be retained for a legitimate legal or fraud-prevention purpose. The team must explain the category, reason and duration—not retain everything indefinitely.</p>
            @if($settings->retention_policy)<div class="retention-summary">{{ $settings->retention_policy }}</div>@else<p>The operator must confirm the detailed retention schedule before publishing the policies. Ask the team about timing when submitting your request.</p>@endif
        </aside>
    </div>
@endsection
