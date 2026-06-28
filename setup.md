# Rapid Rescue — IONOS VPS Deployment Guide

## Stack
- **OS**: Ubuntu 22.04 LTS (recommended)
- **PHP**: 8.2
- **Web Server**: Nginx + PHP-FPM
- **Database**: SQLite (default) — MySQL optional
- **WebSocket**: Laravel Reverb (port 8080)
- **Process Manager**: Supervisor (keeps Reverb alive)
- **SSL**: Certbot (Let's Encrypt)

---

## 1. Initial VPS Setup

```bash
# Log in as root
ssh root@YOUR_VPS_IP

# Update system
apt update && apt upgrade -y

# Create a non-root user
adduser rapidrescue
usermod -aG sudo rapidrescue
su - rapidrescue
```

---

## 2. Install Required Software

### 2a. Nginx
```bash
sudo apt install -y nginx
sudo systemctl enable nginx
sudo systemctl start nginx
```

### 2b. PHP 8.2 + Extensions
```bash
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

sudo apt install -y \
  php8.2 \
  php8.2-fpm \
  php8.2-cli \
  php8.2-sqlite3 \
  php8.2-mbstring \
  php8.2-xml \
  php8.2-curl \
  php8.2-zip \
  php8.2-bcmath \
  php8.2-tokenizer \
  php8.2-fileinfo \
  php8.2-pdo \
  php8.2-openssl \
  php8.2-sockets
```

### 2c. Composer
```bash
cd ~
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

### 2d. Git
```bash
sudo apt install -y git
```

### 2e. Supervisor (keeps Reverb running)
```bash
sudo apt install -y supervisor
sudo systemctl enable supervisor
sudo systemctl start supervisor
```

### 2f. Certbot (SSL)
```bash
sudo apt install -y certbot python3-certbot-nginx
```

---

## 3. Clone the Project

```bash
sudo mkdir -p /var/www/rapidrescue
sudo chown rapidrescue:rapidrescue /var/www/rapidrescue

cd /var/www/rapidrescue
git clone https://github.com/YOUR_USERNAME/YOUR_REPO.git .
```

---

## 4. Install PHP Dependencies

```bash
cd /var/www/rapidrescue
composer install --no-dev --optimize-autoloader
```

---

## 5. Configure Environment

```bash
cp .env.example .env
nano .env
```

Fill in the following values (replace everything in `< >`):

```env
APP_NAME="Rapid Rescue"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<your-domain.com>

# Database — SQLite (default)
DB_CONNECTION=sqlite
# The database file will be at: /var/www/rapidrescue/database/database.sqlite

# Broadcasting
BROADCAST_CONNECTION=reverb

# Reverb — SERVER side (PHP → Reverb, internal connection)
REVERB_APP_ID=<choose-any-id>
REVERB_APP_KEY=<choose-a-random-key>
REVERB_APP_SECRET=<choose-a-random-secret>
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http

# Reverb — CLIENT side (browser → Reverb, public connection)
# This is the host your users' browsers connect to
REVERB_CLIENT_HOST=<your-domain.com>
REVERB_CLIENT_SCHEME=https

# Session
SESSION_DRIVER=file
SESSION_LIFETIME=120

# Cache & Queue
CACHE_STORE=file
QUEUE_CONNECTION=sync

# Mail (update with your SMTP details)
MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_USERNAME=<your-email>
MAIL_PASSWORD=<your-password>
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=<your-email>
MAIL_FROM_NAME="Rapid Rescue"

# reCAPTCHA (get keys from Google reCAPTCHA console)
RECAPTCHA_SITE_KEY=<your-site-key>
RECAPTCHA_SECRET_KEY=<your-secret-key>
```

Generate application key:
```bash
php artisan key:generate
```

---

## 6. Set Up the SQLite Database

```bash
# Create the SQLite file
touch /var/www/rapidrescue/database/database.sqlite

# Run migrations
php artisan migrate --force

# Seed initial data (admin, demo user, driver, 5 emergency requests)
php artisan db:seed --force
```

**Default credentials after seeding:**

| Role   | Login                    | Password   |
|--------|--------------------------|------------|
| Admin  | admin@rapidrescue.com    | Admin1@    |
| User   | user@rapidrescue.com     | User@1234  |
| Driver | driver@rapidrescue.com   | Driver1@   |

> Change these immediately after first login.

---

## 7. Set Correct Permissions

```bash
sudo chown -R www-data:www-data /var/www/rapidrescue/storage
sudo chown -R www-data:www-data /var/www/rapidrescue/bootstrap/cache
sudo chown www-data:www-data /var/www/rapidrescue/database/database.sqlite

sudo chmod -R 775 /var/www/rapidrescue/storage
sudo chmod -R 775 /var/www/rapidrescue/bootstrap/cache
sudo chmod 664 /var/www/rapidrescue/database/database.sqlite
```

---

## 8. Configure Nginx

### 8a. Open Firewall Ports

On Ionos VPS, open these ports in the Ionos control panel firewall AND on the server:

```bash
sudo ufw allow 22      # SSH
sudo ufw allow 80      # HTTP
sudo ufw allow 443     # HTTPS
sudo ufw allow 8080    # Reverb WebSocket
sudo ufw enable
```

### 8b. Create Nginx Site Config

```bash
sudo nano /etc/nginx/sites-available/rapidrescue
```

Paste the following (replace `<your-domain.com>` with your actual domain):

```nginx
server {
    listen 80;
    server_name <your-domain.com> www.<your-domain.com>;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name <your-domain.com> www.<your-domain.com>;

    root /var/www/rapidrescue/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/<your-domain.com>/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/<your-domain.com>/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}

# ── Reverb WebSocket Proxy (WSS on port 8080) ────────────────────────────────
server {
    listen 8080 ssl;
    server_name <your-domain.com>;

    ssl_certificate     /etc/letsencrypt/live/<your-domain.com>/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/<your-domain.com>/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;

    location / {
        proxy_pass             http://127.0.0.1:8080;
        proxy_http_version     1.1;
        proxy_set_header       Upgrade $http_upgrade;
        proxy_set_header       Connection "Upgrade";
        proxy_set_header       Host $host;
        proxy_set_header       X-Real-IP $remote_addr;
        proxy_set_header       X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_read_timeout     60s;
        proxy_send_timeout     60s;
    }
}
```

> **Why port 8080 with SSL?**  
> Browsers on HTTPS pages require `wss://` (secure WebSocket).  
> Nginx listens on port 8080 with SSL and proxies to Reverb's plain HTTP on the same port internally.  
> The `.env` uses `REVERB_CLIENT_HOST=<your-domain>` and `REVERB_CLIENT_SCHEME=https` so browsers connect to `wss://<your-domain>:8080`.

Enable the site:
```bash
sudo ln -s /etc/nginx/sites-available/rapidrescue /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

## 9. Obtain SSL Certificate

> Do this BEFORE setting up the HTTPS Nginx config, or temporarily use the HTTP-only config.

```bash
# Point your domain's DNS A record to YOUR_VPS_IP first, then:
sudo certbot --nginx -d <your-domain.com> -d www.<your-domain.com>
```

Follow the prompts. Certbot will automatically update your Nginx config.

```bash
# Test auto-renewal
sudo certbot renew --dry-run
```

---

## 10. Set Up Supervisor for Reverb

Supervisor keeps the Reverb WebSocket server running continuously and restarts it if it crashes.

```bash
sudo nano /etc/supervisor/conf.d/reverb.conf
```

Paste:

```ini
[program:reverb]
process_name=%(program_name)s
command=php /var/www/rapidrescue/artisan reverb:start --host=127.0.0.1 --port=8080
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/supervisor/reverb.log
stopwaitsecs=10
```

Apply the configuration:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start reverb
sudo supervisorctl status
```

You should see `reverb    RUNNING` in the output.

---

## 11. Optimize Laravel for Production

```bash
cd /var/www/rapidrescue

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

---

## 12. Verify the Setup

| Check | Command / URL |
|-------|---------------|
| Nginx running | `sudo systemctl status nginx` |
| PHP-FPM running | `sudo systemctl status php8.2-fpm` |
| Reverb running | `sudo supervisorctl status reverb` |
| Reverb logs | `sudo tail -f /var/log/supervisor/reverb.log` |
| App live | Visit `https://<your-domain.com>` |
| WebSocket | Open browser console → should see `[Reverb] Admin real-time hub connected.` |

---

## 13. Updating the App

When you push new code:

```bash
cd /var/www/rapidrescue

git pull origin main

composer install --no-dev --optimize-autoloader

php artisan migrate --force

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

sudo supervisorctl restart reverb
sudo systemctl reload php8.2-fpm
```

---

## 14. Environment Variable Reference

| Variable | Where | Value on IONOS VPS |
|----------|-------|--------------------|
| `APP_ENV` | PHP | `production` |
| `APP_DEBUG` | PHP | `false` |
| `APP_URL` | PHP | `https://<your-domain.com>` |
| `REVERB_HOST` | PHP server (internal) | `127.0.0.1` |
| `REVERB_PORT` | PHP + Reverb | `8080` |
| `REVERB_SCHEME` | PHP server (internal) | `http` |
| `REVERB_CLIENT_HOST` | Browser (public) | `<your-domain.com>` |
| `REVERB_CLIENT_SCHEME` | Browser (public) | `https` |

---

## 15. Troubleshooting

### WebSocket won't connect
- Check Reverb is running: `sudo supervisorctl status reverb`
- Check port 8080 is open in Ionos control panel firewall
- Check Nginx config: `sudo nginx -t`
- Tail Reverb logs: `sudo tail -f /var/log/supervisor/reverb.log`

### 500 errors
- Check Laravel logs: `tail -f /var/www/rapidrescue/storage/logs/laravel.log`
- Ensure storage permissions: `sudo chown -R www-data:www-data storage bootstrap/cache`

### Messages not appearing in real-time (but WS is connected)
- Ensure `REVERB_HOST=127.0.0.1` (PHP internal connection)
- Ensure `REVERB_CLIENT_HOST=<your-domain>` (browser connection)
- Verify Reverb is actually running: `sudo supervisorctl status`

### SSL issues on port 8080
- Ensure the `listen 8080 ssl` Nginx server block has the correct certificate paths
- Verify cert exists: `sudo certbot certificates`

### Permission denied on SQLite
```bash
sudo chown www-data:www-data database/database.sqlite
sudo chmod 664 database/database.sqlite
sudo chown -R www-data:www-data database/
```
