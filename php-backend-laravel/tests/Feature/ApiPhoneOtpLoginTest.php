<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MessageTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The Android app's "Continue with phone" — WhatsApp (MSG91) first, Firebase SMS
 * beneath it. Token-returning twin of {@see WhatsAppLoginOtpTest}.
 *
 * Two things these exist to protect:
 *
 *  1. **The silent fallback.** Every reason WhatsApp can't be used must come back as
 *     `channel: "sms"`, never an error. A login is the one message with nothing
 *     behind it, so an error on screen means someone simply cannot get in.
 *
 *  2. **One account per number.** The app, the website's WhatsApp flow and the
 *     Firebase flow must all resolve to the SAME user row. The older
 *     /api/auth/whatsapp/* endpoint keys on bare digits + `@whatsapp.local`; if this
 *     controller ever drifted to that convention, every web user signing in on the
 *     app would silently get a second account.
 */
class ApiPhoneOtpLoginTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE_E164 = '+919876543210';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.driver' => 'msg91',
            'services.whatsapp.msg91.auth_key' => 'test-authkey',
            'services.whatsapp.msg91.integrated_number' => '918000000000',
        ]);
    }

    private function approveTemplate(): void
    {
        MessageTemplate::create([
            'key' => 'auth.login_otp', 'name' => 'Login OTP', 'channel' => 'whatsapp',
            'category' => 'authentication', 'locale' => 'en', 'body' => '{{1}} is your code',
            'variables' => [], 'provider_template_id' => 'login_otp',
            'status' => 'approved', 'is_active' => true,
        ]);
    }

    private function start(string $phone = '9876543210'): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/auth/phone-otp/start', ['phone' => $phone]);
    }

    // ── The fallback half ────────────────────────────────────────────────────

    public function test_start_falls_back_to_sms_when_no_template_is_approved(): void
    {
        Http::fake();

        $this->start()->assertOk()->assertExactJson(['channel' => 'sms']);
    }

    public function test_start_falls_back_to_sms_when_the_number_is_unroutable(): void
    {
        $this->approveTemplate();
        Http::fake();

        $this->start('12')->assertOk()->assertJson(['channel' => 'sms']);
    }

    public function test_start_falls_back_to_sms_when_the_provider_fails(): void
    {
        $this->approveTemplate();
        Http::fake(['*' => Http::response(['message' => 'nope'], 500)]);

        $this->start()->assertOk()->assertJson(['channel' => 'sms']);
    }

    // ── The WhatsApp half ────────────────────────────────────────────────────

    public function test_start_uses_whatsapp_when_the_template_is_approved_and_the_send_works(): void
    {
        $this->approveTemplate();
        Http::fake(['*' => Http::response(['type' => 'success', 'request_id' => 'req-1'], 200)]);

        $this->start()
            ->assertOk()
            ->assertJsonPath('channel', 'whatsapp')
            ->assertJsonStructure(['channel', 'token', 'expires_in']);
    }

    // ── Verification ─────────────────────────────────────────────────────────

    /** Seeds a verified session the way a successful start() would have. */
    private function seedSession(string $code = '654321', string $phone = self::PHONE_E164): string
    {
        $token = 'test-token-'.uniqid();

        Cache::put('app-login-otp:'.$token, [
            'phone' => $phone,
            'otp' => hash_hmac('sha256', $code, (string) config('app.key')),
            'attempts' => 0,
        ], 300);

        return $token;
    }

    public function test_verify_issues_a_token_and_creates_the_account_on_the_shared_convention(): void
    {
        $token = $this->seedSession();

        $this->postJson('/api/auth/phone-otp/verify', ['token' => $token, 'code' => '654321'])
            ->assertOk()
            ->assertJsonPath('newUser', true)
            ->assertJsonStructure(['message', 'newUser', 'token', 'user']);

        // The whole point: E.164 phone + the SAME placeholder suffix the Firebase and
        // website flows use, so no channel can mint a second account for one person.
        $user = User::query()->where('phone', self::PHONE_E164)->first();
        $this->assertNotNull($user);
        $this->assertSame(self::PHONE_E164.'@phone.haraan.local', $user->email);
    }

    public function test_verify_logs_an_existing_number_in_rather_than_creating_a_second_account(): void
    {
        // Exactly what the website's Firebase/WhatsApp flow would have created.
        $existing = User::factory()->create([
            'phone' => self::PHONE_E164,
            'email' => self::PHONE_E164.'@phone.haraan.local',
        ]);

        $token = $this->seedSession();

        $this->postJson('/api/auth/phone-otp/verify', ['token' => $token, 'code' => '654321'])
            ->assertOk()
            ->assertJsonPath('newUser', false)
            ->assertJsonPath('user.id', $existing->id);

        $this->assertSame(1, User::query()->where('phone', self::PHONE_E164)->count());
    }

    public function test_verify_rejects_a_wrong_code_and_burns_the_session_after_five_tries(): void
    {
        $token = $this->seedSession();

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/auth/phone-otp/verify', ['token' => $token, 'code' => '000000'])
                ->assertStatus(422);
        }

        // Fifth wrong guess burns it rather than letting a 6-digit code be walked.
        $this->postJson('/api/auth/phone-otp/verify', ['token' => $token, 'code' => '000000'])
            ->assertStatus(429);

        // Even the RIGHT code is dead now.
        $this->postJson('/api/auth/phone-otp/verify', ['token' => $token, 'code' => '654321'])
            ->assertStatus(410);
    }

    public function test_verify_is_single_use(): void
    {
        $token = $this->seedSession();

        $this->postJson('/api/auth/phone-otp/verify', ['token' => $token, 'code' => '654321'])->assertOk();
        $this->postJson('/api/auth/phone-otp/verify', ['token' => $token, 'code' => '654321'])->assertStatus(410);
    }

    public function test_verify_rejects_an_unknown_token(): void
    {
        $this->postJson('/api/auth/phone-otp/verify', ['token' => 'nope', 'code' => '654321'])
            ->assertStatus(410);
    }
}
