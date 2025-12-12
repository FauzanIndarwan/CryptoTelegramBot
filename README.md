# 🤖 Crypto Telegram Bot

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.0+-777BB4? style=for-the-badge&logo=php&logoColor=white" alt="PHP Version">
  <img src="https://img.shields.io/badge/MySQL-5.7+-4479A1?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL">
  <img src="https://img.shields.io/badge/Telegram-Bot-26A5E4?style=for-the-badge&logo=telegram&logoColor=white" alt="Telegram Bot">
  <img src="https://img.shields.io/badge/Binance-API-F3BA2F?style=for-the-badge&logo=binance&logoColor=white" alt="Binance API">
</p>

<p align="center">
  <b>Bot Telegram untuk monitoring harga cryptocurrency dari Binance dengan fitur analisis teknikal otomatis</b>
</p>

---

## ✨ Fitur Utama

| Fitur | Deskripsi |
|-------|-----------|
| 📊 **Real-time Price** | Pantau harga crypto real-time dari Binance |
| 📈 **Line Chart** | Visualisasi pergerakan harga per 5 menit |
| 🕯️ **Candlestick Chart** | Chart harian dengan data OHLC (30 hari) |
| 📉 **Stochastic RSI** | Indikator teknikal untuk analisis oversold/overbought |
| 🚀 **Moon/Crash Alert** | Notifikasi otomatis saat terjadi pergerakan signifikan (>5%) |
| ⏰ **Auto Notification** | Sinyal sentimen pasar dari monitoring cron |

---

## 🚀 Quick Start

