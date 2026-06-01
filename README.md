# 🪙 PipraPay — Cryptomus Payment Gateway Plugin

> Accept **cryptocurrency payments** on your [PipraPay](https://piprapay.com) platform via [Cryptomus](https://cryptomus.com) — seamlessly and securely.

![Version](https://img.shields.io/badge/version-1.0.2-F6A609?style=flat-square)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?style=flat-square&logo=php&logoColor=white)
![Platform](https://img.shields.io/badge/platform-PipraPay-5F38F9?style=flat-square)
![License](https://img.shields.io/badge/license-GPL--3.0-blue?style=flat-square)

---

## ✨ Features

- ✅ One-click crypto checkout via Cryptomus hosted payment page
- ✅ Supports **all Cryptomus currencies** — USDT, BTC, ETH, TRX, and more
- ✅ Automatic **webhook (IPN)** handling with signature verification
- ✅ Redirect-based payment status polling (fallback)
- ✅ Accepts both `paid` and `paid_over` statuses
- ✅ Clean integration with PipraPay's native gateway API

---

## 📁 File Structure

```
cryptomus-gateway/
├── class.php          ← Main gateway class (loaded by PipraPay)
└── assets/
    └── logo.png       ← Gateway logo shown in checkout
```

---

## ⚙️ Installation

### Step 1 — Download the plugin

Download the latest release from the [Releases](https://github.com/Saifistiak/PipraPay-Cryptomus-plugin/releases) page.

### Step 2 — Upload to PipraPay

Place the `cryptomus-gateway/` folder inside your PipraPay gateways directory:

```
/path-to-piprapay/gateways/cryptomus-gateway/
```

### Step 3 — Get Cryptomus credentials

1. Register at [cryptomus.com](https://cryptomus.com)
2. Go to **Dashboard → Settings → API**
3. Copy your **Merchant UUID** and **Payment API Key**

### Step 4 — Configure in PipraPay Admin

Navigate to **Admin → Payment Gateways → Cryptomus** and enter:

| Field | Value |
|---|---|
| Merchant UUID | Your Cryptomus Merchant UUID |
| Payment API Key | Your Cryptomus Payment API Key (not Payout key) |

---

## 🔄 How It Works

```
Customer clicks Pay
       ↓
process_payment() creates Cryptomus invoice
       ↓
Customer redirected to Cryptomus payment page
       ↓
    [pays crypto]
       ↓
Cryptomus sends webhook POST → callback()
       ↓
Signature verified → transaction marked completed
```

> **Fallback:** If the webhook doesn't fire, the status is polled automatically when the customer is redirected back to your site.

---

## 🧪 Testing

Cryptomus does not have a sandbox environment. Use the **test webhook** endpoint instead:

```bash
curl https://api.cryptomus.com/v1/test-webhook/payment \
  -X POST \
  -H 'merchant: YOUR_MERCHANT_UUID' \
  -H 'sign: YOUR_SIGN' \
  -H 'Content-Type: application/json' \
  -d '{
    "uuid": "test-uuid-001",
    "order_id": "YOUR_TRANSACTION_REF",
    "currency": "USDT",
    "network": "tron",
    "url_callback": "https://yoursite.com/callback-url",
    "status": "paid"
  }'
```

**Generate the sign:**
```php
$sign = md5(base64_encode(json_encode($data)) . $payment_api_key);
```

**Test statuses you can use:**

| Status | Description |
|---|---|
| `paid` | Successful payment ✅ |
| `paid_over` | Overpaid ✅ |
| `wrong_amount` | Underpaid ❌ |
| `cancel` | Cancelled ❌ |
| `fail` | Failed ❌ |

> **Local testing:** Use [ngrok](https://ngrok.com) to expose your localhost so Cryptomus can reach your callback URL.

---

## 🔐 Webhook Security

Every incoming webhook is verified using Cryptomus's signature algorithm:

```
sign = MD5( base64_encode( json_body ) + payment_api_key )
```

Cryptomus sends webhooks only from IP **`91.227.144.54`** — whitelist this on your server firewall for extra security.

---

## 📋 Requirements

- PHP **7.4** or higher
- `curl` extension enabled
- PipraPay platform
- Active [Cryptomus](https://cryptomus.com) merchant account

---

## 📝 License

Licensed under the [GNU General Public License v3.0](LICENSE).

---

## 🙋 Support

- Cryptomus API Docs: [doc.cryptomus.com](https://doc.cryptomus.com)
- PipraPay Help Center: [help.piprapay.com](https://help.piprapay.com)
- Open an [Issue](https://github.com/Saifistiak/PipraPay-Cryptomus-plugin/issues) for bug reports or feature requests
