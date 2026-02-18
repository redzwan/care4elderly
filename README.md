# Care For Elderly — Family Elderly Care Management

A PHP-based web application that helps families coordinate the care of elderly parents. Families can manage parent profiles, track medical appointments, store important documents, monitor medications, and coordinate who is attending each appointment — all in one shared family space.

---

## Features

- **Family Groups** — Create or join a family group with an invite code, with admin and member roles.
- **Parent Profiles** — Store each parent's personal information, medical notes, IC number, pension card, and profile photo.
- **Appointment Scheduling** — Add, edit, and track medical appointments with Google Calendar and iCal export support.
- **Participation Tracking** — Family members can join or leave appointments so everyone knows who is accompanying the parent.
- **Document Management** — Upload and organise documents (IC, medical cards, etc.) per parent with custom document categories.
- **Medication Tracking** — Record current and past medications with dosage instructions and duration.
- **Email Reminders** — Automated appointment reminders via configurable SMTP, with customisable email templates.
- **Admin Panel** — Manage users, configure SMTP settings, and customise email templates.
- **PWA Support** — Installable as a Progressive Web App on mobile devices.

---

## Tech Stack

- **Backend:** PHP (procedural), MySQL / MariaDB
- **Frontend:** Bootstrap 5, Font Awesome 6
- **Email:** PHPMailer (via `includes/mailer.php`)
- **Cron:** PHP CLI script for appointment reminders

---

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 / MariaDB 10.3 or higher
- Web server: Apache or Nginx with `.htaccess` support
- Composer (if managing dependencies manually)
- SMTP credentials for email notifications (optional but recommended)

---

## Installation

### 1. Clone the Repository

```bash
git clone https://github.com/redzwan/care4elderly.git
cd care4elderly
```

### 2. Create the Database

Log in to MySQL and create a new database:

```sql
CREATE DATABASE care4elderly CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 3. Import the Schema

```bash
mysql -u your_username -p care4elderly < database/schema.sql
```

### 4. Configure Environment Variables

Copy the example environment file and fill in your credentials:

```bash
cp .env.example .env
```

Edit `.env` with your values:

```env
DB_HOST=localhost
DB_USERNAME=your_db_username
DB_PASSWORD=your_db_password
DB_NAME=care4elderly

APP_ENV=development
APP_DOMAIN=yourdomain.com
```

### 5. Create the First Admin Account

Open your browser and navigate to:

```
http://yourdomain.com/modules/auth/create_admin.php
```

Follow the on-screen form to create the initial administrator account.

> ⚠️ **Security:** Delete `create_admin.php` immediately after creating the admin account.

```bash
rm modules/auth/create_admin.php
```

### 6. Configure SMTP (Optional)

Log in as admin and go to **Admin → Settings** to configure your SMTP server for email reminders.

### 7. Set Up the Reminder Cron Job (Optional)

To enable automated appointment reminders, add the following cron entry to send emails daily:

```bash
0 8 * * * php /path/to/your/project/cron/send_reminders.php
```

---

## Going to Production

Before deploying to a live server, update your `.env` file:

```env
APP_ENV=production
```

Also ensure:
- `logs/` directory is writable by the web server
- `assets/documents/` directory is writable for file uploads
- `.env` file is not publicly accessible (the included `.htaccess` in `logs/` is a reference — apply similar protection to `.env`)

---

## Project Structure

```
care4elderly/
├── assets/             # Static assets (icons, uploaded documents)
├── config/             # Database connection and logger
├── cron/               # Scheduled tasks (email reminders)
├── database/           # SQL schema for fresh installation
├── includes/           # Shared partials (navbar, footer, mailer)
├── logs/               # Application logs
├── modules/
│   ├── admin/          # Admin user management and settings
│   ├── auth/           # Login, register, password reset, email verification
│   ├── dashboard/      # Main dashboard view
│   ├── family/         # Family group setup and settings
│   └── parents/        # Parent profile management
├── .env.example        # Environment variable template
├── index.php           # Entry point — redirects based on session
├── manifest.json       # PWA manifest
└── style.css           # Global styles
```

---

## License

MIT License

Copyright (c) 2025 Care4TheLove1

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
