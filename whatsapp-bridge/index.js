// Haraan self-hosted WhatsApp bridge.
// Exposes POST /api/send-message { number, message } which Laravel's WhatsAppService calls.
// First run prints a QR (terminal) and writes qr.png — scan it from WhatsApp ▸ Linked devices.

const express = require('express');
const qrcodeTerminal = require('qrcode-terminal');
const QRCode = require('qrcode');
const { Client, LocalAuth } = require('whatsapp-web.js');

const PORT = process.env.PORT || 8090;
const DEFAULT_CC = (process.env.DEFAULT_COUNTRY_CODE || '91').replace(/[^0-9]/g, '');

let ready = false;

const client = new Client({
  authStrategy: new LocalAuth({ dataPath: './.wwebjs_auth' }),
  puppeteer: {
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'],
  },
});

client.on('qr', (qr) => {
  console.log('\n[Haraan WA Bridge] Scan this QR in WhatsApp ▸ Settings ▸ Linked devices:\n');
  qrcodeTerminal.generate(qr, { small: true });
  QRCode.toFile('./qr.png', qr, { width: 420, margin: 2 }, (err) => {
    if (err) console.error('[Haraan WA Bridge] Could not write qr.png:', err.message);
    else console.log('[Haraan WA Bridge] QR also saved to qr.png — open & scan it.');
  });
});

client.on('authenticated', () => console.log('[Haraan WA Bridge] Authenticated.'));
client.on('ready', () => {
  ready = true;
  console.log('[Haraan WA Bridge] WhatsApp client READY — OTPs will now send.');
});
client.on('auth_failure', (m) => console.error('[Haraan WA Bridge] Auth failure:', m));
client.on('disconnected', (r) => {
  ready = false;
  console.log('[Haraan WA Bridge] Disconnected:', r);
});

client.initialize();

/** Turn an app phone number into a WhatsApp wid; adds the default country code to bare 10-digit numbers. */
function toWid(numberRaw) {
  let n = String(numberRaw).replace(/[^0-9]/g, '');
  if (n.length === 10) n = DEFAULT_CC + n;
  return `${n}@c.us`;
}

const app = express();
app.use(express.json());

app.get('/status', (_req, res) => res.json({ ready }));

app.post('/api/send-message', async (req, res) => {
  const { number, message } = req.body || {};
  if (!number || !message) {
    return res.status(422).json({ error: 'number and message are required' });
  }
  if (!ready) {
    return res.status(503).json({ error: 'WhatsApp client not ready — scan the QR first.' });
  }
  try {
    let n = String(number).replace(/[^0-9]/g, '');
    if (n.length === 10) n = DEFAULT_CC + n;
    // Resolve the proper WhatsApp id (handles LID addressing + verifies registration).
    const numId = await client.getNumberId(n);
    if (!numId) {
      console.warn(`[Haraan WA Bridge] ${n} is not on WhatsApp`);
      return res.status(404).json({ error: 'This number is not on WhatsApp.' });
    }
    await client.sendMessage(numId._serialized, message);
    console.log(`[Haraan WA Bridge] Sent to ${n}`);
    return res.json({ success: true });
  } catch (e) {
    console.error('[Haraan WA Bridge] Send failed:', e.message);
    return res.status(500).json({ error: e.message });
  }
});

app.listen(PORT, () => {
  console.log(`[Haraan WA Bridge] HTTP listening on http://localhost:${PORT}`);
  console.log('[Haraan WA Bridge] Waiting for WhatsApp login (QR)…');
});
