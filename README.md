# 🌐 Affiliate Marketplace Platform – Backend (Laravel API)

![Laravel](https://img.shields.io/badge/Laravel-API-red)
![PHP](https://img.shields.io/badge/PHP-8.x-blue)
![Docker](https://img.shields.io/badge/Docker-Containerized-blue)
![MySQL](https://img.shields.io/badge/MySQL-8.0-orange)
![Sanctum](https://img.shields.io/badge/Auth-Sanctum-green)
![Stripe](https://img.shields.io/badge/Payments-Stripe-purple)

This repository contains the **Laravel API backend** for an **Affiliate Marketplace Platform**, where users can discover products, creators promote affiliate products, and admins manage the ecosystem.

The system supports **multi-role architecture (User, Creator, Admin)**, affiliate link generation, earnings tracking, and payout management.

---

# 📌 Project Overview

The platform provides a scalable backend for managing affiliate marketing operations with multiple stakeholders.

### Key Features

- 👤 User authentication (Email + Google OAuth)  
- 🛍️ Affiliate product browsing & saving  
- 🏪 Creator storefront system  
- 🔗 Affiliate link generation  
- 📊 Creator earnings & payout tracking  
- 💳 Stripe Connect integration for payouts  
- 🧑‍💼 Admin dashboard & moderation system  
- 📩 Contact & support system  
- 🔐 Secure RESTful API with role-based access  

---

# 👥 User Roles

### 👤 User (Buyer)
- Browse products  
- Save/favorite products  
- View storefronts  

### 🎨 Creator
- Create and manage storefront  
- Add and manage affiliate products  
- Generate affiliate links  
- Track earnings and request payouts  
- Manage profile & albums  

### 🧑‍💼 Admin
- Manage users (creators & buyers)  
- Moderate products  
- Manage commissions (global & custom)  
- Handle payouts  
- View reports & dashboard stats  
- Manage FAQ, Terms, Privacy Policy  

---

# 🛠 Tech Stack

| Technology | Description |
|------------|-------------|
| Laravel | Backend framework |
| PHP 8.x | Programming language |
| Laravel Sanctum | API authentication |
| MySQL 8 | Database |
| Docker | Containerization |
| Docker Compose | Multi-container orchestration |
| Nginx | Web server |
| Stripe Connect | Payment & payouts |

---

# 📂 Project Structure

```
project-root
│
├── app
├── bootstrap
├── config
├── database
├── docker
│   ├── nginx
│   │   └── default.conf
│   └── php
│       └── Dockerfile
│
├── routes
├── storage
├── docker-compose.yml
└── README.md
```

---

# ⚙️ Requirements

Make sure the following tools are installed:

- Docker  
- Docker Compose  
- Git  

---

# 🚀 Installation (Docker)

## 1️⃣ Clone the repository

```bash
git clone https://github.com/your-username/affiliate-backend.git
cd affiliate-backend
```

---

## 2️⃣ Copy environment file

```bash
cp .env.example .env
```

Update database configuration inside `.env`:

```
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=affiliate_db
DB_USERNAME=affiliate_user
DB_PASSWORD=secret
```

---

## 3️⃣ Build and start containers

```bash
docker compose up -d --build
```

This will start:

| Service | Container | Port |
|--------|----------|------|
| Laravel App | PHP-FPM | internal |
| Nginx | Web Server | 8000 |
| MySQL | Database | 3307 |
| phpMyAdmin | DB Manager | 8080 |

---

## 4️⃣ Install dependencies

```bash
docker compose exec app composer install
```

---

## 5️⃣ Generate app key

```bash
docker compose exec app php artisan key:generate
```

---

## 6️⃣ Run migrations

```bash
docker compose exec app php artisan migrate
```

---

# 🌐 Access the Application

| Service | URL |
|--------|------|
| Laravel API | http://localhost:8000 |
| phpMyAdmin | http://localhost:8080 |
| MySQL | 127.0.0.1:3307 |

---

# 🐳 Docker Services

### PHP-FPM (`app`)
Runs the Laravel application.

### Nginx (`web`)
Handles HTTP requests and serves Laravel `public` directory.

### MySQL (`db`)
Stores application data.

### phpMyAdmin (`phpmyadmin`)
Provides database management UI.

---

# 🔧 Useful Commands

### Start containers

```bash
docker compose up -d
```

### Stop containers

```bash
docker compose down
```

### Rebuild containers

```bash
docker compose up -d --build
```

### Run Artisan commands

```bash
docker compose exec app php artisan <command>
```

Example:

```bash
docker compose exec app php artisan migrate
```

---

# 🔗 API Modules Overview

## 🔐 Auth
- Register / Login / Logout  
- Google OAuth  
- Email verification (OTP)  
- Password reset  

---

## 👤 User Profile
- View / Update profile  
- Change password  

---

## 🏪 Storefront
- Create storefront  
- Public storefront view  
- Products listing  

---

## 🛍️ Products
- Create / update / delete  
- Affiliate link generation  
- Viator & Expedia integrations  

---

## ❤️ Saved Products
- Toggle save/unsave  

---

## 🎨 Creator Dashboard
- Profile & albums  
- Earnings  
- Payout requests  

---

## 💳 Payments
- Stripe Connect onboarding  
- Webhook handling  

---

## 🧑‍💼 Admin Panel
- Dashboard stats  
- User management  
- Product moderation  
- Commission system  
- Payout management  

---

## 📩 Public Endpoints
- FAQ  
- Terms  
- Privacy Policy  
- Contact messages  

---

# 💰 Commission System

- Global commission setup  
- Custom commission per creator  
- Viator & Expedia commission handling  

---

# 🛠 Troubleshooting

### Fix permission issues

```bash
docker compose exec app chown -R www-data:www-data /var/www/html
docker compose exec app chmod -R 775 /var/www/html
```

---

### Fix upload limits

```bash
docker compose exec app bash
```

Then run:

```bash
echo "upload_max_filesize=100M" > /usr/local/etc/php/conf.d/uploads.ini
echo "post_max_size=100M" >> /usr/local/etc/php/conf.d/uploads.ini
```

Reload nginx:

```bash
docker exec -it affiliate_backend_web nginx -s reload
```

---

# 📊 Database Access

### phpMyAdmin

```
http://localhost:8080
```

Login:

```
Server: db
Username: root
Password: root
```

---

# 🚧 Project Status

⚠️ **📦 Delivered (Pending Deployment)**

---

# 📄 License

This project is the property of **SparkTech Agency**.  

Developed by **Md. Omar Faruk**.  

All rights reserved.