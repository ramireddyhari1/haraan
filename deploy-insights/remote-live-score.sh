#!/usr/bin/env bash
# Live-score engine deploy: 11 files, no migration, no routes. Every guard runs BEFORE the
# first write, and the rollback archive is taken before extraction.
set -euo pipefail
AR=/root/haraan-live-score-deploy.tgz
EXPECT_MD5="$1"
APP=/var/www/haraan
TS=$(date -u +%Y%m%d-%H%M%S)

test -s "$AR" || { echo "FATAL: archive missing/empty"; exit 1; }
test "$(md5sum "$AR" | cut -d' ' -f1)" = "$EXPECT_MD5" || { echo "FATAL: md5 mismatch"; exit 1; }
tar tzf "$AR" > /root/live-score-list.txt
test "$(wc -l < /root/live-score-list.txt)" -eq 11 || { echo "FATAL: expected 11 entries"; cat /root/live-score-list.txt; exit 1; }
grep -qx 'app/Services/Scoring/TennisMachine.php' /root/live-score-list.txt || { echo "FATAL: marker absent"; exit 1; }

cd "$APP"
# Rollback: the 8 existing files as they are now (the 3 new ones are simply removed on rollback).
tar czf "/root/haraan-rollback-live-score-$TS.tgz" \
  app/Http/Controllers/Api/MatchesController.php app/Models/MatchEvent.php \
  app/Services/Insights/BasketballInsights.php app/Services/Insights/KabaddiInsights.php \
  app/Services/Insights/SportInsights.php app/Services/MatchEventRecorder.php \
  app/Services/SportScoreEngine.php app/Support/SportRules.php
echo "rollback: /root/haraan-rollback-live-score-$TS.tgz (+ rm -rf app/Services/Scoring)"

tar xzf "$AR" --no-same-owner -C "$APP"
chown root:root app/Services/Scoring
for f in $(cat /root/live-score-list.txt); do php -l "$f" > /dev/null || { echo "LINT FAIL $f"; exit 1; }; done

env HOME=/tmp COMPOSER_HOME=/tmp/composer composer dump-autoload --optimize --no-dev --working-dir="$APP" 2>&1 | tail -1
systemctl reload php8.3-fpm
find "$APP/app" -uid 197609 | head -3
echo "DEPLOYED $TS"
