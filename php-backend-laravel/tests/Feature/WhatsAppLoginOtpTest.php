<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MessageTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phone sign-in over WhatsApp, with Firebase SMS underneath.
 *
 * The fallback is what these mostly cover, because it is the half that must never
 * break: a login is the one message with nothing behind it, so every reason
 * WhatsApp might fail has to come back as `channel: "sms"` and let the browser run
 * the flow it always ran. An error on screen here means someone can't get in.
 */
class WhatsAppLoginOtpTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '+919876543210';

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
        return $this->postJson(route('whatsapp.otp.start'), ['phone' => $phone]);
    }

    // --- the fallback ---------------------------------------------------------

    public function test_no_approved_template_falls_back_to_sms(): void
    {
        Http::fake();

        $this->start()->assertOk()->assertJson(['channel' => 'sms']);

        Http::assertNothingSent();
    }

    public function test_a_provider_failure_falls_back_to_sms_rather_than_erroring(): void
    {
        $this->approveTemplate();
        // MSG91's rejection shape: HTTP 200, body says no.
        Http::fake(['control.msg91.com/*' => Http::response(['type' => 'error', 'message' => 'nope'], 200)]);

        $this->start()
            ->assertOk()          // NOT an error — the browser needs a routing answer
            ->assertJson(['channel' => 'sms']);
    }

    public function test_whatsapp_switched_off_falls_back_to_sms(): void
    {
        $this->approveTemplate();
        config(['services.whatsapp.enabled' => false]);
        Http::fake();

        $this->start()->assertOk()->assertJson(['channel' => 'sms']);
    }

    public function test_an_unroutable_number_falls_back_without_calling_the_provider(): void
    {
        $this->approveTemplate();
        Http::fake();

        $this->start('123')->assertOk()->assertJson(['channel' => 'sms']);

        Http::assertNothingSent();
    }

    // --- the WhatsApp path ----------------------------------------------------

    public function test_the_code_goes_out_as_an_authentication_template(): void
    {
        $this->approveTemplate();
        Http::fake(['control.msg91.com/*' => Http::response(['type' => 'success', 'request_id' => 'r1'], 200)]);

        $response = $this->start()->assertOk();

        $this->assertSame('whatsapp', $response->json('channel'));
        $this->assertNotEmpty($response->json('token'));

        Http::assertSent(function ($request): bool {
            $components = $request->data()['payload']['template']['to_and_components'][0]['components'];

            return $request->data()['payload']['template']['name'] === 'login_otp'
                // The code is sent twice — body and copy-code button — or WhatsApp
                // rejects the message for a component mismatch.
                && preg_match('/^\d{6}$/', $components['body_1']['value']) === 1
                && $components['button_1']['value'] === $components['body_1']['value'];
        });
    }

    public function test_the_right_code_signs_them_in_and_creates_the_account(): void
    {
        $this->approveTemplate();
        Http::fake(['control.msg91.com/*' => Http::response(['type' => 'success'], 200)]);

        $token = $this->start()->json('token');
        $code = $this->codeFor($token);

        $this->postJson(route('whatsapp.otp.verify'), ['token' => $token, 'code' => $code])
            ->assertOk()
            ->assertJsonStructure(['redirect']);

        $user = User::query()->where('phone', self::PHONE)->first();

        $this->assertNotNull($user);
        $this->assertAuthenticatedAs($user);
        // The SAME placeholder convention the Firebase flow uses, so the two
        // channels can never mint two accounts for one person.
        $this->assertSame(self::PHONE . '@phone.haraan.local', $user->email);
    }

    public function test_an_existing_firebase_account_is_reused_not_duplicated(): void
    {
        $existing = User::create([
            'name' => 'Asha', 'email' => self::PHONE . '@phone.haraan.local',
            'phone' => self::PHONE, 'password' => bcrypt('x'), 'role' => 'user', 'status' => 'active',
        ]);

        $this->approveTemplate();
        Http::fake(['control.msg91.com/*' => Http::response(['type' => 'success'], 200)]);

        $token = $this->start()->json('token');
        $this->postJson(route('whatsapp.otp.verify'), ['token' => $token, 'code' => $this->codeFor($token)])
            ->assertOk();

        $this->assertSame(1, User::query()->where('phone', self::PHONE)->count());
        $this->assertAuthenticatedAs($existing->fresh());
    }

    public function test_a_wrong_code_is_refused_and_nobody_is_signed_in(): void
    {
        $this->approveTemplate();
        Http::fake(['control.msg91.com/*' => Http::response(['type' => 'success'], 200)]);

        $token = $this->start()->json('token');

        $this->postJson(route('whatsapp.otp.verify'), ['token' => $token, 'code' => '000000'])
            ->assertStatus(422);

        $this->assertGuest();
    }

    public function test_a_code_cannot_be_used_twice(): void
    {
        $this->approveTemplate();
        Http::fake(['control.msg91.com/*' => Http::response(['type' => 'success'], 200)]);

        $token = $this->start()->json('token');
        $code = $this->codeFor($token);

        $this->postJson(route('whatsapp.otp.verify'), ['token' => $token, 'code' => $code])->assertOk();

        // A replayed request must not read as a second successful verification.
        $this->postJson(route('whatsapp.otp.verify'), ['token' => $token, 'code' => $code])->assertStatus(410);
    }

    public function test_guessing_burns_the_code_rather_than_allowing_a_walk(): void
    {
        $this->approveTemplate();
        Http::fake(['control.msg91.com/*' => Http::response(['type' => 'success'], 200)]);

        $token = $this->start()->json('token');
        $code = $this->codeFor($token);

        for ($i = 0; $i < 4; $i++) {
            $this->postJson(route('whatsapp.otp.verify'), ['token' => $token, 'code' => '000000'])->assertStatus(422);
        }

        // The fifth wrong attempt burns it...
        $this->postJson(route('whatsapp.otp.verify'), ['token' => $token, 'code' => '000000'])->assertStatus(429);

        // ...and the real code no longer works either.
        $this->postJson(route('whatsapp.otp.verify'), ['token' => $token, 'code' => $code])->assertStatus(410);
        $this->assertGuest();
    }

    public function test_an_unknown_token_is_expired_not_a_wrong_code(): void
    {
        $this->postJson(route('whatsapp.otp.verify'), ['token' => 'nope', 'code' => '123456'])
            ->assertStatus(410);
    }

    /**
     * Read the issued code back out of the cache.
     *
     * Only the HMAC is stored, so the code is recovered by matching it — six digits
     * is a small enough space that this is faster than plumbing a test seam into
     * the controller, and it proves the hashing works at the same time.
     */
    private function codeFor(string $token): string
    {
        $payload = Cache::get('wa-login-otp:' . $token);
        $this->assertIsArray($payload, 'the OTP session should exist');

        for ($i = 100000; $i <= 999999; $i++) {
            if (hash_equals((string) $payload['otp'], hash_hmac('sha256', (string) $i, (string) config('app.key')))) {
                return (string) $i;
            }
        }

        $this->fail('could not recover the issued code');
    }

    // --- partner console surface ------------------------------------------------

    private function partner(string $storedPhone): User
    {
        return User::create([
            'name' => 'Partner', 'phone' => $storedPhone, 'email' => 'p'.uniqid().'@example.test',
            'password' => bcrypt('secret'), 'role' => 'PARTNER', 'status' => 'active',
            'partner_type' => 'event',
        ]);
    }

    public function test_a_partner_signs_into_the_console_not_the_member_site(): void
    {
        $partner = $this->partner(self::PHONE);
        $this->approveTemplate();
        Http::fake(['control.msg91.com/*' => Http::response(['status' => 'success', 'request_id' => 'r1'], 200)]);

        $token = $this->postJson(route('whatsapp.otp.start'), ['phone' => self::PHONE, 'surface' => 'partner'])
            ->assertOk()->json('token');

        $this->postJson(route('whatsapp.otp.verify'), ['token' => $token, 'code' => $this->codeFor($token)])
            ->assertOk()
            ->assertJsonPath('redirect', route('filament.partner.pages.dashboard'));

        $this->assertSame($partner->id, Auth::id());
    }

    public function test_a_partner_whose_number_is_stored_as_bare_digits_still_matches(): void
    {
        // Admins enter partner numbers in every shape. An exact E.164 match would
        // silently tell a real partner no account exists.
        $partner = $this->partner('9876543210');
        $this->approveTemplate();
        Http::fake(['control.msg91.com/*' => Http::response(['status' => 'success', 'request_id' => 'r1'], 200)]);

        $token = $this->postJson(route('whatsapp.otp.start'), ['phone' => self::PHONE, 'surface' => 'partner'])
            ->assertOk()->json('token');

        $this->postJson(route('whatsapp.otp.verify'), ['token' => $token, 'code' => $this->codeFor($token)])->assertOk();

        $this->assertSame($partner->id, Auth::id());
    }

    public function test_the_partner_surface_never_creates_an_account(): void
    {
        // firstOrCreate here would mint a stray member for a partner whose number
        // simply was not stored as E.164 — and hand them a console they can't use.
        $this->approveTemplate();
        Http::fake(['control.msg91.com/*' => Http::response(['status' => 'success', 'request_id' => 'r1'], 200)]);
        $before = User::query()->count();

        $this->postJson(route('whatsapp.otp.start'), ['phone' => self::PHONE, 'surface' => 'partner'])
            ->assertOk()
            ->assertJson(['channel' => 'sms']);

        $this->assertSame($before, User::query()->count());
    }

    public function test_a_member_code_cannot_be_redeemed_against_the_partner_console(): void
    {
        // The surface is bound to the code when it is SENT, so flipping a field on
        // the verify request cannot escalate a member login into a console login.
        $this->partner(self::PHONE);
        $this->approveTemplate();
        Http::fake(['control.msg91.com/*' => Http::response(['status' => 'success', 'request_id' => 'r1'], 200)]);

        $token = $this->postJson(route('whatsapp.otp.start'), ['phone' => self::PHONE])->json('token');

        $response = $this->postJson(route('whatsapp.otp.verify'), [
            'token' => $token, 'code' => $this->codeFor($token), 'surface' => 'partner',
        ])->assertOk();

        $this->assertNotSame(route('filament.partner.pages.dashboard'), $response->json('redirect'));
    }
}
