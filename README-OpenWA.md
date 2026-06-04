# OpenWA Easy API Setup Guide

This is a local setup for connecting your WhatsApp account to the OpenWA API using the official Easy API route. **Please keep this setup local only. Do not expose it to the public internet.**

## 1. Start OpenWA
To start your local WhatsApp API server, run the following exact command in this folder:
```bash
npx @open-wa/wa-automate@4.76.0 --port 8080
```

## 2. Connect Your WhatsApp (Scan the QR Code)
Once the command starts running:
1. Wait for the terminal to display a QR code (or a pairing code method).
2. Open **WhatsApp** on your phone.
3. Tap **Linked Devices** > **Link a Device**.
4. Scan the QR code shown on your computer screen.

## 3. Test the Local API
After scanning the QR code and successfully connecting, open this URL in your web browser to test the API actions:
[http://localhost:8080/api-docs/](http://localhost:8080/api-docs/)

*Use your own number for the first test message before connecting other tools!*

## 4. How to Stop OpenWA
To stop the OpenWA server, simply go to your terminal window and press:
**`Ctrl + C`**

## 5. How to Restart It
If you want to restart it later, just run the same start command again:
```bash
npx @open-wa/wa-automate@4.76.0 --port 8080
```

---
*Note: No webhook URL has been added to this configuration.*
