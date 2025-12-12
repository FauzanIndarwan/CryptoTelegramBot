# Installation Guide

This guide will help you set up the Crypto Telegram Bot on your server.

## Prerequisites

- **PHP 8.0+** with the following extensions:
  - mysqli
  - curl
  - json
  - mbstring
- **MySQL 5.7+** or MariaDB
- **Web server** (Apache/Nginx) with PHP support
- **Telegram Bot Token** from [@BotFather](https://t.me/BotFather)
- **SSL Certificate** (required for Telegram webhook)

## Step 1: Clone Repository

```bash
cd /var/www/html
git clone https://github.com/FauzanIndarwan/CryptoTelegramBot.git
cd CryptoTelegramBot
```

## Step 2: Database Setup

### Create Database

```bash
mysql -u root -p
```

```sql
CREATE DATABASE crypto_bot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'crypto_bot_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON crypto_bot.* TO 'crypto_bot_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### Import Database Schema

```bash
mysql -u crypto_bot_user -p crypto_bot < setup_database.sql
```

## Step 3: Configuration

### Copy Configuration Template

```bash
cp config.example.php config.php
```

### Edit Configuration

```bash
nano config.php
```

**Required settings:**

```php
'telegram' => [
    'bot_token' => 'YOUR_TELEGRAM_BOT_TOKEN_HERE',
    'chat_id_notifikasi' => 'YOUR_NOTIFICATION_CHAT_ID',
],

'database' => [
    'host' => 'localhost',
    'user' => 'crypto_bot_user',
    'password' => 'your_secure_password',
    'name' => 'crypto_bot',
],

'cron' => [
    'secret_key' => 'generate_a_random_secret_key_here',
],
```

**Security Note:** Never commit `config.php` to version control!

### Using Environment Variables (Recommended for Production)

Create a `.env` file or set environment variables:

```bash
export BOT_TOKEN="your_bot_token"
export CHAT_ID_NOTIFIKASI="your_chat_id"
export DB_HOST="localhost"
export DB_USER="crypto_bot_user"
export DB_PASS="your_secure_password"
export DB_NAME="crypto_bot"
export CRON_KEY="your_secret_key"
```

## Step 4: Set Permissions

```bash
# Make sure web server can read files
chown -R www-data:www-data /var/www/html/CryptoTelegramBot
chmod 644 *.php
chmod 600 config.php  # Protect sensitive config
```

## Step 5: Configure Web Server

### Apache (.htaccess)

Create `.htaccess` in the bot directory:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /CryptoTelegramBot/
    
    # Prevent access to sensitive files
    <FilesMatch "^(config\.php|\.env|\.git)">
        Order allow,deny
        Deny from all
    </FilesMatch>
</IfModule>
```

### Nginx

Add to your server block:

```nginx
location ~ ^/CryptoTelegramBot/(config\.php|\.env|\.git) {
    deny all;
    return 404;
}
```

## Step 6: Set Up Telegram Webhook

### Get Your Chat ID

1. Message your bot: `/start`
2. Visit: `https://api.telegram.org/bot<YOUR_BOT_TOKEN>/getUpdates`
3. Find your chat ID in the JSON response

### Set Webhook

Replace placeholders with your actual values:

```bash
curl -X POST "https://api.telegram.org/bot<YOUR_BOT_TOKEN>/setWebhook?url=https://yourdomain.com/CryptoTelegramBot/bot.php"
```

Expected response:
```json
{"ok":true,"result":true,"description":"Webhook was set"}
```

### Verify Webhook

```bash
curl "https://api.telegram.org/bot<YOUR_BOT_TOKEN>/getWebhookInfo"
```

## Step 7: Configure Cron Jobs

Edit crontab:

```bash
crontab -e
```

Add the following lines (adjust paths as needed):

```bash
# Process job queue every minute
* * * * * /usr/bin/php /var/www/html/CryptoTelegramBot/worker.php >> /var/log/crypto_bot_worker.log 2>&1

# Market sentiment monitoring every 5 minutes
*/5 * * * * /usr/bin/curl -s "https://yourdomain.com/CryptoTelegramBot/bot.php?cron=YOUR_CRON_SECRET_KEY" >> /var/log/crypto_bot_cron.log 2>&1

# Fetch historical data daily at 00:05
5 0 * * * /usr/bin/php /var/www/html/CryptoTelegramBot/ambil_data_historis.php >> /var/log/crypto_bot_historical.log 2>&1

# Check StochRSI every 4 hours
0 */4 * * * /usr/bin/php /var/www/html/CryptoTelegramBot/cek_stoch_rsi.php >> /var/log/crypto_bot_stochrsi.log 2>&1
```

## Step 8: Test the Bot

1. Open Telegram and find your bot
2. Send `/start` to see the welcome message
3. Try commands:
   - `/harga BTC USDT`
   - `/chart ETH USDT`
   - `/indicator SOL USDT`

## Step 9: Monitoring

### Check Logs

```bash
# Worker logs
tail -f /var/log/crypto_bot_worker.log

# Cron logs
tail -f /var/log/crypto_bot_cron.log

# PHP error logs
tail -f /var/log/apache2/error.log  # or /var/log/nginx/error.log
```

### Monitor Database

```sql
-- Check job queue
SELECT * FROM bot_job_queue ORDER BY created_at DESC LIMIT 10;

-- Check sentiment reports
SELECT * FROM laporan_sentimen_pasar ORDER BY created_at DESC LIMIT 10;

-- Check historical data
SELECT COUNT(*) FROM data_historis_harian;
```

## Troubleshooting

### Bot Not Responding

1. Check webhook status: `curl "https://api.telegram.org/bot<TOKEN>/getWebhookInfo"`
2. Verify SSL certificate is valid
3. Check PHP error logs
4. Ensure `bot.php` is accessible from the web

### Worker Not Processing Jobs

1. Check if cron is running: `sudo systemctl status cron`
2. Verify paths in crontab
3. Check worker log file for errors
4. Test manually: `php worker.php`

### Database Connection Errors

1. Verify MySQL service is running
2. Check credentials in `config.php`
3. Ensure database user has proper permissions
4. Test connection: `mysql -u crypto_bot_user -p crypto_bot`

### API Rate Limiting

If you hit Binance API rate limits:
- Increase cache duration in config
- Reduce cron job frequency
- Contact Binance for higher limits

## Security Checklist

- [ ] `config.php` has restricted permissions (600 or 640)
- [ ] `.gitignore` is properly configured
- [ ] Environment variables are used for production
- [ ] Webhook uses HTTPS with valid SSL
- [ ] Cron secret key is strong and unique
- [ ] Database user has minimal required permissions
- [ ] Web server blocks access to sensitive files
- [ ] PHP error display is disabled in production
- [ ] Regular backups are configured

## Updating the Bot

```bash
cd /var/www/html/CryptoTelegramBot
git pull origin main
# Review and merge any config changes from config.example.php
```

## Support

For issues and questions:
- GitHub Issues: https://github.com/FauzanIndarwan/CryptoTelegramBot/issues
- Check existing documentation in README.md
