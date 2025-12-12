# Security Documentation

This document outlines the security measures implemented in the Crypto Telegram Bot and best practices for maintaining a secure deployment.

## Security Features Implemented

### 1. Input Sanitization

#### Symbol Validation (BinanceAPI.php)
- All cryptocurrency symbols are sanitized using `preg_replace('/[^A-Za-z0-9]/', '', $input)`
- Only alphanumeric characters are allowed
- Prevents injection attacks through trading pair manipulation

```php
public static function formatSymbol($base, $quote) {
    // Sanitize inputs - only allow alphanumeric characters
    $base = preg_replace('/[^A-Za-z0-9]/', '', $base);
    $quote = preg_replace('/[^A-Za-z0-9]/', '', $quote);
    return strtoupper($base) . strtoupper($quote);
}
```

#### Table Name Validation (worker.php)
- Dynamic table names are sanitized and validated against expected patterns
- Pattern validation: `/^riwayat_[a-z0-9]+_usdt$/`
- Prevents SQL injection through table name manipulation

```php
// Sanitize symbol
$sanitizedSymbol = preg_replace('/[^A-Za-z0-9]/', '', $symbol);

// Validate symbol format
if (substr($sanitizedSymbol, -4) !== 'USDT') {
    error_log("Invalid symbol format: $symbol");
    return;
}

// Additional pattern validation
if (!preg_match('/^riwayat_[a-z0-9]+_usdt$/', $tableName)) {
    error_log("Invalid table name generated: $tableName");
    return;
}
```

