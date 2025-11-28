# DSSM WhatsApp Webhook

Minimal WhatsApp Business API webhook for EcoCash subscription processing, deployed on Render with Docker.

## 🚀 Quick Deploy

1. **Create GitHub repo** named `dssm-webhook`
2. **Push this code** to the repo
3. **Create Render Web Service:**
   - Environment: `Docker`
   - Root Directory: `.` (empty)
   - Environment Variables:
     - `WHATSAPP_VERIFY_TOKEN`: `dssm_verify_2025`
4. **Set webhook URL** in Meta Developers Console:
   - `https://your-render-app.onrender.com/src/whatsapp_webhook.php`

## 📁 Structure

```
dssm-webhook/
├── Dockerfile          # PHP 8.1 + Apache container
├── src/
│   └── whatsapp_webhook.php  # Webhook handler
├── logs/               # Auto-created log directory
└── README.md
```

## 🔧 Features

- ✅ **Meta Webhook Verification** - Handles GET verification requests
- ✅ **Message Logging** - Logs all incoming POST data
- ✅ **Environment Variables** - Secure token configuration
- ✅ **Docker Ready** - Optimized for Render deployment
- ✅ **Host Agnostic** - Works with parameter rewriting

## 📝 Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `WHATSAPP_VERIFY_TOKEN` | Token for Meta verification | `dssm_verify_2025` |

## 🐳 Docker Build

```bash
# Build locally (optional)
docker build -t dssm-webhook .
docker run -p 8080:80 dssm-webhook
```

## 🔍 Debugging

Check logs at:
- `https://your-app.onrender.com/src/logs/debug_get.txt`
- `https://your-app.onrender.com/src/logs/whatsapp_incoming.log`

## 📋 Meta Setup

1. Go to Meta Developers Console
2. Add WhatsApp product
3. Configure webhook:
   - Callback URL: `https://your-render-app.onrender.com/src/whatsapp_webhook.php`
   - Verify Token: `dssm_verify_2025`
4. Subscribe to `messages` events

## ⚠️ Security Notes

- Remove `ini_set('display_errors', 1)` in production
- Never commit log files to repository
- Use strong verify tokens
- Monitor logs for suspicious activity

## 🎯 Next Steps

This webhook currently only logs messages. To complete the EcoCash system:

1. Add database connection
2. Implement EcoCash message parsing
3. Add activation code generation
4. Send WhatsApp replies
5. Integrate with Flutter app

---

**Deployed on Render** 🚀