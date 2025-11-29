# DSSM Unlock Key System

This document describes the manual one-time unlock key system added to the existing WhatsApp EcoCash subscription system.

## Overview

The unlock key system allows administrators to manually generate one-time keys that users can redeem for premium features. Keys are valid for 5 minutes and can only be used once.

## Database Changes

### New Table: `unlock_keys`

```sql
CREATE TABLE IF NOT EXISTS unlock_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(20) UNIQUE NOT NULL, -- Alphanumeric key, e.g., XXXX-XXXX-XXXX
    expires_at DATETIME NOT NULL, -- Expires in 5 minutes
    used BOOLEAN DEFAULT FALSE, -- One-time use only
    user_id VARCHAR(50) NULL, -- User ID who redeemed the key
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_key (`key`),
    INDEX idx_expires_at (expires_at),
    INDEX idx_used (used)
);
```

## Admin Dashboard

### Access
- URL: `/admin`
- Authentication: Password-based session authentication
- Password: Set via `ADMIN_PASSWORD` environment variable (default: `admin123`)

### Features
- **Dashboard**: View key statistics and recent keys
- **Generate Key**: Create new unlock keys with 5-minute expiry
- **View Keys**: List all keys with filtering (active/used/expired)
- **Session Management**: Automatic logout after 30 minutes of inactivity

## API Endpoints

### POST `/src/verify_key.php`

Verifies and redeems an unlock key.

**Request Body:**
```json
{
  "user_id": "string",
  "unlock_key": "XXXX-XXXX-XXXX"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Premium features unlocked!",
  "premium_until": "2025-11-29T14:39:52+02:00",
  "duration_minutes": 5
}
```

**Error Response (400/500):**
```json
{
  "success": false,
  "error": "Invalid or expired unlock key"
}
```

## Key Generation

- Format: `XXXX-XXXX-XXXX` (16 characters, 4 groups of 4)
- Characters: `ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789`
- Expiry: 5 minutes from generation
- Uniqueness: Cryptographically secure random generation

## Security Features

- Password-protected admin access
- Session-based authentication with timeout
- One-time use keys
- Time-limited validity (5 minutes)
- Input validation and sanitization
- Environment variable configuration

## Integration with Existing System

- Does not interfere with existing EcoCash payment flow
- Uses the same database connection and environment variables
- Maintains existing WhatsApp webhook functionality
- Separate from activation_codes table used for WhatsApp-generated codes

## Usage Workflow

1. **Admin generates key**: Log into `/admin`, click "Generate New Key"
2. **Admin shares key**: Manually send the key to the user (via WhatsApp, email, etc.)
3. **User redeems key**: App calls `/src/verify_key.php` with user_id and key
4. **Premium unlocked**: User gets 5 minutes of premium features

## Testing

Use the test script at `/test_unlock_key.php` for API testing examples.

## Environment Variables

Add to your deployment:
```
ADMIN_PASSWORD=your_secure_password_here
```

## Files Added/Modified

### New Files:
- `admin/index.php` - Admin redirect
- `admin/login.php` - Admin login page
- `admin/dashboard.php` - Admin dashboard
- `admin/generate_key.php` - Key generation
- `admin/view_keys.php` - Key listing
- `admin/logout.php` - Session logout
- `src/verify_key.php` - Key verification API
- `test_unlock_key.php` - Test documentation

### Modified Files:
- `src/schema.sql` - Added unlock_keys table
- `index.php` - Added admin section and updated features

## Deployment Notes

1. Run the updated `schema.sql` on your MySQL database
2. Set the `ADMIN_PASSWORD` environment variable
3. Deploy the updated files to Render
4. Test admin access at `https://your-app.onrender.com/admin`
5. Test API at `https://your-app.onrender.com/src/verify_key.php`