#### Markdown Injection Prevention (bot.php)
- User names from Telegram are sanitized to prevent Markdown injection
- Special characters `*_`[]` are removed before use in messages

```php
$userName = preg_replace('/[*_`\[\]]/', '', $message['from']['first_name'] ?? 'User');
```

### 2. SQL Injection Prevention

#### Prepared Statements
All database queries use prepared statements with parameter binding:

```php
$stmt = $db->prepare(
    "INSERT INTO bot_job_queue (chat_id, command, pair, status) VALUES (?, ?, ?, 'pending')",
    [$chatId, $command, $symbol],
    'sss'
);
```

#### Type Casting
Numeric values are explicitly cast to prevent type confusion:

```php
$batchSize = (int)$config['worker']['batch_size'];
```

### 3. Authentication & Authorization

#### Cron Job Authentication
- Cron endpoints require a secret key
- Uses timing-safe comparison with `hash_equals()` to prevent timing attacks

```php
if (isset($_GET['cron']) && hash_equals($config['cron']['secret_key'], $_GET['cron'])) {
    handleCronJob($telegram, $binance, $db, $config);
    exit;
}
```

**Why hash_equals()?**
- Prevents timing attacks that could guess the secret key
- Compares strings in constant time regardless of differences

### 4. Configuration Security

#### Environment Variables
Sensitive credentials support environment variables:

```php
'bot_token' => getenv('BOT_TOKEN') ?: 'YOUR_TELEGRAM_BOT_TOKEN_HERE',
'db_password' => getenv('DB_PASS') ?: 'your_database_password',
```

#### .gitignore Protection
Sensitive files are excluded from version control:
- `config.php` (contains actual credentials)
- `.env` files
- `*.sql` files (except setup_database.sql)
- Log files

### 5. Database Security

#### Connection Management
- Uses singleton pattern to manage connection lifecycle
- Automatically reconnects if connection is lost
- Closes connections properly in destructors

#### Character Set
- Uses `utf8mb4` charset to prevent encoding-based attacks
- Supports full Unicode range including emojis

```php
$this->connection->set_charset($dbConfig['charset']);
```

### 6. API Security

#### SSL/TLS Verification
- cURL requests verify SSL certificates by default
- Prevents man-in-the-middle attacks

```php
curl_setopt($this->curlHandle, CURLOPT_SSL_VERIFYPEER, true);
```

#### Rate Limiting Protection
- Implements caching to reduce API calls
- Exponential backoff on retry attempts
- Configurable cache duration

```php
// Exponential backoff
if ($attempt < $maxRetries) {
    sleep(pow(2, $attempt - 1));
}
```

#### Timeout Configuration
- All API requests have 30-second timeout
- Prevents hanging connections

```php
curl_setopt($this->curlHandle, CURLOPT_TIMEOUT, 30);
```

## Security Best Practices

### Deployment

1. **File Permissions**
   ```bash
   chmod 600 config.php  # Only owner can read/write
   chmod 644 *.php       # Owner can write, others read-only
   chown www-data:www-data /path/to/bot
   ```

2. **Web Server Configuration**
   - Block access to `config.php` and `.env` files
   - Use HTTPS for all webhook endpoints
   - Keep web server and PHP updated

3. **Database Security**
   - Use dedicated database user with minimal privileges
   - Only grant necessary permissions (SELECT, INSERT, UPDATE, DELETE)
   - No GRANT, DROP, or ALTER permissions needed
   - Use strong passwords (20+ characters)

4. **Cron Secret Key**
   - Generate cryptographically secure random key
   - Minimum 32 characters
   - Use different key for each environment

   ```bash
   # Generate secure key
   openssl rand -base64 32
   ```

### Monitoring

1. **Log Monitoring**
   - Monitor for repeated failed authentication attempts
   - Watch for unusual API usage patterns
   - Track SQL errors that might indicate injection attempts

2. **Database Monitoring**
   - Monitor for unexpected table creation
   - Watch for abnormal query patterns
   - Set up alerts for failed transactions

3. **API Monitoring**
   - Track Binance API rate limit usage
   - Monitor for 429 (rate limit) responses
   - Set up alerts for API failures

### Regular Maintenance

1. **Updates**
   - Keep PHP updated to latest stable version
   - Update MySQL/MariaDB regularly
   - Monitor for security advisories

2. **Credential Rotation**
   - Rotate Telegram bot token annually
   - Change database passwords regularly
   - Update cron secret keys periodically

3. **Backups**
   - Regular database backups
   - Store backups securely (encrypted)
   - Test backup restoration regularly

4. **Audit**
   - Review logs regularly
   - Check for suspicious activity
   - Perform security audits quarterly

## Vulnerability Disclosure

If you discover a security vulnerability:

1. **DO NOT** open a public GitHub issue
2. Email the maintainers directly with:
   - Description of the vulnerability
   - Steps to reproduce
   - Potential impact
   - Suggested fix (if any)
3. Allow 90 days for patch before public disclosure

## Known Limitations

1. **No Rate Limiting on Bot Commands**
   - Users can spam commands
   - Mitigated by job queue system
   - Future: Implement per-user rate limiting

2. **No User Authentication**
   - Any Telegram user can use the bot
   - Future: Implement user whitelist/blacklist

3. **No Encrypted Storage**
   - Database stores data in plain text
   - Historical price data is not sensitive
   - No private user data is stored

4. **API Key Storage**
   - Binance API keys stored in config file
   - Only needed for private endpoints (not currently used)
   - Future: Consider key management service

## Security Checklist

Before deploying to production:

- [ ] All credentials moved to environment variables
- [ ] Strong passwords used (20+ characters)
- [ ] File permissions set correctly
- [ ] Web server blocks access to sensitive files
- [ ] HTTPS configured with valid SSL certificate
- [ ] Cron secret key is cryptographically secure
- [ ] Database user has minimal privileges
- [ ] PHP error display disabled in production
- [ ] Logging enabled and monitored
- [ ] Backups configured and tested
- [ ] Security headers configured in web server
- [ ] Regular update schedule established
- [ ] Monitoring and alerting set up

## Additional Resources

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [PHP Security Guide](https://www.php.net/manual/en/security.php)
- [MySQL Security Best Practices](https://dev.mysql.com/doc/refman/8.0/en/security-guidelines.html)
- [Telegram Bot Security](https://core.telegram.org/bots/webhooks#security)

## Contact

For security concerns, contact the maintainers through GitHub.
