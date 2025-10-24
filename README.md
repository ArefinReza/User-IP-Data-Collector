# Visitor IP Logger (Raw PHP)

## Overview
A simple PHP-based visitor logger that captures IP, geo data, device, browser, session id, and time-on-page. Start time is recorded on page load; end time & total time is recorded via `navigator.sendBeacon()` on unload.

## File Structure
- `index.php` — main page, starts session & includes `store_ip.php`.
- `store_ip.php` — gathers IP + geo + UA + device info, inserts into DB.
- `store_end_time.php` — receives POST (session_id, total_time_spent) and updates the visitors record's end_time and total_time_spent.
- `db_config.php` — DB connection.
- `vendor/` — composer deps (MobileDetect).
- `error_log.txt` — logs server errors.

## DB Schema
CREATE DATABASE IF NOT EXISTS ipcollection CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ipcollection;

CREATE TABLE IF NOT EXISTS visitors (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip_address VARCHAR(45) NOT NULL,
  region VARCHAR(128) DEFAULT NULL,
  city VARCHAR(128) DEFAULT NULL,
  asn VARCHAR(64) DEFAULT NULL,
  isp VARCHAR(255) DEFAULT NULL,
  latitude DOUBLE DEFAULT 0,
  longitude DOUBLE DEFAULT 0,
  destination_port INT DEFAULT 0,
  session_id VARCHAR(128) DEFAULT NULL,
  user_agent TEXT,
  device_type VARCHAR(64) DEFAULT NULL,
  browser VARCHAR(64) DEFAULT NULL,
  start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  end_time TIMESTAMP NULL DEFAULT NULL,
  total_time_spent INT NULL, -- seconds
  INDEX (ip_address),
  INDEX (session_id),
  INDEX (start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


## How it works (flow)
1. `index.php` starts PHP session.
2. `store_ip.php` runs (on include) and inserts a visitor row (start_time created automatically).
3. Client JS captures `session_id` and startTime and on page-exit sends a beacon to `store_end_time.php`.
4. `store_end_time.php` updates the row with `end_time` and `total_time_spent`.

## Important Notes / Improvements
- Do **not** echo a full HTML page from `store_ip.php` when including it inside `index.php`. Convert it to a silent processor or call it via AJAX.
- Use cURL instead of `file_get_contents()` for remote API calls.
- Consider HTTPS geolocation APIs in production.
- Respect privacy laws when storing precise geolocation.

## Quick Setup
1. `composer install`
2. Import SQL schema
3. Update `db_config.php`
4. Ensure `error_log.txt` write permissions
5. Open `index.php` to test

