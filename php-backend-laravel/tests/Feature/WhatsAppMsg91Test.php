<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Event;
use App\Models\MessageLog;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Services\BookingLedger;
use App\Services\PaymentNotifier;
use App\Services\WhatsAppService;
use App\Support\MessageContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * WhatsApp over MSG91 as the BSP.
 *
 * Two things are worth pinning down and nothing else is. First, that templates and
 * free text go to DIFFERENT MSG91 endpoints — sending a template down the
 * single-message endpoint is accepted and never delivered, which is invisible
 * until a customer says they got nothing. Second, that an HTTP 200 carrying
 * {"type":"error"} is recorded as a FAILURE: MSG91 rejects that way, and reading
 * the status code as the verdict would log every rejection as a delivered ticket.
 */
class WhatsAppMsg91Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.driver' => 'msg91',
            'services.whatsapp.msg91.auth_key' => 'test-authkey',
            'services.whatsapp.msg91.integrated_number' => '918000000000',
            'services.whatsapp.msg91.base_url' => 'https://control.msg91.com/api/v5',
        ]);
    }

    private function fakeMsg91(array $body = ['type' => 'success', 'request_id' => 'req-1'], int $status = 200): void
    {
        Http::fake(['control.msg91.com/*' => Http::response($body, $status)]);
    }

    private function approvedTemplate(string $key, string $name, string $category = 'utility'): MessageTemplate
    {
        return MessageTemplate::create([
            'key' => $key, 'name' => $key, 'channel' => 'whatsapp', 'category' => $category,
            'locale' => 'en', 'body' => 'body', 'variables' => [],
            'provider_template_id' => $name, 'status' => 'approved', 'is_active' => true,
        ]);
    }

    public function test_a_template_goes_to_the_bulk_endpoint_with_positional_variables_named(): void
    {
        $this->fakeMsg91();

        $ok = app(WhatsAppService::class)->sendTemplate(
            '9876543210',
            'booking_confirmation',
            ['Sunburn Arena', '12 Sep, 7:00 PM', 'https://haraan.app/t/ABC'],
        );

        $this->assertTrue($ok);

        Http::assertSent(function ($request): bool {
            $data = $request->data();
            $template = $data['payload']['template'];
            $components = $template['to_and_components'][0]['components'];

            return str_ends_with($request->url(), '/whatsapp/whatsapp-outbound-message/bulk/')
                && $request->header('authkey')[0] === 'test-authkey'
                && $data['integrated_number'] === '918000000000'
                && $data['content_type'] === 'template'
                && $template['name'] === 'booking_confirmation'
                && $template['language'] === ['code' => 'en', 'policy' => 'deterministic']
                // E.164 without the plus, country code filled in from the bare 10 digits.
                && $template['to_and_components'][0]['to'] === ['919876543210']
                && $components['body_1'] === ['type' => 'text', 'value' => 'Sunburn Arena']
                && $components['body_2'] === ['type' => 'text', 'value' => '12 Sep, 7:00 PM']
                && $components['body_3'] === ['type' => 'text', 'value' => 'https://haraan.app/t/ABC'];
        });
    }

    public function test_free_text_goes_to_the_single_message_endpoint_instead(): void
    {
        $this->fakeMsg91();

        app(WhatsAppService::class)->sendMessage('9876543210', 'Hello there');

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            // Flat, and deliberately NOT the Meta-style payload.to/text.body
            // envelope the template endpoint uses. Confirmed against the live API:
            // the nested form is rejected with "recipient_number not found".
            return str_ends_with($request->url(), '/whatsapp/whatsapp-outbound-message/')
                && $data['content_type'] === 'text'
                && $data['recipient_number'] === '919876543210'
                && $data['text'] === 'Hello there'
                && ! isset($data['payload']);
        });
    }

    public function test_a_single_message_id_is_read_from_data_message_uuid(): void
    {
        // The two endpoints name the identifier differently — bulk returns
        // `request_id` at the top level, single-message returns
        // `data.message_uuid`. Both are confirmed live, and losing either means a
        // ledger row with nothing to reconcile a delivery report against.
        $this->fakeMsg91([
            'status' => 'success',
            'hasError' => false,
            'data' => ['message_uuid' => 'uuid-123', 'message' => 'Your request is in process'],
            'errors' => null,
        ]);

        app(WhatsAppService::class)->sendMessage('9876543210', 'Hello');

        $this->assertSame('uuid-123', MessageLog::query()->latest('id')->first()->provider_message_id);
    }

    public function test_a_status_fail_body_on_a_400_is_recorded_with_the_real_reason(): void
    {
        // Their validation shape, captured live.
        $this->fakeMsg91([
            'status' => 'fail', 'hasError' => true, 'data' => null,
            'errors' => 'recipient_number not found in request',
        ], 400);

        $this->assertFalse(app(WhatsAppService::class)->sendMessage('9876543210', 'Hello'));

        $log = MessageLog::query()->latest('id')->first();
        $this->assertSame(MessageLog::STATUS_FAILED, $log->status);
        $this->assertSame('recipient_number not found in request', $log->error);
    }

    public function test_an_otp_template_carries_the_code_in_the_copy_code_button_too(): void
    {
        $this->fakeMsg91();

        app(WhatsAppService::class)->sendOtpTemplate('9876543210', 'login_otp', '482913');

        Http::assertSent(function ($request): bool {
            $components = $request->data()['payload']['template']['to_and_components'][0]['components'];

            return $components['body_1']['value'] === '482913'
                && $components['button_1']['value'] === '482913'
                && $components['button_1']['subtype'] === 'url';
        });
    }

    public function test_a_template_approved_without_a_button_omits_the_button_component(): void
    {
        config(['services.whatsapp.auth_template_has_button' => false]);
        $this->fakeMsg91();

        app(WhatsAppService::class)->sendOtpTemplate('9876543210', 'login_otp', '482913');

        Http::assertSent(function ($request): bool {
            $components = $request->data()['payload']['template']['to_and_components'][0]['components'];

            return $components['body_1']['value'] === '482913'
                && ! array_key_exists('button_1', $components);
        });
    }

    public function test_newlines_are_flattened_out_of_template_variables(): void
    {
        $this->fakeMsg91();

        // A title pasted in from a poster. WhatsApp rejects the whole message for a
        // parameter containing a line break, so it must never reach the wire.
        app(WhatsAppService::class)->sendTemplate('9876543210', 'booking_confirmation', ["Sunburn\nArena  2026"]);

        Http::assertSent(function ($request): bool {
            $components = $request->data()['payload']['template']['to_and_components'][0]['components'];

            return $components['body_1']['value'] === 'Sunburn Arena 2026';
        });
    }

    public function test_a_two_hundred_carrying_an_error_body_is_recorded_as_a_failure(): void
    {
        // MSG91's rejection shape: the HTTP status says fine, the body says no.
        $this->fakeMsg91(['type' => 'error', 'message' => 'Template not approved'], 200);

        $ok = app(WhatsAppService::class)->sendMessage('9876543210', 'Hello');

        $this->assertFalse($ok);

        $log = MessageLog::query()->latest('id')->first();
        $this->assertSame(MessageLog::STATUS_FAILED, $log->status);
        $this->assertSame('msg91', $log->provider);
        $this->assertSame('Template not approved', $log->error);
    }

    public function test_a_successful_send_is_metered_against_the_msg91_provider(): void
    {
        $this->fakeMsg91();

        app(WhatsAppService::class)->sendMessage(
            '9876543210',
            'Hello',
            MessageContext::platform(MessageContext::AUTHENTICATION, 'auth.login_otp'),
        );

        $log = MessageLog::query()->latest('id')->first();

        $this->assertSame(MessageLog::STATUS_SENT, $log->status);
        $this->assertSame('msg91', $log->provider);
        $this->assertSame('req-1', $log->provider_message_id);
        $this->assertSame('auth.login_otp', $log->template_key);
        // Normalised, so one person is one recipient across channels.
        $this->assertSame('+919876543210', $log->recipient);
    }

    public function test_nothing_is_sent_when_the_integrated_number_is_missing(): void
    {
        config(['services.whatsapp.msg91.integrated_number' => null]);
        $this->fakeMsg91();

        $this->assertFalse(app(WhatsAppService::class)->sendMessage('9876543210', 'Hello'));

        Http::assertNothingSent();
        $this->assertSame(MessageLog::STATUS_UNCONFIGURED, MessageLog::query()->latest('id')->first()->status);
    }

    // -------------------------------------------------------------------------
    // Payment receipts
    // -------------------------------------------------------------------------

    private function booking(): Booking
    {
        $partner = User::create([
            'name' => 'P', 'email' => 'p@example.test', 'password' => bcrypt('secret'),
            'role' => 'PARTNER', 'status' => 'active', 'partner_type' => 'event',
        ]);

        $event = Event::create([
            'partner_id' => $partner->id, 'title' => 'Sunburn Arena', 'category' => 'Music',
            'location' => '12 Marine Drive', 'venue' => 'Phoenix Arena', 'city' => 'Mumbai',
            'date' => Carbon::parse('2026-09-12'), 'time' => '7:00 PM',
            'price' => 500, 'total_slots' => 50, 'available_slots' => 50, 'images' => [],
            'status' => 'published',
        ]);

        $buyer = User::create([
            'name' => 'Buyer', 'email' => 'b@example.test', 'password' => bcrypt('secret'),
            'role' => 'user', 'status' => 'active',
        ]);

        return Booking::create([
            'user_id' => $buyer->id, 'event_id' => $event->id, 'quantity' => 2,
            'total_amount' => 1000, 'status' => 'CONFIRMED', 'attendee_phone' => '9876543210',
        ]);
    }

    public function test_a_payment_receipt_names_what_when_how_much_and_which_booking(): void
    {
        $this->approvedTemplate('payment.success', 'payment_success');
        $this->fakeMsg91();

        $booking = $this->booking();
        app(BookingLedger::class)->collect($booking, 400.0, 'cash');

        app(PaymentNotifier::class)->notify(BookingPayment::query()->latest('id')->first());

        Http::assertSent(function ($request) use ($booking): bool {
            $components = $request->data()['payload']['template']['to_and_components'][0]['components'];

            // The approved order: 1 event, 2 when, 3 amount, 4 booking code. No
            // outstanding balance — Haraan takes payment in full at checkout, so
            // that line would read Rs.0 on every receipt.
            return $request->data()['payload']['template']['name'] === 'payment_success'
                && count($components) === 4
                && $components['body_1']['value'] === 'Sunburn Arena'
                && $components['body_2']['value'] === '12 Sep, 7:00 PM'
                // The instalment that just arrived, not the running total.
                && $components['body_3']['value'] === '400'
                && $components['body_4']['value'] === $booking->ticket_code;
        });
    }

    public function test_a_refund_does_not_send_a_payment_received_message(): void
    {
        $this->approvedTemplate('payment.success', 'payment_success');
        $this->fakeMsg91();

        $booking = $this->booking();
        app(BookingLedger::class)->collect($booking, 1000.0, 'online');
        Http::fake(['control.msg91.com/*' => Http::response(['type' => 'success'], 200)]);

        app(BookingLedger::class)->refund($booking->fresh(), 1000.0, 'online');

        // The refund row is negative, and dispatch() refuses those outright.
        PaymentNotifier::dispatch(BookingPayment::query()->latest('id')->first());

        Http::assertNothingSent();
    }

    public function test_no_approved_template_means_no_receipt_rather_than_a_doomed_send(): void
    {
        $this->fakeMsg91();

        $booking = $this->booking();
        app(BookingLedger::class)->collect($booking, 1000.0, 'online');

        app(PaymentNotifier::class)->notify(BookingPayment::query()->latest('id')->first());

        Http::assertNothingSent();
    }
}
