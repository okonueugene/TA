# Time Attendance Processor ZK-Time And Attendance

This project provides a robust and flexible solution for processing raw attendance data (clock-in/out punches) and transforming it into structured employee shift records. The system is designed to handle complex shift patterns, including cross-day night shifts and specialized overtime rules for different employee groups, such as the Blowmolding department.

## 🚀 Getting Started

### Prerequisites

  * PHP 8.2 or higher
  * Composer
  * MySQL 8.0 or compatible database
  * A running web server environment (e.g., Apache, Nginx, or Docker with a pre-configured stack like Laravel Sail or XAMPP)

### Installation

1.  **Clone the repository:**

    ```bash
    git clone https://github.com/okonueugene/TA.git
    cd TA
    ```

2.  **Install PHP dependencies:**

    ```bash
    composer install
    ```

3.  **Set up your environment file:**
    Copy the `.env.example` file and configure your database connection and other environment variables.

    ```bash
    cp .env.example .env
    ```

    Then, edit the `.env` file with your specific credentials.

4.  **Generate an application key:**

    ```bash
    php artisan key:generate
    ```

5.  **Run migrations and seed the database (if applicable):**
    This will create the necessary tables, including `employees`, `attendances`, and `employee_shifts`.

    ```bash
    php artisan migrate --seed
    ```

## ⚙️ Configuration

The application's behavior is configured via the `.env` file and the `config/` directory.

### `.env` File

Below are the key environment variables you need to configure.

```ini
# Database Connection
DB_CONNECTION=mysql
DB_HOST=127.0.1.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password

# TAD Device Configuration
TAD_IP=192.168.0.1
TAD_INTERNAL_ID=1
TAD_COM_KEY=0
TAD_DESCRIPTION="Time Attendance Device"
TAD_SOAP_PORT=80
TAD_UDP_PORT=4370
TAD_ENCODING=utf-8

# Custom TAD Sync Settings
# Comma-separated list of emails for sync notifications
TAD_NOTIFY_EMAILS="email1@example.com,email2@example.com"
# Cache TTLs in seconds
TAD_CACHE_ERROR_TTL=3600
TAD_CACHE_LAST_ERROR_TTL=86400
```

### `config/tad.php`

This configuration file holds the settings for the Time Attendance Device and is where the `.env` variables are loaded.

```php
<?php

return [
    // Device connection settings
    'ip' => env('TAD_IP', '192.168.0.1'),
    'internal_id' => env('TAD_INTERNAL_ID', 1),
    'com_key' => env('TAD_COM_KEY', 0),
    'description' => env('TAD_DESCRIPTION', 'Time Attendance Device'),
    'soap_port' => env('TAD_SOAP_PORT', 80),
    'udp_port' => env('TAD_UDP_PORT', 4370),
    'encoding' => env('TAD_ENCODING', 'utf-8'),

    // Custom: Notification emails for sync events
    'notify_emails' => env('TAD_NOTIFY_EMAILS', ['email1@example.com', 'email2@example.com']),

    // Custom: Cache TTLs (in seconds)
    'cache_ttl' => [
        'error' => env('TAD_CACHE_ERROR_TTL', 3600),
        'last_error' => env('TAD_CACHE_LAST_ERROR_TTL', 86400),
    ],
];
```

## 📜 Core Concepts

The system is built around three key data models and a central processing service:

  * **`Attendance`**: The raw, unprocessed clock-in and clock-out punches from a time clock.
  * **`Employee`**: Employee details, including a `pin` for matching with attendance records and an `is_blowmolding` flag for special rules.
  * **`EmployeeShift`**: The final, processed record representing a single shift with calculated hours, overtime, and anomalies.
  * **`AttendanceProcessor`**: A core service class that orchestrates the entire process of pairing attendance punches, calculating shift metrics, and persisting `EmployeeShift` records.

## 🛠️ Usage

The primary way to interact with the processor is via a Laravel console command.

### Running the Processor

You can run the command with various options to process attendance data for different timeframes and employees.

```bash
php artisan process:shifts {date?} {--month=} {--employee=}
```

  * **Process yesterday's shifts (default):**

    ```bash
    php artisan process:shifts
    ```

  * **Process a specific date:**

    ```bash
    php artisan process:shifts 2025-07-29
    ```

  * **Process a full month:**

    ```bash
    php artisan process:shifts --month=2025-07
    ```

  * **Process a specific employee:**

    ```bash
    php artisan process:shifts --employee=12345 --month=2025-07
    ```

### Scheduling the Command

For production use, you should schedule this command to run automatically (e.g., daily) using Laravel's task scheduler. Add the following to your `app/Console/Kernel.php` file:

```php
// app/Console/Kernel.php

protected function schedule(Schedule $schedule): void
{
    $schedule->command('process:shifts')->dailyAt('02:00');
}
```

## 🚀 Key Features

### 1\. Robust Shift Pairing

The `AttendanceProcessor` intelligently pairs `in` and `out` punches, handling common issues like:

  * **Cross-day night shifts**: A shift starting on one day and ending on the next is correctly associated with its starting date.
  * **Missing punches**: Unpaired punches are flagged as anomalies.
  * **Duplicate punches**: Closely timed duplicate punches are identified and managed.

### 2\. Specialized Blowmolding Overtime Rules

A dedicated logic layer handles the unique overtime rules for the Blowmolding department, which include:

  * **4-Day Night Shift Cycle (M-T-W-T):** Standard overtime rules apply.
  * **3-Day Night Shift Cycle (F-S-S):** The Sunday night shift does **not** get a special 2x overtime rate; it is treated as a regular shift.
  * The system uses persistent state (`consecutive_night_shifts`, `night_shift_cycle_start_date`) on the `Employee` model to track and apply these rules accurately.

### 3\. Anomaly Detection and Logging

The processor logs detailed information about shifts and anomalies. It creates special `EmployeeShift` records for:

  * `missing_clockin`
  * `missing_clockout`
  * `short_shift_anomaly`
  * `double_punch_anomaly`