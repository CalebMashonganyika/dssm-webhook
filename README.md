# DSSM WhatsApp EcoCash Subscription System

Complete WhatsApp Business API backend for EcoCash subscription processing, deployed on Render with Docker.

## 🚀 System Overview

This system allows users to:
1. Pay $3 via EcoCash to `0772872564`
2. Forward the SMS confirmation to WhatsApp
3. Receive an activation code automatically
4. Enter the code in the Flutter app to activate a 30-day subscription

## 📁 Project Structure

```
dssm-webhook/
├── Dockerfile              # PHP 8.1 + Apache container
├── index.php               # System status dashboard
├── src/
│   ├── whatsapp_webhook.php # Complete webhook handler
│   ├── schema.sql          # Database schema
│   └── logs/               # Auto-created log directory
└── README.md
```

## 🔧 Features

- ✅ **Complete EcoCash Processing** - Parses SMS, validates payments, generates codes
- ✅ **WhatsApp Business API** - Automated replies and confirmations
- ✅ **MySQL Database** - User management, payments, subscriptions, activation codes
- ✅ **Meta Webhook Verification** - Handles GET verification requests
- ✅ **Environment Variables** - Secure configuration
- ✅ **Docker Ready** - Optimized for Render deployment
- ✅ **Comprehensive Logging** - All activities logged for debugging
- ✅ **Input Validation** - Secure data processing
- ✅ **Transaction Uniqueness** - Prevents duplicate processing

## 🚀 Quick Deploy

1. **Create GitHub repo** named `dssm-webhook`
2. **Push this code** to the repo
3. **Create Render Web Service:**
   - Environment: `Docker`
   - Root Directory: `.` (empty)
   - Environment Variables:
     - `WHATSAPP_VERIFY_TOKEN`: `dssm_verify_2025`
     - `WHATSAPP_ACCESS_TOKEN`: `[Your WhatsApp Access Token]`
     - `WHATSAPP_PHONE_NUMBER_ID`: `879081068623691`
     - `DB_HOST`: `sql100.infinityfree.com`
     - `DB_NAME`: `if0_40401590_subscriptions_db`
     - `DB_USER`: `if0_40401590`
     - `DB_PASS`: `[Your Database Password]`
4. **Set webhook URL** in Meta Developers Console:
   - `https://your-render-app.onrender.com/src/whatsapp_webhook.php`

## 📊 Database Schema

Run `src/schema.sql` to create:
- `users` - User accounts with phone numbers
- `payments` - EcoCash payment records
- `activation_codes` - One-time codes with expiry
- `subscriptions` - Active subscriptions with dates

## 🔄 Processing Flow

1. **User Payment**: EcoCash payment to `0772872564`
2. **SMS Forward**: User forwards confirmation SMS to WhatsApp
3. **Webhook Receive**: System receives WhatsApp message
4. **Message Parse**: Extract amount, phone, transaction reference
5. **Validation**: Check amount ($3.00) and uniqueness
6. **User Management**: Find/create user by phone number
7. **Payment Save**: Store verified payment in database
8. **Code Generation**: Create unique activation code (expires in 20 min)
9. **Subscription Create**: Set up 30-day subscription
10. **WhatsApp Reply**: Send activation code to user
11. **App Activation**: User enters code in Flutter app

## 📝 Environment Variables

| Variable | Description | Example |
|----------|-------------|---------|
| `WHATSAPP_VERIFY_TOKEN` | Meta webhook verification | `dssm_verify_2025` |
| `WHATSAPP_ACCESS_TOKEN` | WhatsApp API access token | `[Long token string]` |
| `WHATSAPP_PHONE_NUMBER_ID` | WhatsApp phone number ID | `879081068623691` |
| `DB_HOST` | MySQL database host | `sql100.infinityfree.com` |
| `DB_NAME` | Database name | `if0_40401590_subscriptions_db` |
| `DB_USER` | Database username | `if0_40401590` |
| `DB_PASS` | Database password | `[Your password]` |

## 🐳 Docker Build

```bash
# Build locally (optional)
docker build -t dssm-webhook .
docker run -p 8080:80 dssm-webhook
```

## 🔍 Debugging & Monitoring

Check logs at:
- **System Status**: `https://your-app.onrender.com/`
- **Incoming Messages**: `https://your-app.onrender.com/src/logs/whatsapp_incoming.log`
- **Verification Logs**: `https://your-app.onrender.com/src/logs/debug_get.txt`

## 📋 Meta WhatsApp Setup

1. Go to Meta Developers Console
2. Create WhatsApp Business app
3. Configure webhook:
   - Callback URL: `https://your-render-app.onrender.com/src/whatsapp_webhook.php`
   - Verify Token: `dssm_verify_2025`
4. Subscribe to `messages` events
5. Test with sample message

## 🧪 Testing

```bash
# Test webhook verification
curl "https://your-app.onrender.com/src/whatsapp_webhook.php?hub_mode=subscribe&hub_verify_token=dssm_verify_2025&hub_challenge=test123"

# Test system status
curl https://your-app.onrender.com/
```

## ⚠️ Security & Best Practices

- ✅ Environment variable configuration
- ✅ Input validation and sanitization
- ✅ SQL injection prevention (PDO prepared statements)
- ✅ Transaction reference uniqueness checks
- ✅ Activation code expiry (20 minutes)
- ✅ Device-specific validation
- ✅ Comprehensive error logging
- ✅ HTTPS-only communication

## 🎯 Integration with Flutter App

The Flutter app should:
1. Generate device ID on first run
2. Call `/src/generate_code.php` to get test codes
3. Allow users to enter activation codes
4. Call `/src/redeem_code.php` to activate subscriptions
5. Call `/src/check_subscription.php` to validate active subscriptions

## 📈 Scaling Considerations

- Database connection pooling
- Message queuing for high volume
- Rate limiting for API calls
- Monitoring and alerting
- Backup strategies

---

**🚀 Production Ready - Deployed on Render.com**