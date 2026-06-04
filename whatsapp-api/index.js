const express = require('express');
const { Client, LocalAuth } = require('whatsapp-web.js');
const qrcode = require('qrcode-terminal');

const app = express();
const port = 8080;

app.use(express.json());
app.use(express.urlencoded({ extended: true }));

console.log('Initializing WhatsApp Client...');

const client = new Client({
    authStrategy: new LocalAuth(),
    puppeteer: {
        // Essential flags to run smoothly on Windows/Linux
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-extensions']
    }
});

let isClientReady = false;

client.on('qr', (qr) => {
    console.log('\n--- PLEASE SCAN THIS QR CODE WITH YOUR WHATSAPP ---\n');
    qrcode.generate(qr, { small: true });
});

client.on('ready', () => {
    console.log('\n✅ WhatsApp Client is READY!');
    isClientReady = true;
});

client.on('auth_failure', msg => {
    console.error('AUTHENTICATION FAILURE', msg);
});

client.on('disconnected', (reason) => {
    console.log('Client was logged out', reason);
    isClientReady = false;
});

client.initialize();

// Simple HTML Dashboard
app.get('/', (req, res) => {
    res.send(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>WhatsApp API Dashboard</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; background: #f0f2f5; }
                .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                h1 { color: #075e54; }
                .status { padding: 10px; border-radius: 4px; margin-bottom: 20px; font-weight: bold; }
                .ready { background: #d4edda; color: #155724; }
                .not-ready { background: #fff3cd; color: #856404; }
                input, textarea { width: 100%; padding: 10px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
                button { background: #25D366; color: white; border: none; padding: 10px 20px; font-size: 16px; border-radius: 4px; cursor: pointer; }
                button:hover { background: #128C7E; }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>WhatsApp API</h1>
                <div class="status ${isClientReady ? 'ready' : 'not-ready'}">
                    Status: ${isClientReady ? '✅ Connected and Ready' : '⏳ Waiting for QR Code scan...'}
                </div>
                
                <h3>Test Sending a Message</h3>
                <form action="/api/send-message" method="POST">
                    <label>Phone Number (with country code, e.g. 919876543210):</label>
                    <input type="text" name="number" required placeholder="919876543210">
                    
                    <label>Message:</label>
                    <textarea name="message" rows="4" required placeholder="Hello from the local API!"></textarea>
                    
                    <button type="submit">Send Message</button>
                </form>
            </div>
        </body>
        </html>
    `);
});

// Send Message Endpoint
app.post('/api/send-message', async (req, res) => {
    if (!isClientReady) {
        return res.status(503).json({ success: false, error: 'WhatsApp client is not ready yet. Please scan the QR code in the terminal.' });
    }

    const { number, message } = req.body;

    if (!number || !message) {
        return res.status(400).json({ success: false, error: 'Phone number and message are required.' });
    }

    try {
        // Format number correctly
        const sanitizedNumber = number.replace(/\D/g, '');
        
        // Use getNumberId to let WhatsApp resolve the proper internal ID
        const numberId = await client.getNumberId(sanitizedNumber);
        if (!numberId) {
            return res.status(404).send(`<h3>❌ Number not registered on WhatsApp:</h3><p>The number ${sanitizedNumber} does not exist on WhatsApp.</p><br><a href="/">Go back</a>`);
        }

        const response = await client.sendMessage(numberId._serialized, message);
        
        // Respond with success (or redirect back if it was a form submission)
        if (req.headers.accept && req.headers.accept.includes('text/html')) {
            res.send(`<h3>✅ Message sent successfully!</h3><a href="/">Go back</a>`);
        } else {
            res.json({ success: true, response });
        }
    } catch (error) {
        console.error('Error sending message:', error);
        if (req.headers.accept && req.headers.accept.includes('text/html')) {
            res.status(500).send(`<h3>❌ Failed to send message:</h3><pre>${error.message || error}</pre><br><a href="/">Go back</a>`);
        } else {
            res.status(500).json({ success: false, error: 'Failed to send message.', details: error.message || error.toString() });
        }
    }
});

app.listen(port, () => {
    console.log(`\n========================================`);
    console.log(`🚀 API Server running at: http://localhost:${port}`);
    console.log(`========================================\n`);
});
