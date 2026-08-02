# WhatsApp via MSG91

Haraan sends WhatsApp over one of two transports, chosen by `WHATSAPP_DRIVER`:

| Driver | What it is |
| ------ | ---------- |
| `meta` | The WhatsApp Cloud API, direct to Meta. |
| `msg91` | MSG91 as the Business Solution Provider in front of the same WhatsApp Business Account. |

Both reach the **same WABA** and the **same approved templates**, so switching is
an env change that re-approves nothing. Only the wire format differs, which is why
`WhatsAppService` builds a provider-neutral intent and each driver renders it.

There is no SMS transport any more. It existed as a second aggregator integration
with its own DLT template registry, and was never switched on — India requires
every transactional SMS to match a template approved before the send, which is a
whole parallel approval process for a channel nobody was using. A booking ticket
now goes out over **email, push and WhatsApp**, and email is the rung that needs no
approval and no app.

The practical consequence worth knowing: a customer with no WhatsApp now receives
the ticket by email and push only. If they have neither, they have their booking
code and nothing else.

---

## 1. Configure the account

```bash
WHATSAPP_ENABLED=true
WHATSAPP_DRIVER=msg91
MSG91_AUTH_KEY=<the same authkey the SMS driver uses>
MSG91_WHATSAPP_NUMBER=<the number on the MSG91 panel, country code, no plus>
```

`MSG91_WHATSAPP_NAMESPACE` is optional — only older WABAs need it stated.

Nothing is sent while `WHATSAPP_ENABLED=false`; attempts are still written to the
messaging ledger as `disabled`, so a misconfiguration is visible rather than silent.

## 2. Register the templates

Submit these in the MSG91 panel (WhatsApp → Templates). The **name** column is what
Haraan puts on the wire — it must match exactly, and it is pre-filled in
`/control → Platform → Templates` by the seeder.

Variables are positional. **The order below is the order the code fills them**, so
if you register a different order, fix the copy — not the code.

### `booking_confirmation` — utility

```
Your booking is confirmed.

*{{1}}*
{{2}}
{{3}}

Booking ID: {{4}}
Show the QR code at entry: {{5}}

Thank you for booking with Haraan.
```

1. event or venue name · 2. date and time · 3. venue and city · 4. booking code · 5. link to the QR

`*{{1}}*` renders bold — it's the line people scan for. No emoji, deliberately.

Venue bookings have no public pass page, so slot 5 falls back to the QR image URL
rather than a link to a 404. The slot means "where to see your QR", and both forms
answer it.

**Do not add a button to this template.** A dynamic URL button requires a button
parameter on every send, and `sendTemplate()` only supplies one for the OTP
template — every booking confirmation would be rejected for a component mismatch.
The ticket link is already in the body.

### `payment_success` — utility

```
We've received your payment for *{{1}}*.
{{2}}

Amount: Rs.{{3}}
Booking ID: {{4}}

Thank you — Haraan.
```

1. event or venue name · 2. date and time · 3. amount paid · 4. ticket/booking code

Quotes the **instalment**, not the running total — a deposit now and the balance at
the desk are two payments and two receipts.

**No outstanding balance line, deliberately.** Haraan takes payment in full at
checkout, so it would read `Rs.0` on essentially every receipt — a number answering
a question nobody asked, on the one message that should be readable at a glance.

It also doesn't repeat the QR. The booking confirmation already carries that, and
two QRs in one thread is how someone shows the wrong one at the gate.

### `event_reminder` — utility

```
Reminder: {{1}} is coming up.
{{2}}

Your ticket & QR: {{3}}

— Haraan
Reply STOP to opt out.
```

1. event or venue name · 2. date and time · 3. ticket pass URL

One template serves both reminder steps (24h and 2h before). The customer reads the
timing from when it arrives; a second template is a second approval queue for nothing.

### `login_otp` — **authentication** category

Create it from WhatsApp's authentication skeleton, with the **copy-code button**.
The category matters: an OTP submitted as `utility` is rejected, and an
authentication template may not carry marketing copy.

```
{{1}} is your Haraan verification code. It expires in 5 minutes.
```

1. the 6-digit code

The code is sent **twice** — once for the body, once for the button, which is what
"Copy code" actually copies. If your template was approved with *no* button, set
`WHATSAPP_AUTH_TEMPLATE_HAS_BUTTON=false` or the extra component is itself a
rejection.

### `review_request` — marketing

```
Hope you enjoyed *{{1}}*.

How was it? Leave a quick rating here: {{2}}

Your feedback helps the organiser and everyone booking next.

— Haraan
Reply STOP to opt out.
```

1. event or venue name · 2. review page URL

`{{2}}` is `https://haraan.app/r/{ticket_code}` — public and sessionless, like the
ticket pass, because the person who attended often isn't the person who paid. One
review per booking, nothing accepted before the event has happened, and it lands
on the partner's Reviews page for both lanes.

Marketing, not utility: it asks for something rather than serving the transaction,
and WhatsApp prices and polices the two differently.

## 3. Mark them approved

```bash
php artisan db:seed --class=MessageTemplateSeeder
```

