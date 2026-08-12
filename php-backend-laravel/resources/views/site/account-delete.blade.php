@extends('site.layout')

@section('content')
{{-- Account deletion — the URL filed in the Play Console Data safety form.
     Google checks this page for three things by name, so keep them all present:
       1. it names the app / developer as shown on the store listing
       2. it prominently states the STEPS to request deletion
       3. it states what data is deleted, what is kept, and for how long
     Editing this page? Don't remove those three, or the next review fails. --}}
<div class="aprof aprof-doc">
    <h1 class="aprof-doc__title">Delete your Haraan account</h1>
    <p class="aprof-doc__lede">
        This page lets you delete your account for <strong>Haraan: Events &amp; Sports</strong>
        (<code>com.haraan.app</code>), published by Haraan. You can use it whether or not you
        still have the app installed.
    </p>

    @if(session('deleted'))
        <div class="aprof-flash">{{ session('deleted') }}</div>
    @elseif(session('success'))
        <div class="aprof-flash">{{ session('success') }}</div>
    @endif

    <h2 class="aprof-heading">How to delete your account</h2>
    <div class="aprof-card aprof-doc__body">
        <p><strong>In the app:</strong> open <em>Account → Privacy → Delete account</em>,
           then confirm. Your account is deleted straight away.</p>
        <p><strong>On this page:</strong></p>
        <ol>
            <li>Enter the email address on your Haraan account in the form below.</li>
            <li>Tick the confirmation box and press <em>Request deletion</em>.</li>
            <li>Open the confirmation link we email you. The link is valid for 48 hours.</li>
            <li>Your account and personal data are deleted as soon as you open that link.</li>
        </ol>
        <p>If you are signed in on this website, the form deletes your account immediately
           and no email step is needed.</p>
    </div>

    <h2 class="aprof-heading">What is deleted</h2>
    <div class="aprof-card aprof-doc__body">
        <p>Deleted permanently, and immediately on confirmation:</p>
        <ul>
            <li>Your name, email address, phone number and profile photo</li>
            <li>Your date of birth, gender, nationality and other profile details</li>
            <li>Your player profile, career statistics, ranking and reputation score</li>
            <li>Your support chat history with us</li>
            <li>Your saved devices and push-notification tokens, so all notifications stop</li>
            <li>Your notification history, viewing history and activity records</li>
            <li>Any venue waitlist entries you were holding</li>
            <li>All active sign-in sessions, on every device</li>
        </ul>
        <p>Your profile stops appearing on leaderboards, in search and on match pages
           at the same moment.</p>
    </div>

    <h2 class="aprof-heading">What is kept, and for how long</h2>
    <div class="aprof-card aprof-doc__body">
        <p>Records of completed ticket purchases and venue bookings — the amount, date,
           and payment reference — are <strong>kept</strong>. We are required to retain
           financial and tax records under Indian tax and accounting law, currently for
           up to <strong>8 years</strong> from the end of the relevant financial year.</p>
        <p>These records are anonymised: once your account is deleted they are no longer
           linked to your name, email, phone number or any other detail that identifies
           you. They cannot be used to contact you or to rebuild your profile.</p>
        <p>We also keep a dated record that a deletion request was made and honoured.
           It holds only the email address the request was made about, as proof that we
           acted on it.</p>
        <p>Deleting your account does not cancel or refund a booking you have already
           paid for. If you need a refund, contact us <em>before</em> deleting.</p>
    </div>

    <h2 class="aprof-heading">Request deletion</h2>
    <div class="aprof-card aprof-doc__body">
        <p style="margin-top:0"><strong>This cannot be undone.</strong> A deleted account
           cannot be restored, and your player history and ranking cannot be recovered.</p>

        @if($errors->any())
            <div class="aprof-flash" style="background:#fdecec;color:#8f1d1d">
                @foreach($errors->all() as $error)
                    <p style="margin:0">{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('site.account.delete.submit') }}">
            @csrf

            @if($user)
                <p>You are signed in as <strong>{{ $user->email }}</strong>. Submitting this
                   form deletes that account immediately.</p>
            @else
                <div class="field">
                    <label for="del-email" style="font-weight:800;font-size:13px">Email address on the account</label>
                    <input type="email" id="del-email" name="email"
                           value="{{ old('email') }}" required autocomplete="email"
                           placeholder="you@example.com">
                </div>
            @endif

            <div class="field">
                <label for="del-reason" style="font-weight:800;font-size:13px">Reason (optional)</label>
                <textarea id="del-reason" name="reason" rows="3" maxlength="500"
                          placeholder="Anything you'd like us to know"
                          style="width:100%;border:1px solid rgba(18,22,32,0.1);border-radius:14px;padding:14px 16px;background:rgba(255,255,255,0.92);color:#121620;font:inherit">{{ old('reason') }}</textarea>
            </div>

            <label style="display:flex;gap:10px;align-items:flex-start;margin:14px 0">
                <input type="checkbox" name="confirm" value="1" required style="width:auto;margin-top:3px">
                <span>I understand my account and personal data will be permanently deleted.</span>
            </label>

            <button type="submit" class="btn btn--solid btn--full btn--large">Request deletion</button>
        </form>
    </div>

    <div class="aprof-card aprof-doc__body">
        <p style="margin:0">Questions, or need help instead of deleting? Email
           <a href="mailto:support@haraan.app">support@haraan.app</a> or read our
           <a href="{{ route('site.legal', 'privacy') }}">Privacy Policy</a>.</p>
    </div>

    <p class="aprof-doc__back"><a href="{{ url('/') }}">← Haraan</a></p>
</div>
@endsection
