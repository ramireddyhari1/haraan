# Haraan WhatsApp Bridge

Self-hosted WhatsApp gateway for real OTP delivery in dev, using [whatsapp-web.js](https://wwebjs.dev/).
Laravel's `WhatsAppService` posts OTP messages to this service.

## Run

```bash
cd whatsapp-bridge
npm install        # first time only (downloads a headless Chromium)
npm start
```

On first start it prints a QR (and writes `qr.png`). Open **WhatsApp ▸ Settings ▸ Linked devices ▸ Link a device** and scan it. The session is saved in `.wwebjs_auth/`, so you only scan once.

When you see `WhatsApp client READY`, OTPs will send for real.

## Backend wiring

In `php-backend-laravel/.env`:

```
WHATSAPP_BRIDGE_ENABLED=true
WHATSAPP_BRIDGE_URL=http://localhost:8080/api/send-message
```

## Endpoints

- `GET /status` → `{ ready: true|false }`
- `POST /api/send-message` `{ "number": "9876543210", "message": "..." }` — 10-digit numbers get the default country code (91) prepended.

## Notes

- Uses the WhatsApp account you scan with as the *sender*. Unofficial API — against WhatsApp ToS for production; fine for dev/testing.
- Config via env: `PORT` (default 8080), `DEFAULT_COUNTRY_CODE` (default 91).
- `.wwebjs_auth/`, `.wwebjs_cache/`, `qr.png`, and `node_modules/` should not be committed.
