# Server hardening — safe · fast · secure

Runbook for the Haraan backend host (nginx + php-fpm + SQLite + WhatsApp bridge + Reverb).
Application-layer rate limiting is already shipped (see `routes/api.php` + `AppServiceProvider::configureRateLimiters`).
The steps below are the **host / infra** layer and must be applied on the server.

---

## 1. Rate limiting at nginx (blocks floods before they reach PHP)

The Laravel `throttle:` limiters protect logic; nginx protects the box. Add to `http {}` in `nginx.conf`:

```nginx
limit_req_zone  $binary_remote_addr zone=api:10m   rate=10r/s;
limit_req_zone  $binary_remote_addr zone=otp:10m   rate=1r/s;
limit_conn_zone $binary_remote_addr zone=conn:10m;
```

In the site `server {}` block:

```nginx
location /api/ {
    limit_req  zone=api burst=20 nodelay;
    limit_conn conn 20;
    try_files $uri $uri/ /index.php?$query_string;
}

# OTP send is the costliest/most-abused path — clamp harder.
location ~ ^/api/auth/(email|whatsapp)/request$ {
    limit_req zone=otp burst=3 nodelay;
    try_files $uri /index.php?$query_string;
}
```

`limit_req_status 429;` keeps the status code consistent with the app layer.

## 2. Redis (move hot state off SQLite; queue the slow work)

```bash
sudo apt install -y redis-server
```
`/etc/redis/redis.conf`:
```
bind 127.0.0.1 ::1        # never expose to the internet
requirepass <STRONG_SECRET>
maxmemory 256mb
maxmemory-policy allkeys-lru
```
Then in the Laravel `.env`:
```env
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis        # only after a worker is running (see below)
REDIS_PASSWORD=<STRONG_SECRET>
```
Cache the expensive aggregations (leaderboards, /home/layout, /config) with short TTLs.
Note: `BookingNotifier` uses `defer()` and needs **no** worker. If you switch `QUEUE_CONNECTION`
to redis, run a worker or those deferred sends still fire post-response fine, but any real
`->onQueue()` jobs need: `php artisan queue:work --sleep=1 --tries=3` under supervisor/systemd.

## 3. TLS (unblocks camera check-in, Play Store, Google Sign-In)

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d haraan.app -d www.haraan.app
```
Then in `.env`: `APP_URL=https://haraan.app`, `SESSION_SECURE_COOKIE=true`, `APP_DEBUG=false`.

## 4. Firewall + SSH

```bash
sudo ufw default deny incoming && sudo ufw default allow outgoing
sudo ufw allow 22 && sudo ufw allow 80 && sudo ufw allow 443
sudo ufw enable
```
Redis (6379), Reverb, and the SQLite file must be bound to localhost / private only.
`/etc/ssh/sshd_config`: `PasswordAuthentication no`, `PermitRootLogin no` (key-only).
`sudo apt install fail2ban unattended-upgrades -y`.

## 5. Bridge endpoints — auth-gate them

`/api/send-media`, `/qr`, and resolve-by-code must require auth/host scope. An open
WhatsApp-send endpoint is a spam relay. Verify none are publicly reachable without a token.

## 6. Perf quick wins (per deploy)

```bash
php artisan config:cache route:cache view:cache event:cache
```
Enable OPcache in php-fpm; tune `pm.max_children` to host RAM; gzip/brotli + long cache
headers on `/build` assets.

---

### Bigger move (before real user growth)
SQLite serialises writes behind one lock. WAL + busy_timeout (already set in
`config/database.php`) buys headroom, but migrate the **main app DB to PostgreSQL** before
scaling. Keep the app stateless (sessions in Redis, uploads on R2) so a load balancer +
second node becomes a config change, not a rewrite.
