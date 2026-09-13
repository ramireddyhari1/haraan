{{--
    Split brand panel for the Haraan Employee Portal (/employee) sign-in screen.
    Injected at PanelsRenderHook::SIMPLE_LAYOUT_START from EmployeePanelProvider.
--}}
<div class="hrn-empbrand" aria-hidden="true">
    <div class="hrn-empbrand__glow hrn-empbrand__glow--a"></div>
    <div class="hrn-empbrand__glow hrn-empbrand__glow--b"></div>
    <div class="hrn-empbrand__grid"></div>

    <div class="hrn-empbrand__inner">
        <div class="hrn-empbrand__top">
            <img class="hrn-empbrand__logo"
                 src="{{ asset('images/haraan-logo-white.png') }}"
                 alt="haraan" width="1680" height="445">
            <span class="hrn-empbrand__pill">EMPLOYEE</span>
        </div>

        <div class="hrn-empbrand__mid">
            <h1 class="hrn-empbrand__headline">
                Your Daily Workplace.<br>Everything in one place.
            </h1>
            <p class="hrn-empbrand__sub">
                HARAAN Employee Self-Service — punch in with GPS &amp; QR, check your shift rosters, track floor tasks, apply for leave, and view your digital payslips.
            </p>

            <ul class="hrn-empbrand__chips">
                <li><span class="hrn-empbrand__chip-ic">⏱</span> GPS Geofenced Clock-in</li>
                <li><span class="hrn-empbrand__chip-ic">🗓</span> Shift Rosters &amp; Swaps</li>
                <li><span class="hrn-empbrand__chip-ic">💰</span> Digital Payslips &amp; KPIs</li>
            </ul>
        </div>

        <div class="hrn-empbrand__mock">
            <div class="hrn-empbrand__mock-row">
                <div class="hrn-empbrand__mock-kpi">
                    <span class="hrn-empbrand__mock-k">09:00 AM</span>
                    <span class="hrn-empbrand__mock-l">Clock-in Time</span>
                </div>
                <div class="hrn-empbrand__mock-kpi">
                    <span class="hrn-empbrand__mock-k">Inside</span>
                    <span class="hrn-empbrand__mock-l">Geofence Status</span>
                </div>
                <div class="hrn-empbrand__mock-kpi">
                    <span class="hrn-empbrand__mock-k">4.9 ★</span>
                    <span class="hrn-empbrand__mock-l">KPI Rating</span>
                </div>
            </div>
        </div>

        <div class="hrn-empbrand__bottom">
            <span>HARAAN HRMS &bull; Enterprise Workforce System</span>
        </div>
    </div>
</div>

<style>
    .hrn-empbrand {
        position: relative;
        overflow: hidden;
        background: linear-gradient(135deg, #064e3b 0%, #065f46 45%, #047857 100%);
        color: #ecfdf5;
        padding: 48px 40px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        min-height: 100%;
        border-radius: 20px 0 0 20px;
    }
    .hrn-empbrand__inner {
        position: relative;
        z-index: 2;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        height: 100%;
    }
    .hrn-empbrand__top {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .hrn-empbrand__logo {
        height: 28px;
        width: auto;
        display: block;
    }
    .hrn-empbrand__pill {
        font-size: 11px;
        font-weight: 800;
        letter-spacing: .08em;
        background: rgba(16, 185, 129, 0.25);
        border: 1px solid rgba(110, 231, 183, 0.4);
        color: #a7f3d0;
        padding: 3px 10px;
        border-radius: 9999px;
        text-transform: uppercase;
    }
    .hrn-empbrand__mid {
        margin: 40px 0;
    }
    .hrn-empbrand__headline {
        font-size: 32px;
        font-weight: 800;
        line-height: 1.15;
        color: #ffffff;
        letter-spacing: -0.02em;
        margin: 0 0 16px;
    }
    .hrn-empbrand__sub {
        font-size: 14px;
        line-height: 1.6;
        color: #a7f3d0;
        max-width: 440px;
        margin: 0 0 24px;
    }
    .hrn-empbrand__chips {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .hrn-empbrand__chips li {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 13.5px;
        font-weight: 600;
        color: #ecfdf5;
    }
    .hrn-empbrand__chip-ic {
        width: 24px;
        height: 24px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        background: rgba(16, 185, 129, 0.3);
        color: #34d399;
        font-size: 13px;
    }
    .hrn-empbrand__mock {
        background: rgba(2, 44, 34, 0.5);
        border: 1px solid rgba(16, 185, 129, 0.25);
        backdrop-filter: blur(8px);
        border-radius: 12px;
        padding: 16px 20px;
        margin-top: 20px;
    }
    .hrn-empbrand__mock-row {
        display: flex;
        justify-content: space-between;
    }
    .hrn-empbrand__mock-kpi {
        display: flex;
        flex-direction: column;
    }
    .hrn-empbrand__mock-k {
        font-size: 16px;
        font-weight: 700;
        color: #ffffff;
    }
    .hrn-empbrand__mock-l {
        font-size: 11px;
        color: #6ee7b7;
        margin-top: 2px;
    }
    .hrn-empbrand__bottom {
        font-size: 11.5px;
        color: #6ee7b7;
        letter-spacing: .02em;
        margin-top: 30px;
    }
    @media (max-width: 1023px) {
        .hrn-empbrand {
            display: none;
        }
    }
</style>
