# Contributing to Crypto Telegram Bot

Thank you for your interest in contributing! This document provides guidelines for contributing to the project.

## Code of Conduct

- Be respectful and inclusive
- Welcome newcomers and help them get started
- Focus on constructive feedback
- Keep discussions professional and on-topic

## Getting Started

1. Fork the repository
2. Clone your fork: `git clone https://github.com/YOUR_USERNAME/CryptoTelegramBot.git`
3. Create a branch: `git checkout -b feature/your-feature-name`
4. Make your changes
5. Test thoroughly
6. Commit with clear messages
7. Push to your fork
8. Open a Pull Request

## Development Setup

### Prerequisites

- PHP 8.0+ with mysqli, curl, json extensions
- MySQL 5.7+ or MariaDB
- Composer (optional, for future dependencies)
- Telegram Bot Token (for testing)

### Local Setup

```bash
# Clone repository
git clone https://github.com/FauzanIndarwan/CryptoTelegramBot.git
cd CryptoTelegramBot

# Copy configuration
cp config.example.php config.php

# Edit with your test credentials
nano config.php

# Setup test database
mysql -u root -p < setup_database.sql
```

## Code Standards

### PHP Style Guide

1. **Follow PSR-12 coding standards**
   - Use 4 spaces for indentation (no tabs)
   - Opening braces on the same line for functions/methods
   - One statement per line

2. **Naming Conventions**
   - Classes: `PascalCase` (e.g., `BinanceAPI`)
   - Methods/Functions: `camelCase` (e.g., `getPrice`)
   - Constants: `UPPER_SNAKE_CASE` (e.g., `MAX_RETRIES`)
   - Variables: `camelCase` (e.g., `$userName`)

3. **Documentation**
   - Add PHPDoc comments for all classes, methods, and functions
   - Include parameter types and return types
   - Describe what the code does, not how

   ```php
   /**
    * Calculate Simple Moving Average (SMA)
    * 
    * @param array $values Array of values
    * @param int $period Period for SMA
    * @return array Array of SMA values
    */
   public static function calculateSMA($values, $period) {
       // Implementation
   }
   ```

### Security Requirements

1. **Input Validation**
   - Sanitize all user inputs
   - Use prepared statements for database queries
   - Validate data types and formats

2. **SQL Injection Prevention**
   ```php
   // ✅ Good - using prepared statement
   $stmt = $db->prepare("SELECT * FROM users WHERE id = ?", [$userId], 'i');
   
   // ❌ Bad - direct interpolation
   $result = $db->query("SELECT * FROM users WHERE id = $userId");
   ```

3. **XSS Prevention**
   - Sanitize output to users
   - Be careful with Markdown formatting
   - Remove special characters from user-provided content

4. **API Security**
   - Always use HTTPS
   - Verify SSL certificates
   - Implement rate limiting
   - Use timing-safe comparisons for secrets

### Database Guidelines

1. **Use prepared statements** for all queries
2. **Close statements** after use to free resources
3. **Handle errors** gracefully with try-catch
4. **Use transactions** for related operations
5. **Index columns** used in WHERE clauses and JOINs

### API Integration Guidelines

1. **Implement caching** to reduce API calls
2. **Use retry logic** with exponential backoff
3. **Handle rate limits** gracefully
4. **Set timeouts** on all requests
5. **Reuse cURL handles** for better performance

## Testing

### Manual Testing Checklist

Before submitting a PR, test:

- [ ] All bot commands work correctly
- [ ] Error handling works for invalid inputs
- [ ] Database operations complete successfully
- [ ] No PHP syntax errors
- [ ] No warnings or notices in logs
- [ ] Cron jobs execute properly
- [ ] API calls succeed and cache correctly

### Testing Commands

```bash
# Check PHP syntax
php -l bot.php

# Test worker manually
php worker.php

# Test historical data fetch
php ambil_data_historis.php

# Test StochRSI checker
php cek_stoch_rsi.php
```

## Pull Request Guidelines

### Before Submitting

1. **Update documentation** if needed
2. **Test all changes** thoroughly
3. **Check for security issues**
4. **Ensure code follows style guide**
5. **Write clear commit messages**

### PR Title Format

Use conventional commit format:
- `feat: Add new feature`
- `fix: Fix bug in worker`
- `docs: Update README`
- `refactor: Improve code structure`
- `security: Fix SQL injection vulnerability`
- `perf: Optimize API caching`

### PR Description Template

```markdown
## What does this PR do?
Brief description of changes

## Why is this needed?
Explain the motivation

## How has this been tested?
Describe your testing process

## Screenshots (if applicable)
Add screenshots for UI changes

## Checklist
- [ ] Code follows style guidelines
- [ ] Documentation updated
- [ ] All tests pass
- [ ] No security vulnerabilities introduced
- [ ] Backwards compatible (or breaking changes documented)
```

## Areas for Contribution

### High Priority

1. **Unit Tests**
   - Add PHPUnit tests for core classes
   - Test database operations
   - Test API wrappers

2. **Error Handling**
   - Improve error messages
   - Add more specific exception types
   - Better logging

3. **Performance**
   - Optimize database queries
   - Improve caching strategies
   - Reduce API calls

### Medium Priority

1. **Features**
   - Additional technical indicators (MACD, Bollinger Bands)
   - Support for more exchanges
   - User preferences system
   - Alert customization

2. **Documentation**
   - API documentation
   - Architecture diagrams
   - Video tutorials
   - Translations

3. **User Experience**
   - Interactive keyboards
   - Inline queries
   - Better chart formatting
   - Customizable alerts

### Low Priority

1. **Web Dashboard**
   - Real-time price charts
   - User management
   - Admin panel
   - API endpoints

2. **Monitoring**
   - Health check endpoint
   - Performance metrics
   - Usage statistics
   - Alert system

## Architecture Overview

### Key Components

1. **bot.php** - Telegram webhook handler
   - Receives messages from users
   - Queues jobs for processing
   - Handles cron triggers

2. **worker.php** - Job processor
   - Processes queued jobs in batches
   - Fetches data from Binance
   - Generates charts and sends to users

3. **BinanceAPI.php** - API wrapper
   - Handles all Binance API calls
   - Implements caching and retry logic
   - Formats data for consumption

4. **Database.php** - Database layer
   - Singleton pattern for connections
   - Prepared statement helpers
   - Transaction support

5. **Indicators.php** - Technical analysis
   - RSI, StochRSI calculations
   - Market sentiment analysis
   - Signal interpretation

### Data Flow

```
User → Telegram → bot.php → Database (job queue)
                                ↓
                            worker.php
                                ↓
                          BinanceAPI.php
                                ↓
                          Process & Format
                                ↓
                        TelegramHelper.php
                                ↓
                            User receives result
```

## Common Issues

### "Database connection failed"
- Check MySQL service is running
- Verify credentials in config.php
- Ensure database exists

### "Telegram webhook not working"
- Must use HTTPS with valid SSL
- Check webhook URL is correct
- Verify bot token is valid

### "Worker not processing jobs"
- Check cron is configured
- Verify file permissions
- Look at log files for errors

## Resources

- [PHP Documentation](https://www.php.net/manual/en/)
- [Binance API Docs](https://binance-docs.github.io/apidocs/)
- [Telegram Bot API](https://core.telegram.org/bots/api)
- [PSR-12 Coding Standard](https://www.php-fig.org/psr/psr-12/)

## Questions?

- Open an issue for bugs or feature requests
- Use discussions for questions
- Check existing issues before creating new ones

Thank you for contributing! 🎉
