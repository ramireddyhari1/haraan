@extends('site.layout')

@section('content')
<section class="auth-shell" style="padding: 40px 0;">
    <div class="auth-card" style="max-width: 580px; width: 100%; border: 1px solid rgba(13, 84, 60, 0.15); box-shadow: 0 20px 40px rgba(9,55,39,0.06);">
        <div class="auth-header" style="background: linear-gradient(135deg, #093727 0%, #0d543c 100%); padding: 32px; border-radius: 12px 12px 0 0; color: #fff; text-align: center;">
            <span style="font-size: 11px; font-weight: 800; letter-spacing: 1.5px; color: #34d399; text-transform: uppercase;">Cricket Onboarding</span>
            <h1 style="margin: 8px 0; font-size: 24px; font-weight: 900; color: #fff; border: none; padding: 0;">Permanent Cricket Identity</h1>
            <p style="color: rgba(255,255,255,0.8); font-size: 13px; line-height: 1.5;">Complete your profile once to unlock your live stats, ranks, and district leaderboard eligibility.</p>
        </div>

        <div style="padding: 32px;">
            <!-- Player ID Live Preview Card -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px; margin-bottom: 28px; display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <span style="font-size: 10px; font-weight: 800; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px; display: block; margin-bottom: 4px;">Player ID Preview</span>
                    <strong id="id_preview" style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 20px; font-weight: 900; color: #0d543c; letter-spacing: 0.5px;">HRN-SS-DDD-{{ str_pad((string)$user->id, 5, '0', STR_PAD_LEFT) }}</strong>
                </div>
                <div style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); color: #10b981; padding: 6px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                    League Grade
                </div>
            </div>

            <!-- Profile Form -->
            <form method="POST" action="{{ route('site.profile.setup.save') }}" enctype="multipart/form-data" class="auth-form">
                @csrf
                <input type="hidden" name="claim_user_id" id="claim_user_id" value="">

                <!-- Name Input -->
                <div class="field" style="margin-bottom: 20px;">
                    <label for="name" style="font-weight: 800; font-size: 13px; color: #093727; display: block; margin-bottom: 8px;">Full Name</label>
                    <input 
                        type="text" 
                        id="name" 
                        name="name" 
                        placeholder="e.g. Hariharan Reddy"
                        required
                        style="width: 100%; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; font-weight: 700;"
                        oninput="checkClaimablePlayers(this.value)"
                    >
                </div>

                <!-- State & District Flex Row -->
                <div style="display: flex; gap: 16px; margin-bottom: 20px;">
                    <div class="field" style="flex: 1;">
                        <label for="state" style="font-weight: 800; font-size: 13px; color: #093727; display: block; margin-bottom: 8px;">State</label>
                        <input 
                            type="text" 
                            id="state" 
                            name="state" 
                            placeholder="e.g. Andhra Pradesh"
                            required
                            style="width: 100%; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; font-weight: 700;"
                            oninput="updatePlayerIdPreview()"
                        >
                    </div>
                    <div class="field" style="flex: 1;">
                        <label for="district" style="font-weight: 800; font-size: 13px; color: #093727; display: block; margin-bottom: 8px;">District</label>
                        <input 
                            type="text" 
                            id="district" 
                            name="district" 
                            placeholder="e.g. Kadapa"
                            required
                            style="width: 100%; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; font-weight: 700;"
                            oninput="updatePlayerIdPreview()"
                        >
                    </div>
                </div>

                <!-- Claiming Widget (Hidden by default, shown when matches found) -->
                <div id="claiming_widget" style="display: none; background: #fff6ed; border: 1px solid #ffedd5; border-radius: 12px; padding: 18px; margin-bottom: 24px; border-left: 4px solid #f97316;">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                        <span style="font-size: 16px;">🔥</span>
                        <h4 style="margin: 0; font-size: 14px; font-weight: 850; color: #9a3412;">Found Matching Guest Records!</h4>
                    </div>
                    <p style="margin: 0 0 14px 0; font-size: 12px; color: #7c2d12; line-height: 1.4;">An organizer added a guest profile with your name. Claim it to merge all historical match stats into your new account:</p>
                    
                    <div id="claimable_list" style="display: flex; flex-direction: column; gap: 8px;"></div>
                </div>

                <!-- Playing Styles Flex Row -->
                <div style="display: flex; gap: 16px; margin-bottom: 20px;">
                    <div class="field" style="flex: 1;">
                        <label for="batting_style" style="font-weight: 800; font-size: 13px; color: #093727; display: block; margin-bottom: 8px;">Batting Style</label>
                        <select id="batting_style" name="batting_style" required style="width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; font-weight: 700; background: #fff; height: 48px;">
                            <option value="">Select Batting Style</option>
                            <option value="Right-hand bat">Right-hand bat</option>
                            <option value="Left-hand bat">Left-hand bat</option>
                        </select>
                    </div>
                    <div class="field" style="flex: 1;">
                        <label for="bowling_style" style="font-weight: 800; font-size: 13px; color: #093727; display: block; margin-bottom: 8px;">Bowling Style</label>
                        <select id="bowling_style" name="bowling_style" required style="width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; font-weight: 700; background: #fff; height: 48px;">
                            <option value="">Select Bowling Style</option>
                            <option value="Right-arm fast">Right-arm fast</option>
                            <option value="Right-arm medium">Right-arm medium</option>
                            <option value="Right-arm spin">Right-arm spin</option>
                            <option value="Left-arm fast">Left-arm fast</option>
                            <option value="Left-arm medium">Left-arm medium</option>
                            <option value="Left-arm spin">Left-arm spin</option>
                            <option value="None / All-Rounder">None / Batsman Only</option>
                        </select>
                    </div>
                </div>

                <!-- Photo Upload -->
                <div class="field" style="margin-bottom: 30px;">
                    <label for="photo" style="font-weight: 800; font-size: 13px; color: #093727; display: block; margin-bottom: 8px;">Profile Photo (Optional)</label>
                    <input 
                        type="file" 
                        id="photo" 
                        name="photo" 
                        accept="image/*"
                        style="width: 100%; padding: 10px; border: 1px dashed #cbd5e1; border-radius: 8px; font-size: 13px; font-weight: 700; background: #f8fafc;"
                    >
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn btn--solid btn--full btn--large" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #fff; border: none; font-weight: 800; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; height: 50px; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);">
                    Create Cricket Profile
                </button>
            </form>
        </div>
    </div>