Idempotent, and it never overwrites a row an admin has edited — it only fills in a
`provider_template_id` that was never set.

Then in `/control → Platform → Templates`, set each row's status to **approved**
once WhatsApp has actually approved it. Until then `TemplateResolver` reports
`blocked — template not approved`, which is the honest state: it stops a doomed
send from being logged as a delivery failure that reads like an outage.

## 4. Verify

```bash
php artisan whatsapp:probe 9876543210 --key=booking.ticket --var="Test Event" --var="Sat, 9 Aug" --var="https://haraan.app/t/ABC"
```

It prints the driver, whether it's enabled and configured, how the template
resolved, and then the provider's own verdict read back out of the ledger. Nothing
is swallowed here — unlike a booking flow, which deliberately hides send failures
so a dead aggregator can never fail a purchase.

```bash
php artisan whatsapp:probe 9876543210 --otp=123456
```

sends the authentication template the same way login does.

---

## What sends what

| Trigger | Key | When |
| ------- | --- | ---- |
| Booking confirmed | `booking.ticket` | `BookingNotifier`, deferred after the response |
| Money collected | `payment.success` | `BookingLedger::collect()` → `PaymentNotifier` |
| 24h / 2h before | `event.reminder_24h`, `event.reminder_2h` | `MessageJourneys`, on the cron tick |
| After the event | `review.request` | `MessageJourneys` |
| Phone login | `auth.login_otp` | `POST /api/auth/whatsapp/request` |

Journeys have their **own** master switch (`MESSAGING_JOURNEYS_ENABLED`), separate
from `WHATSAPP_ENABLED`: the queue fills and drains its bookkeeping either way, but
reminders don't reach customers until that is deliberately turned on.

## Inbound: the webhook

Sending needs nothing configured in the MSG91 panel. **Receiving** does, and it is
not optional if the templates say "Reply STOP to opt out" — which ours do.

In the panel: **Number → Action ⋮ → Webhook**

| Field | Value |
| ----- | ----- |
| URL | `https://haraan.app/api/webhooks/msg91/whatsapp` |
| Header | `X-Haraan-Token: <the value of MSG91_WEBHOOK_TOKEN>` |

```bash
MSG91_WEBHOOK_TOKEN=<a long random string>
MSG91_WEBHOOK_HEADER=X-Haraan-Token
```

**MSG91 signs nothing.** Meta signs every webhook with the app secret; MSG91's
panel offers arbitrary request headers and no signature scheme at all. So that
shared secret is the *entire* authentication for an endpoint that can create
opt-outs and open billable conversations — generate it with
`php -r 'echo bin2hex(random_bytes(32));'`, don't invent one by hand, and treat it
like a password. With `MSG91_WEBHOOK_TOKEN` blank the endpoint refuses every
delivery, which is the safe default: an endpoint that accepts everything until
someone remembers to configure it is worse than one that accepts nothing, because
nobody notices the first.

Without this wired up, three things silently don't work: **STOP opt-outs are never
honoured** (a promise the templates make in writing), the 24-hour service window
never opens so free-text fallbacks stay permanently blocked, and WhatsApp replies
never reach support chat.

`Msg91WebhookController` translates MSG91's envelope and hands off to the same
`InboundMessages::handle()` the Meta path uses, so behaviour is identical on both
drivers.

**Delivery reports arrive on this same URL** and are deliberately dropped. Treating
one as an inbound message would open a 24-hour conversation off the back of our own
outgoing ticket, bill it, and make free text legal to a customer who never wrote to
us. Feeding reports into per-message delivery status is a separate piece of work —
`message_log` has no `delivered`/`read` states and its counters are already
committed at send time, so re-deriving them from reports would double-count.

## The window rule, and why it shapes all of this

WhatsApp only permits free text inside a 24-hour service window, and **only the
customer opens that window** by messaging us. Buying a ticket does not. So for
almost every message above, an approved template is the only send that will be
delivered — which is why `TemplateResolver` is consulted first everywhere, and why
a missing approval is reported as `blocked` rather than attempted and lost.

The ticket is the one exception: with nothing registered it still falls back to the
QR image and free text, so a self-hosted bridge or a freshly-approved number keeps
working without a code change, and the rejection lands in the ledger where it can
be seen.

## Known unverified

The MSG91 request shapes here are covered by `tests/Feature/WhatsAppMsg91Test.php`
against a faked HTTP client, and match their published v5 endpoints — but MSG91's
public docs do not publish the full JSON body, so they have **not** been confirmed
against a live account. Run `whatsapp:probe` once with real credentials before
trusting them; the provider's error text is printed verbatim, which is enough to
correct `sendViaMsg91()` if any field name differs.

The same applies inbound. MSG91 documents the webhook *field names*
(`customerNumber`, `contentType`, `text`, `content`, `direction`, `eventName`,
`uuid`…) but not a complete example payload, so `Msg91WebhookController` reads
several plausible spellings rather than pinning to one — a batch may arrive bare,
or wrapped under `data`/`messages`/`events`, and the body may be in `text` or as
stringified JSON in `content`. After the first real delivery, check the log and
tighten it to what MSG91 actually sends.