### Prerequisites
- PHP 8.0+
- MySQL 5.7+
- cURL extension
- Telegram Bot Token (dari [@BotFather](https://t.me/BotFather))

### Installation

1. **Clone repository**
```bash
git clone https://github.com/FauzanIndarwan/CryptoTelegramBot.git
cd CryptoTelegramBot
```

2. **Setup Database**
```bash
mysql -u root -p < setup_database.sql
# Or manually:
# CREATE DATABASE crypto_bot;
# Then import setup_database.sql
```

3. **Konfigurasi**
```bash
cp config.example.php config.php
# Edit config.php dengan kredensial Anda
nano config.php
```

**Configure the following:**
- `telegram.bot_token` - Your Telegram bot token from @BotFather
- `telegram.chat_id_notifikasi` - Your chat ID for notifications
- `database.*` - MySQL database credentials
- `cron.secret_key` - Secret key for cron job security

4. **Setup Webhook Telegram**
```
https://api.telegram.org/bot<YOUR_TOKEN>/setWebhook?url=https://yourdomain.com/bot. php
```

5. **Setup Cron Jobs**
```bash
# Setiap 5 menit - Update harga & sinyal
*/5 * * * * curl -s "https://yourdomain.com/bot.php? cron=YOUR_CRON_KEY"

# Setiap 1 menit - Process job queue
* * * * * php /path/to/worker.php

# Setiap hari jam 00:05 - Ambil data historis
5 0 * * * php /path/to/ambil_data_historis.php

# Setiap 4 jam - Cek StochRSI
0 */4 * * * php /path/to/cek_stoch_rsi.php
```

---

## 📱 Perintah Bot

| Perintah | Contoh | Deskripsi |
|----------|--------|-----------|
| `/start` | `/start` | Memulai bot dan melihat daftar perintah |
| `/harga` | `/harga BTC USDT` | Cek harga terkini suatu pair |
| `/chart` | `/chart ETH USDT` | Line chart pergerakan 1 jam terakhir |
| `/chartdaily` | `/chartdaily BTC USDT` | Candlestick chart 30 hari |
| `/indicator` | `/indicator BTC USDT` | Analisis Stochastic RSI |
| `/stop` | `/stop` | Batalkan semua pekerjaan dalam antrian |

**Note:** All trading pairs use USDT as the quote currency (e.g., BTC/USDT, ETH/USDT). This is different from the previous version which used IDR.

---

## 📊 Sistem Sentimen

Bot menggunakan sistem level sentimen untuk mengkategorikan kondisi pasar berdasarkan jumlah koin yang mengalami pergerakan signifikan (>5%):

### 🚀 Moon Levels (Bullish)

| Level | Jumlah Koin |
|-------|-------------|
| 💎 Diamond Moon | > 121 |
| 🥇 Golden Moon 2 | 111-120 |
| 🥇 Golden Moon 1 | 101-110 |
| 🔥 Ultra Moon 2 | 91-100 |
| 🔥 Ultra Moon 1 | 81-90 |
| ⚡ Mega Moon 2 | 71-80 |
| ⚡ Mega Moon 1 | 61-70 |
| 🌟 Super Moon 2 | 51-60 |
| 🌟 Super Moon 1 | 41-50 |
| 🌙 Moon 2 | 31-40 |
| 🌙 Moon 1 | 21-30 |
| 🚀 Go Moon 2 | 11-20 |
| 🚀 Go Moon 1 | 1-10 |

### 🔻 Crash Levels (Bearish)

| Level | Jumlah Koin |
|-------|-------------|
| 💎 Diamond Crash | > 121 |
| 🥇 Golden Crash 2 | 111-120 |
| 🥇 Golden Crash 1 | 101-110 |
| 🔥 Ultra Crash 2 | 91-100 |
| 🔥 Ultra Crash 1 | 81-90 |
| ⚡ Mega Crash 2 | 71-80 |
| ⚡ Mega Crash 1 | 61-70 |
| 🌟 Super Crash 2 | 51-60 |
| 🌟 Super Crash 1 | 41-50 |
| 📉 Crash 2 | 31-40 |
| 📉 Crash 1 | 21-30 |
| 🔻 Go Crash 2 | 11-20 |
| 🔻 Go Crash 1 | 1-10 |

---

## 🏗️ Arsitektur Project

```
CryptoTelegramBot/
│
├── 📄 bot.php                  # Entry point utama & Telegram webhook handler
├── 📄 worker.php               # Background job processor untuk antrian
├── 📄 ambil_data_historis.php  # Cron: Pengambil data OHLC harian
├── 📄 cek_stoch_rsi.php        # Cron: Pengecekan sinyal StochRSI
│
├── ⚙️ config.php               # Konfigurasi (jangan di-commit!)
├── ⚙️ config.example.php       # Template konfigurasi
├── 📄 setup_database.sql       # Database schema setup
│
├── 🔧 Core Classes
│   ├── Database.php            # Database singleton dengan connection pooling
│   ├── TelegramHelper.php      # Telegram API helper dengan reusable cURL
│   ├── BinanceAPI.php          # Binance API wrapper dengan caching dan retry
│   ├── Indicators.php          # Kalkulasi indikator teknikal (RSI, StochRSI)
│   └── ChartGenerator.php      # Generator URL chart menggunakan QuickChart
│
└── 📄 .gitignore               # File yang diabaikan Git
```

---

## ⚡ Optimasi yang Diterapkan

| Aspek | Sebelum | Sesudah |
|-------|---------|---------|
| 🔌 **Database** | Buka/tutup berulang | Singleton pattern |
| 🌐 **cURL** | Handle baru tiap request | Reusable handle |
| 💾 **Caching** | Tidak ada | API cache 60 detik |
| 🔄 **Retry Logic** | Tidak ada | Exponential backoff |
| 📦 **Batch Processing** | Satu per satu | Batch 5 pekerjaan |
| 🏛️ **Struktur** | Prosedural | OOP dengan class |
| ⚠️ **Error Handling** | Tidak konsisten | Try-catch terstruktur |
| 🔒 **Security** | Hardcoded credentials | Environment variables |

---

## 🗄️ Database Schema

```sql
-- Tabel harga real-time (dibuat otomatis per pair)
CREATE TABLE riwayat_btc_idr (
    id INT AUTO_INCREMENT PRIMARY KEY,
    harga DECIMAL(20,8) NOT NULL,
    harga_tertinggi DECIMAL(20,8) NOT NULL,
    harga_terendah DECIMAL(20,8) NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_timestamp (timestamp)
);

-- Tabel data historis harian
CREATE TABLE data_historis_harian (
    id INT AUTO_INCREMENT PRIMARY KEY,
    simbol VARCHAR(20) NOT NULL,
    waktu_buka BIGINT NOT NULL,
    harga_buka DECIMAL(20,8),
    harga_tertinggi DECIMAL(20,8),
    harga_terendah DECIMAL(20,8),
    harga_tutup DECIMAL(20,8),
    volume DECIMAL(30,8),
    UNIQUE KEY unique_symbol_time (simbol, waktu_buka),
    INDEX idx_simbol (simbol)
);

-- Tabel job queue
CREATE TABLE bot_job_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chat_id VARCHAR(50) NOT NULL,
    command VARCHAR(50) NOT NULL,
    pair VARCHAR(20) NOT NULL,
    status ENUM('pending', 'processing', 'done', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_chat_id (chat_id)
);

-- Tabel laporan sentimen
CREATE TABLE laporan_sentimen_pasar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    moon_count INT DEFAULT 0,
    moon_level VARCHAR(50),
    crash_count INT DEFAULT 0,
    crash_level VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created (created_at)
);
```

---

## 🔒 Security

> ⚠️ **PENTING**: Jangan pernah commit file `config.php` dengan kredensial asli ke repository!

### Menggunakan Environment Variables (Recommended)

```bash
# Tambahkan ke . bashrc atau .env
export BOT_TOKEN="your_bot_token"
export CHAT_ID_NOTIFIKASI="your_chat_id"
export DB_HOST="localhost"
export DB_USER="your_user"
export DB_PASS="your_password"
export DB_NAME="your_database"
export CRON_KEY="your_secret_key"
```

---

## 📝 Changelog

### v3.0.0 (2025) - Binance API Migration
- 🔄 **BREAKING:** Migrated from Indodax API to Binance API
- 🔄 **BREAKING:** Changed from IDR pairs to USDT pairs
- ✅ Refactored to full object-oriented architecture
- ✅ Added BinanceAPI wrapper class with caching and retry logic
- ✅ Implemented comprehensive technical indicators (RSI, StochRSI)
- ✅ Added ChartGenerator for QuickChart integration
- ✅ Improved database connection with singleton pattern
- ✅ Batch processing for job queue (5 jobs per batch)
- ✅ Enhanced security with environment variables support
- ✅ Added comprehensive error handling and logging
- ✅ Created modular, maintainable code structure

### v2.0.0 (2024)
- ✅ Initial OOP refactoring
- ✅ Basic caching implementation
- ✅ Support for environment variables

### v1.0.0
- 🎉 Initial release with Indodax API
- 📊 Basic price monitoring
- 📈 Chart generation
- 🔔 Moon/Crash notifications

---

## 🤝 Contributing

Kontribusi selalu diterima! Untuk perubahan besar: 

1. Fork repository ini
2. Buat branch fitur (`git checkout -b feature/AmazingFeature`)
3. Commit perubahan (`git commit -m 'Add some AmazingFeature'`)
4. Push ke branch (`git push origin feature/AmazingFeature`)
5. Buka Pull Request

---

## 📄 License

Distributed under the MIT License.  See `LICENSE` for more information.

---

<p align="center">
  Made with ❤️ for Indonesian Crypto Community
</p>

<p align="center">
  <a href="https://t.me/your_bot">🤖 Try the Bot</a> •
  <a href="https://github.com/FauzanIndarwan/CryptoTelegramBot/issues">🐛 Report Bug</a> •
  <a href="https://github.com/FauzanIndarwan/CryptoTelegramBot/issues">💡 Request Feature</a>
</p>
