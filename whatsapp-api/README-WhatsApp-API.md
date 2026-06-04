# Custom WhatsApp Local API Setup

This setup uses **whatsapp-web.js** to reliably bypass the timeout errors found in the official OpenWA package. It creates a local Express API server and a visual testing dashboard.

## 1. Start the Server
Open a terminal in the `whatsapp-api` folder and run:
```bash
node index.js
```

## 2. Connect Your WhatsApp
Once the server starts running:
1. Wait a few seconds for the terminal to display a QR code.
2. Open **WhatsApp** on your phone.
3. Tap **Linked Devices** > **Link a Device**.
4. Scan the QR code shown on your computer screen.

## 3. Test the Local API
After scanning the QR code, the terminal will say "WhatsApp Client is READY!".
You can now open this URL in your web browser to test the API actions:
[http://localhost:8080/](http://localhost:8080/)

From this dashboard, you can type a phone number and message to verify the connection is working.

## 4. How to Stop the API
To stop the server, simply go to your terminal window and press:
**`Ctrl + C`**

## 5. How to Restart It
If you want to restart it later, just run the same start command again:
```bash
node index.js
```

---
*Note: This setup is designed to be kept local. Do not expose this port to the public internet.*
