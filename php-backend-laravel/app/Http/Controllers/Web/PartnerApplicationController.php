<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Filament\Resources\Venues\Schemas\VenueForm;
use App\Http\Controllers\Controller;
use App\Models\PartnerApplication;
use App\Services\PartnerApplicationNotifier;
use App\Support\ContactPrefill;
use App\Support\PlatformRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The public "Become a partner" form. Submitting it only files an application —
 * an admin turns it into a partner account and a draft venue from /control
 * (see PartnerApplicationResource).
 *
 * The place questions follow the /control venue form top to bottom and use its
 * vocabulary ({@see VenueForm::SPORTS}, AMENITIES, RULE_PRESETS), so the draft venue
 * is filled straight from the answers.
 */
class PartnerApplicationController extends Controller
{
    public const MAX_PHOTOS = 8;

    public function show(Request $request): View
    {
        return view('site.become-partner', [
            'open' => PlatformRules::bool('partner_onboarding.enabled'),
            'closedMessage' => PlatformRules::string('partner_onboarding.closed_message'),
            'headline' => PlatformRules::string('partner_onboarding.headline'),
            'subline' => PlatformRules::string('partner_onboarding.subline'),
            'callbackHours' => PlatformRules::int('partner_onboarding.callback_hours'),
            'successTitle' => PlatformRules::string('partner_onboarding.success_title'),
            'successBody' => PlatformRules::string('partner_onboarding.success_body'),
            'sports' => self::sports(),
            'amenities' => array_keys(VenueForm::AMENITIES),
            'rulePresets' => VenueForm::RULE_PRESETS,
            'maxPhotos' => self::MAX_PHOTOS,
            // Same bar /control holds venue photos to; here it only warns (see venue_photos.partner_warning).
            'photoCheck' => PlatformRules::bool('venue_photos.partner_warning') ? [
                'w' => PlatformRules::int('venue_photos.min_width'),
                'h' => PlatformRules::int('venue_photos.min_height'),
                'landscape' => PlatformRules::bool('venue_photos.require_landscape'),
            ] : null,
            'askPayouts' => PlatformRules::bool('partner_onboarding.ask_payouts'),
            'banks' => self::banks(),
            // Only promise a WhatsApp confirmation when one can actually be sent.
            'whatsappLive' => PlatformRules::bool('partner_onboarding.whatsapp_confirm')
                && (\App\Models\MessageTemplate::query()->where('key', PartnerApplicationNotifier::TEMPLATE_KEY)->where('channel', 'whatsapp')
                    ->where('is_active', true)->first()?->isApproved() ?? false),
            'prefill' => ContactPrefill::for($request->user()),
            'source' => Str::limit((string) ($request->query('utm_source') ?: $request->query('from', '')), 100, ''),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! PlatformRules::bool('partner_onboarding.enabled')) {
            return response()->json(['message' => PlatformRules::string('partner_onboarding.closed_message')], 403);
        }

        // Honeypot: a real browser never fills the hidden "website" field.
        if (filled($request->input('website'))) {
            return response()->json(['reference' => PartnerApplication::referenceFor(random_int(1000, 99999))]);
        }

        // Answers arrive as one JSON `payload` beside the photo files (multipart).
        $input = json_decode((string) $request->input('payload', '{}'), true);
        $input = is_array($input) ? $input : [];
        $input['photos'] = array_values(array_filter((array) $request->file('photos', []), fn ($f): bool => $f instanceof UploadedFile));

        $place = in_array($input['business_type'] ?? null, PartnerApplication::PLACE_TYPES, true);
        $askPayouts = PlatformRules::bool('partner_onboarding.ask_payouts');
        $method = $askPayouts ? ($input['payout_method'] ?? 'later') : 'later';
        $input['payout_method'] = $method;
        $input['ifsc_code'] = strtoupper(preg_replace('/\s+/', '', (string) ($input['ifsc_code'] ?? '')));
        $input['account_number'] = preg_replace('/\s+/', '', (string) ($input['account_number'] ?? ''));
        $input['account_number_confirmation'] = preg_replace('/\s+/', '', (string) ($input['account_number_confirmation'] ?? ''));
        $input['upi_vpa'] = trim((string) ($input['upi_vpa'] ?? ''));
        // Fields the chosen method doesn't use are dropped, not validated: an owner who
        // typed an IFSC and then picked "share on the call" isn't stopped by it.
        if ($method !== 'bank') {
            foreach (['bank_name', 'account_number', 'account_number_confirmation', 'ifsc_code'] as $unused) {
                $input[$unused] = '';
            }
        }
        if ($method === 'later') {
            $input['account_holder'] = $input['upi_vpa'] = '';
        }
        $time = ['nullable', 'date_format:H:i'];

        $validator = Validator::make($input, [
            'business_type' => ['required', Rule::in(array_keys(PartnerApplication::BUSINESS_TYPES))],

            // Basics
            'business_name' => ['required', 'string', 'min:2', 'max:120'],
            'sports' => [Rule::requiredIf($place && ($input['business_type'] ?? '') !== 'cafe'), 'nullable', 'array', 'max:12'],
            'sports.*' => ['string', 'max:40'],
            'tagline' => ['nullable', 'string', 'max:120'],
            'about' => ['nullable', 'string', 'max:2000'],

            // Location
            'area' => [Rule::requiredIf($place), 'nullable', 'string', 'max:120'],
            'city' => ['required', 'string', 'min:2', 'max:80'],
            'address' => ['nullable', 'string', 'max:255'],
            'map_link' => ['nullable', 'url:https', 'max:600'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],

            // Courts & pricing
            'courts_detail' => [Rule::requiredIf($place), 'nullable', 'array', 'max:50'],
            'courts_detail.*.name' => ['required', 'string', 'max:40'],
            'courts_detail.*.sports' => ['nullable', 'array', 'max:12'],
            'courts_detail.*.sports.*' => ['string', 'max:40'],
            'courts_detail.*.price' => ['nullable', 'integer', 'min:0', 'max:100000'],

            // Hours & cancellation
            'hours_json' => [Rule::requiredIf($place), 'nullable', 'array'],
            'hours_json.*.open' => $time,
            'hours_json.*.close' => $time,
            'slot_minutes' => ['nullable', Rule::in(array_keys(PartnerApplication::SLOT_LENGTHS))],
            'cancel_free_hours' => ['nullable', 'integer', 'min:0', 'max:168'],
            'cancel_refund_percent' => ['nullable', 'integer', 'min:0', 'max:100'],

            // Photos, amenities, good to know
            'photos' => ['array', 'max:' . self::MAX_PHOTOS],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'amenities' => ['nullable', 'array', 'max:25'],
            'amenities.*' => ['string', 'max:40'],
            'rules' => ['nullable', 'array', 'max:20'],
            'rules.*' => ['string', 'max:120'],

            // Payouts — bank account, UPI, or both; or later on the call.
            'payout_method' => ['required', Rule::in(array_keys(PartnerApplication::PAYOUT_METHODS))],
            'bank_name' => [Rule::requiredIf($method === 'bank'), 'nullable', 'string', 'max:80'],
            'account_holder' => [Rule::requiredIf(in_array($method, ['bank', 'upi'], true)), 'nullable', 'string', 'min:2', 'max:100'],
            'account_number' => [Rule::requiredIf($method === 'bank'), 'nullable', 'regex:/^\d{9,18}$/', 'confirmed'],
            'ifsc_code' => [Rule::requiredIf($method === 'bank'), 'nullable', 'regex:' . PartnerApplication::IFSC_PATTERN],
            'upi_vpa' => [Rule::requiredIf($method === 'upi'), 'nullable', 'max:120', 'regex:' . PartnerApplication::UPI_PATTERN],

            // Today
            'booking_channels' => ['nullable', 'array'],
            'booking_channels.*' => [Rule::in(array_keys(PartnerApplication::BOOKING_CHANNELS))],
            'monthly_bookings' => ['nullable', Rule::in(array_keys(PartnerApplication::MONTHLY_BOOKINGS))],
            'goals' => ['nullable', 'array'],
            'goals.*' => [Rule::in(array_keys(PartnerApplication::GOALS))],

            // Contact
            'contact_name' => ['required', 'string', 'min:2', 'max:100'],
            'contact_role' => ['nullable', Rule::in(array_keys(PartnerApplication::CONTACT_ROLES))],
            'phone' => ['required', 'string', 'max:20'],
            'whatsapp_same' => ['nullable', 'boolean'],
            'whatsapp_phone' => [Rule::requiredIf(($input['whatsapp_same'] ?? true) === false), 'nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email:filter', 'max:160'],
            'call_time' => ['nullable', Rule::in(array_keys(PartnerApplication::CALL_TIMES))],
            'gstin' => ['nullable', 'string', 'regex:/^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/i'],
            'note' => ['nullable', 'string', 'max:1000'],
            'source' => ['nullable', 'string', 'max:100'],
            'consent' => ['accepted'],
        ], [
            'business_type.required' => 'Pick what you run.',
            'business_name.required' => 'What is your place called?',
            'sports.required' => 'Pick at least one sport.',
            'area.required' => 'Which area or locality is it in?',
            'city.required' => 'Which city are you in?',
            'map_link.url' => 'Paste the link from Google Maps → Share → Copy link.',
            'courts_detail.required' => 'Add at least one court.',
            'courts_detail.*.name.required' => 'Give every court a name.',
            'hours_json.required' => 'Open at least one day.',
            'photos.max' => 'Up to ' . self::MAX_PHOTOS . ' photos.',
            'photos.*.image' => 'Photos must be JPG, PNG or WebP.',
            'photos.*.mimes' => 'Photos must be JPG, PNG or WebP.',
            'photos.*.max' => 'Each photo must be under 10 MB.',
            'contact_name.required' => 'Who should we ask for?',
            'phone.required' => 'We need a number to call you on.',
            'whatsapp_phone.required' => 'Enter the number you use on WhatsApp.',
            'gstin.regex' => 'That GSTIN doesn’t look right — it is 15 characters, like 37ABCDE1234F1Z5. Leave it empty if you don’t have one.',
            'consent.accepted' => 'Tick the box so we can call you.',
            'bank_name.required' => 'Pick your bank.',
            'account_holder.required' => 'Whose name is the account in?',
            'account_number.required' => 'Enter the account number.',
            'account_number.regex' => 'An account number is 9 to 18 digits.',
            'account_number.confirmed' => 'The two account numbers don’t match.',
            'ifsc_code.required' => 'Enter the IFSC — it is printed on your cheque book.',
            'ifsc_code.regex' => 'An IFSC is 11 characters, like HDFC0001234 (the fifth is a zero).',
            'upi_vpa.required' => 'Enter your UPI ID.',
            'upi_vpa.regex' => 'A UPI ID looks like name@okhdfcbank.',
        ]);

        // Hours that open and close at the same minute produce no slots — say so here,
        // like the /control venue form does.
        $validator->after(function ($v) use ($input): void {
            foreach ((array) ($input['hours_json'] ?? []) as $day => $row) {
                if (! array_key_exists($day, PartnerApplication::WEEKDAYS)) {
                    $v->errors()->add('hours_json', 'Unknown day in the hours.');
                } elseif (is_array($row) && ($row['open'] ?? null) && ($row['open'] ?? null) === ($row['close'] ?? null)) {
                    $v->errors()->add('hours_json', PartnerApplication::WEEKDAYS[$day] . ': closing time can’t be the same as opening time.');
                }
            }
        });

        $data = $validator->validate();

        $phone = self::normalisePhone($data['phone']);

        if ($phone === null) {
            return response()->json([
                'message' => 'That phone number doesn’t look right.',
                'errors' => ['phone' => ['Enter a 10-digit mobile number.']],
            ], 422);
        }

        $whatsapp = null;
        if (($data['whatsapp_same'] ?? true) === false) {
            $whatsapp = self::normalisePhone((string) ($data['whatsapp_phone'] ?? ''));
            if ($whatsapp === null) {
                return response()->json([
                    'message' => 'That WhatsApp number doesn’t look right.',
                    'errors' => ['whatsapp_phone' => ['Enter a 10-digit mobile number.']],
                ], 422);
            }
            // The same number twice is just "this number".
            $whatsapp = $whatsapp === $phone ? null : $whatsapp;
        }

        // Pressing submit twice, or coming back tomorrow to "check it went through",
        // must not queue the same owner twice for a call.
        $existing = PartnerApplication::query()
            ->open()
            ->where('phone', $phone)
            ->where('created_at', '>=', now()->subDays(7))
            ->latest('id')
            ->first();

        if ($existing !== null) {
            return response()->json(['reference' => $existing->reference, 'duplicate' => true]);
        }

        $clean = static fn (?string $s, int $max): ?string => filled($s) ? Str::limit(trim(strip_tags((string) $s)), $max, '') : null;
        $list = static fn (array $items, int $max): array => collect($items)
            ->map(fn ($s): string => Str::limit(trim(strip_tags((string) $s)), $max, ''))
            ->filter()
            ->unique(fn (string $s): string => mb_strtolower($s))
            ->values()
            ->all();

        $sports = $list($data['sports'] ?? [], 40);

        $courts = [];
        $hours = null;
        if ($place) {
            foreach ($data['courts_detail'] ?? [] as $i => $court) {
                $courts[] = [
                    'name' => $clean($court['name'] ?? null, 40) ?? 'Court ' . ($i + 1),
                    // A court only plays what the venue offers.
                    'sports' => array_values(array_intersect($list($court['sports'] ?? [], 40), $sports)) ?: $sports,
                    'price' => isset($court['price']) && $court['price'] !== '' ? (int) $court['price'] : null,
                ];
            }

            foreach (PartnerApplication::WEEKDAYS as $key => $_) {
                $row = $data['hours_json'][$key] ?? null;
                if (is_array($row) && ! empty($row['open']) && ! empty($row['close'])) {
                    $hours[$key] = ['open' => $row['open'], 'close' => $row['close']];
                }
            }
        }

        $prices = array_filter(array_column($courts, 'price'), fn ($p): bool => (int) $p > 0);

        $application = PartnerApplication::create([
            'reference' => 'TMP-' . Str::random(12),
            'status' => PartnerApplication::STATUS_NEW,
            'business_type' => $data['business_type'],
            'business_name' => trim($data['business_name']),
            'sports' => $sports ?: null,
            'tagline' => $clean($data['tagline'] ?? null, 120),
            'about' => $clean($data['about'] ?? null, 2000),
            'area' => $clean($data['area'] ?? null, 120),
            'city' => trim($data['city']),
            'address' => $clean($data['address'] ?? null, 255),
            'map_link' => $data['map_link'] ?? null,
            'latitude' => isset($data['latitude']) ? round((float) $data['latitude'], 7) : null,
            'longitude' => isset($data['longitude']) ? round((float) $data['longitude'], 7) : null,
            'courts_detail' => $courts ?: null,
            'courts' => $courts ? count($courts) : null,
            'base_price' => $prices ? (int) min($prices) : null,
            'hours_json' => $hours,
            'slot_minutes' => $place ? (int) ($data['slot_minutes'] ?? 60) : null,
            'cancel_free_hours' => $place ? ($data['cancel_free_hours'] ?? null) : null,
            'cancel_refund_percent' => $place && isset($data['cancel_free_hours']) ? ($data['cancel_refund_percent'] ?? null) : null,
            'amenities' => $place ? ($list($data['amenities'] ?? [], 40) ?: null) : null,
            'rules' => $place ? ($list($data['rules'] ?? [], 120) ?: null) : null,
            'booking_channels' => array_values(array_unique($data['booking_channels'] ?? [])) ?: null,
            'monthly_bookings' => $data['monthly_bookings'] ?? null,
            'goals' => array_values(array_unique($data['goals'] ?? [])) ?: null,
            'payout_method' => $method,
            'bank_name' => $method === 'bank' ? $clean($data['bank_name'] ?? null, 80) : null,
            'account_holder' => $method === 'later' ? null : $clean($data['account_holder'] ?? null, 100),
            'account_number' => $method === 'bank' ? ($data['account_number'] ?: null) : null,
            'ifsc_code' => $method === 'bank' ? ($data['ifsc_code'] ?: null) : null,
            'upi_vpa' => $method === 'later' ? null : (filled($data['upi_vpa'] ?? null) ? mb_strtolower($data['upi_vpa']) : null),
            'contact_name' => trim($data['contact_name']),
            'contact_role' => $data['contact_role'] ?? null,
            'phone' => $phone,
            'whatsapp_phone' => $whatsapp,
            'email' => filled($data['email'] ?? null) ? mb_strtolower(trim($data['email'])) : null,
            'call_time' => $data['call_time'] ?? null,
            'gstin' => filled($data['gstin'] ?? null) ? strtoupper($data['gstin']) : null,
            'note' => $clean($data['note'] ?? null, 1000),
            'user_id' => $request->user()?->id,
            'source' => $data['source'] ?? null,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ]);

        // Photos go in after the row exists so the folder carries its id.
        $paths = [];
        foreach ($data['photos'] ?? [] as $photo) {
            $paths[] = $photo->store('partner-applications/' . $application->id, 'public');
        }
        if ($paths !== []) {
            $application->forceFill(['images' => array_values(array_filter($paths))])->saveQuietly();
        }

        $application = $application->fresh();

        // After the response: the owner sees their stamp without waiting on WhatsApp.
        dispatch(fn () => app(PartnerApplicationNotifier::class)->received($application))->afterResponse();

        return response()->json(['reference' => $application->reference], 201);
    }

    /** @return array<int, string> */
    public static function banks(): array
    {
        return array_values(array_filter(array_map(
            'trim',
            explode(',', PlatformRules::string('partner_onboarding.banks')),
        )));
    }

    /** @return array<int, string> */
    public static function sports(): array
    {
        return array_values(array_filter(array_map(
            'trim',
            explode(',', PlatformRules::string('partner_onboarding.sports')),
        )));
    }

    /** Indian mobiles to +91XXXXXXXXXX; anything already in +country form is kept as typed. */
    public static function normalisePhone(string $raw): ?string
    {
        $raw = trim($raw);

        if (str_starts_with($raw, '+')) {
            $digits = preg_replace('/\D/', '', $raw);

            return strlen($digits) >= 8 && strlen($digits) <= 15 ? '+' . $digits : null;
        }

        $digits = preg_replace('/\D/', '', $raw);

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return preg_match('/^[6-9]\d{9}$/', $digits) ? '+91' . $digits : null;
    }
}