</section>

<script>
    function updatePlayerIdPreview() {
        const state = document.getElementById('state').value.trim();
        const district = document.getElementById('district').value.trim();
        
        let statePart = 'SS';
        if (state) {
            const words = state.replace(/[^A-Za-z ]/g, '').split(/\s+/).filter(Boolean);
            if (words.length >= 2) {
                statePart = words.map(w => w[0]).join('').substring(0, 3);
            } else if (words.length === 1) {
                statePart = words[0].substring(0, 2);
            }
            statePart = statePart.toUpperCase();
        }
        
        let distPart = 'DDD';
        if (district) {
            const distClean = district.replace(/[^A-Za-z]/g, '');
            if (distClean.length > 0) {
                const first = distClean[0];
                const rest = distClean.substring(1);
                const restNoVowels = rest.replace(/[aeiouAEIOU]/g, '');
                distPart = (first + restNoVowels).substring(0, 3).toUpperCase();
            }
        }
        
        const userId = '{{ str_pad((string)$user->id, 5, "0", STR_PAD_LEFT) }}';
        document.getElementById('id_preview').innerText = `HRN-${statePart}-${distPart}-${userId}`;
    }

    let claimTimeout = null;
    function checkClaimablePlayers(name) {
        clearTimeout(claimTimeout);
        if (name.length < 2) {
            document.getElementById('claiming_widget').style.display = 'none';
            document.getElementById('claim_user_id').value = '';
            return;
        }

        claimTimeout = setTimeout(async () => {
            try {
                const res = await fetch(`/api/players/claimable?name=${encodeURIComponent(name)}`);
                const data = await res.json();
                
                const widget = document.getElementById('claiming_widget');
                const list = document.getElementById('claimable_list');
                
                if (data.length > 0) {
                    list.innerHTML = '';
                    data.forEach(item => {
                        const div = document.createElement('div');
                        div.style.cssText = "display: flex; align-items: center; justify-content: space-between; background: #fff; padding: 10px 14px; border: 1px solid #fed7aa; border-radius: 8px; cursor: pointer; transition: all 0.2s;";
                        div.innerHTML = `
                            <div>
                                <span style="font-weight: 700; color: #1e293b; font-size: 13px;">${item.name}</span>
                                <span style="font-size: 11px; color: #64748b; margin-left: 8px;">(ID: ${item.player_id})</span>
                                <div style="font-size: 11px; color: #f97316; font-weight: 600; margin-top: 2px;">Played in: ${item.played_with}</div>
                            </div>
                            <button type="button" class="btn btn--solid" style="background: #f97316; border-color: #f97316; color: #fff; font-size: 11px; padding: 4px 12px; height: auto;">Claim Profile</button>
                        `;
                        div.onclick = () => selectClaimPlayer(item.id, item.name, div);
                        list.appendChild(div);
                    });
                    widget.style.display = 'block';
                } else {
                    widget.style.display = 'none';
                    document.getElementById('claim_user_id').value = '';
                }
            } catch (e) {
                console.error('Error fetching claimable players', e);
            }
        }, 300);
    }

    function selectClaimPlayer(id, name, element) {
        // Reset all backgrounds
        document.querySelectorAll('#claimable_list > div').forEach(div => {
            div.style.background = '#fff';
            div.style.borderColor = '#fed7aa';
            const btn = div.querySelector('button');
            if (btn) {
                btn.innerText = 'Claim Profile';
                btn.style.background = '#f97316';
            }
        });

        // Set active style
        element.style.background = '#fff7ed';
        element.style.borderColor = '#f97316';
        const activeBtn = element.querySelector('button');
        if (activeBtn) {
            activeBtn.innerText = 'Selected ✅';
            activeBtn.style.background = '#16a34a';
            activeBtn.style.borderColor = '#16a34a';
        }

        document.getElementById('claim_user_id').value = id;
    }
</script>
@endsection
