@extends('site.layout')

@section('content')
<div class="actionboard-create-page">
    <div class="container container--narrow">
        
        <!-- Form Center Box -->
        <div class="create-match-card animate-fade-in">
            <!-- Card Header -->
            <div class="create-match-card__header">
                <div class="brand-sub">Haran Live</div>
                <h2>Create Live Cricket Match</h2>
                <p>Register a new active scoring session for dynamic ball-by-ball tracking.</p>
            </div>

            <!-- Card Body / Form Area -->
            <div class="create-match-card__body">
                @if ($errors->any())
                    <div class="modal-error-alert">
                        <div class="modal-error-alert__header">
                            ⚠️ Please correct the following errors:
                        </div>
                        <ul class="modal-error-alert__list">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('site.gamehub.actionboard.store') }}" method="POST">
                    @csrf
                    
                    <div class="form-grid">
                        <!-- Team A Input -->
                        <div class="form-group">
                            <label for="home">Team A (Home / Batting / Bowling)</label>
                            <div class="input-wrapper">
                                <span class="input-icon">🏏</span>
                                <input type="text" id="home" name="home" required placeholder="e.g. Chennai Super Kings" value="{{ old('home') }}">
                            </div>
                            <small class="form-help">Enter the full or abbreviated name of the home franchise.</small>
                        </div>

                        <!-- Team B Input -->
                        <div class="form-group">
                            <label for="away">Team B (Away / Opponent)</label>
                            <div class="input-wrapper">
                                <span class="input-icon">🛡️</span>
                                <input type="text" id="away" name="away" required placeholder="e.g. Mumbai Indians" value="{{ old('away') }}">
                            </div>
                            <small class="form-help">Enter the full or abbreviated name of the opponent franchise.</small>
                        </div>

                        <!-- Total Overs -->
                        <div class="form-group">
                            <label for="total_overs">Total Overs</label>
                            <div class="input-wrapper">
                                <span class="input-icon">⏱️</span>
                                <input type="number" id="total_overs" name="total_overs" required placeholder="e.g. 20" min="1" max="100" value="{{ old('total_overs', 20) }}">
                            </div>
                            <small class="form-help">Total overs allocated for each innings (e.g. 20 for T20, 50 for ODI).</small>
                        </div>

                        <!-- Tournament Name -->
                        <div class="form-group">
                            <label for="competition">Tournament / Series Name (Optional)</label>
                            <div class="input-wrapper">
                                <span class="input-icon">🏆</span>
                                <input type="text" id="competition" name="competition" placeholder="e.g. Hyderabad Premier League" value="{{ old('competition') }}">
                            </div>
                            <small class="form-help">Enter the tournament or league name to group your matches together.</small>
                        </div>

                        <!-- Toss Details -->
                        <div class="form-group">
                            <label for="toss">Toss Details (Optional)</label>
                            <div class="input-wrapper">
                                <span class="input-icon">🪙</span>
                                <input type="text" id="toss" name="toss" placeholder="e.g. CSK won the toss & elected to bat first" value="{{ old('toss') }}">
                            </div>
                            <small class="form-help">Optionally record details of the toss and dynamic starting decision.</small>
                        </div>
                    </div>

                    <!-- Action buttons -->
                    <div class="form-actions-row">
                        <a href="{{ route('site.gamehub.actionboard') }}" class="cancel-link-btn">Cancel & Return</a>
                        <button type="submit" class="create-submit-btn">
                            Initialize Match Room
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
/* --------------------------------------------------
   Create Live Match View - Luxury Redesign stylesheet
   Theme: Premium Forest-Emerald & Glassmorphic borders
-------------------------------------------------- */
.actionboard-create-page {
    padding: 60px 0 90px;
    background-color: #f4f7f5;
    background-image: radial-gradient(circle at 10% 20%, rgba(9, 55, 39, 0.03) 0%, transparent 40%),
                      radial-gradient(circle at 90% 80%, rgba(16, 185, 129, 0.03) 0%, transparent 40%);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    min-height: 100vh;
}

.container--narrow {
    max-width: 620px;
    margin: 0 auto;
    padding: 0 20px;
}

/* Glassmorphic Main Card */
.create-match-card {
    background: #ffffff;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 20px 50px rgba(9, 55, 39, 0.08);
    border: 1px solid rgba(9, 55, 39, 0.07);
}

.create-match-card__header {
    background: linear-gradient(135deg, #093727 0%, #0d543c 50%, #111c18 100%);
    padding: 32px;
    color: #ffffff;
    text-align: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.brand-sub {
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: #34d399;
    margin-bottom: 8px;
}

.create-match-card__header h2 {
    font-size: 22px;
    font-weight: 900;
    margin: 0 0 10px;
    letter-spacing: 0.5px;
}

.create-match-card__header p {
    font-size: 13px;
    color: rgba(255, 255, 255, 0.75);
    margin: 0;
    line-height: 1.5;
}

.create-match-card__body {
    padding: 36px;
}

/* Error banner styling */
.modal-error-alert {
    background: #fef2f2;
    border-left: 4px solid #ef4444;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 28px;
}

.modal-error-alert__header {
    font-size: 13px;
    font-weight: 800;
    color: #991b1b;
    margin-bottom: 8px;
}

.modal-error-alert__list {
    margin: 0;
    padding-left: 20px;
    font-size: 12px;
    color: #b91c1c;
    font-weight: 600;
    line-height: 1.5;
}

/* Form controls and grid */
.form-grid {
    display: flex;
    flex-direction: column;
    gap: 24px;
    margin-bottom: 32px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-group label {
    font-size: 13px;
    font-weight: 800;
    color: #093727;
    letter-spacing: 0.2px;
}

.input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.input-icon {
    position: absolute;
    left: 14px;
    font-size: 16px;
    pointer-events: none;
    color: #64748b;
}

.input-wrapper input {
    width: 100%;
    padding: 12px 14px 12px 42px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 700;
    color: #0f172a;
    background-color: #ffffff;
    transition: all 0.2s ease;
}

.input-wrapper input:focus {
    border-color: #10b981;
    outline: none;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
    background-color: #fcfefe;
}

.form-help {
    font-size: 11px;
    color: #64748b;
    font-weight: 500;
    line-height: 1.4;
}

/* Action button rows */
.form-actions-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid #f1f5f9;
    padding-top: 24px;
}

.cancel-link-btn {
    color: #64748b;
    text-decoration: none;
    font-size: 13px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    transition: color 0.15s ease;
}

.cancel-link-btn:hover {
    color: #093727;
}

.create-submit-btn {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: #ffffff;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
    transition: all 0.25s ease;
}

.create-submit-btn:hover {
    background: linear-gradient(135deg, #34d399 0%, #10b981 100%);
    box-shadow: 0 6px 16px rgba(16, 185, 129, 0.3);
    transform: translateY(-1.5px);
}

/* Transitions */
.animate-fade-in {
    animation: fadeIn 0.4s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
@endsection
