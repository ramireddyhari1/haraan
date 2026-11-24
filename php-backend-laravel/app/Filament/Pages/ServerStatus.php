<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Support\BusinessClock;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Live server / infrastructure health for the ops team — the "is the box OK?" screen, so an
 * operator can see CPU, memory, disk and every service the app leans on from /control
 * without SSH. Polls itself every 10 s (wire:poll in the view).
 *
 * Every probe is defensive and cross-platform: the dev box is Windows, prod is Linux, so
 * anything that only exists on one (load average, /proc, redis) degrades to "—" rather
 * than throwing. Super-admins only. Reads only — nothing here mutates state.
 */
class ServerStatus extends Page
{
    protected string $view = 'filament.pages.server-status';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-server-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?string $title = 'Server status';

    protected static ?string $navigationLabel = 'Server status';

    protected static ?int $navigationSort = 90;

    /** @var array<int,array<string,mixed>> Host meters (CPU / memory / disk). */
    public array $meters = [];

    /** @var array<int,array<string,mixed>> One row per service the app depends on. */
    public array $services = [];

    /** @var array<string,string|null> Plain facts about the box: host, OS, uptime. */
    public array $facts = [];

    /** Time of the last probe (IST, H:i:s), shown in the header. */
    public ?string $checkedAt = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->refresh();
    }

    /** Polled by wire:poll — re-runs every probe. */
    public function refresh(): void
    {
        $this->meters = [
            $this->cpuMeter(),
            $this->memoryMeter(),
            $this->diskMeter(),
        ];

        $this->services = [
            $this->databaseRow(),
            $this->cacheRow(),
            $this->redisRow(),
            $this->queueRow(),
            $this->whatsappRow(),
            $this->laravelRow(),
            $this->phpRow(),
        ];

        $this->facts = [
            'host' => (string) (gethostname() ?: 'unknown host'),
            'os' => trim(php_uname('s') . ' ' . php_uname('r')),
            'uptime' => $this->uptime(),
        ];

        $this->checkedAt = BusinessClock::now()->format('H:i:s');
    }

    // ---------------------------------------------------------------------
    //  Host meters — label, icon, value, unit, detail, pct (0–100|null),
    //  ticks (threshold marks on the bar, in %), status.
    //  status ∈ ok | warn | down | idle
    // ---------------------------------------------------------------------

    private function cpuMeter(): array
    {
        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : false;
        if ($load === false) {
            return $this->meter('CPU load', 'line:cpu', '—', '', 'Load average is not reported on this OS', null, 'idle');
        }

        $cores = $this->cpuCount();
        $perCore = $cores ? $load[0] / $cores : $load[0];
        $pct = (int) min(100, round($perCore * 100));
        // Load per core > 1.0 means saturated.
        $status = $perCore >= 1.5 ? 'down' : ($perCore >= 0.9 ? 'warn' : 'ok');
        $detail = sprintf('load %.2f · %.2f · %.2f', $load[0], $load[1], $load[2])
            . ($cores ? " · {$cores} cores" : '');

        return $this->meter('CPU load', 'line:cpu', (string) $pct, '%', $detail, $pct, $status, [90]);
    }

    /** System RAM from /proc/meminfo. Uses MemAvailable, so page cache isn't counted as "used". */
    private function memoryMeter(): array
    {
        $info = @is_readable('/proc/meminfo') ? (string) @file_get_contents('/proc/meminfo') : '';
        preg_match('/^MemTotal:\s+(\d+)/m', $info, $t);
        preg_match('/^MemAvailable:\s+(\d+)/m', $info, $a);
        if (! $t || ! $a || (int) $t[1] <= 0) {
            return $this->meter('Memory', 'line:memory-stick', '—', '', 'System memory is not readable on this OS', null, 'idle');
        }

        $total = (int) $t[1] * 1024;
        $used = max(0, $total - (int) $a[1] * 1024);
        $pct = (int) round($used / $total * 100);
        $status = $pct >= 90 ? 'down' : ($pct >= 80 ? 'warn' : 'ok');

        $detail = $this->bytes($used) . ' of ' . $this->bytes($total);
        preg_match('/^SwapTotal:\s+(\d+)/m', $info, $st);
        preg_match('/^SwapFree:\s+(\d+)/m', $info, $sf);
        if ($st && $sf && (int) $st[1] > 0) {
            $detail .= ' · swap ' . $this->bytes(((int) $st[1] - (int) $sf[1]) * 1024) . ' used';
        }

        return $this->meter('Memory', 'line:memory-stick', (string) $pct, '%', $detail, $pct, $status, [80, 90]);
    }

    private function diskMeter(): array
    {
        $free = @disk_free_space(base_path());
        $total = @disk_total_space(base_path());
        if ($free === false || $total === false || $total <= 0) {
            return $this->meter('Disk', 'line:hard-drive', '—', '', 'Disk size is not readable here', null, 'idle');
        }

        $pct = (int) round((($total - $free) / $total) * 100);
        $status = $pct >= 90 ? 'down' : ($pct >= 80 ? 'warn' : 'ok');

        return $this->meter('Disk', 'line:hard-drive', (string) $pct, '%', $this->bytes((int) $free) . ' free of ' . $this->bytes((int) $total), $pct, $status, [80, 90]);
    }

    // ---------------------------------------------------------------------
    //  Service rows — name, icon, brand (colour key), detail, metric, status, note.
    // ---------------------------------------------------------------------

    private function databaseRow(): array
    {
        $driver = (string) config('database.connections.' . config('database.default') . '.driver', 'database');
        $brand = in_array($driver, ['mysql', 'mariadb'], true) ? 'mysql' : ($driver === 'sqlite' ? 'sqlite' : null);
        $icon = $brand ? "brand:{$brand}" : 'line:database';
        $label = ['sqlite' => 'SQLite', 'mysql' => 'MySQL', 'mariadb' => 'MariaDB', 'pgsql' => 'PostgreSQL'][$driver] ?? ucfirst($driver);

        try {
            $start = microtime(true);
            DB::connection()->select('select 1');
            $ms = (int) round((microtime(true) - $start) * 1000);

            $detail = $label;
            if ($driver === 'sqlite') {
                $path = DB::connection()->getConfig('database');
                if (is_string($path) && is_file($path)) {
                    $detail .= ' · ' . $this->bytes((int) filesize($path));
                }
                try {
                    $jm = strtoupper((string) (DB::connection()->select('PRAGMA journal_mode')[0]->journal_mode ?? ''));
                    if ($jm !== '') {
                        $detail .= " · {$jm} journal";
                    }
                } catch (\Throwable) {
                    // pragma unavailable — ignore
                }
            }

            return $this->row('Database', $icon, $brand, $detail, "{$ms} ms", $ms > 500 ? 'warn' : 'ok', $ms > 500 ? 'Slow' : null);
        } catch (\Throwable $e) {
            return $this->row('Database', $icon, $brand, $label . ' · ' . $this->short($e), null, 'down');
        }
    }

    private function cacheRow(): array
    {
        $store = (string) config('cache.default');
        $isRedis = $store === 'redis';

        try {
            $start = microtime(true);
            Cache::put('server_status:probe', '1', 5);
            $ok = Cache::get('server_status:probe') === '1';
            $ms = (int) round((microtime(true) - $start) * 1000);

            return $this->row('Cache', $isRedis ? 'brand:redis' : 'line:layers', $isRedis ? 'redis' : null,
                ucfirst($store) . ' store · write and read back', "{$ms} ms", $ok ? 'ok' : 'warn', $ok ? null : 'Not writable');
        } catch (\Throwable $e) {
            return $this->row('Cache', $isRedis ? 'brand:redis' : 'line:layers', $isRedis ? 'redis' : null,
                ucfirst($store) . ' store · ' . $this->short($e), null, 'down');
        }
    }

    private function redisRow(): array
    {
        $usesRedis = in_array('redis', [config('cache.default'), config('session.driver'), config('queue.default')], true);

        if (! $usesRedis && ! extension_loaded('redis') && ! class_exists(\Predis\Client::class)) {
            return $this->row('Redis', 'brand:redis', 'redis', 'Not used — cache, sessions and queue run without it', null, 'idle');
        }

        try {
            $start = microtime(true);
            $pong = app('redis')->connection()->ping();
            $ms = (int) round((microtime(true) - $start) * 1000);
            $ok = $pong === true || (is_string($pong) && str_contains($pong, 'PONG'));

            return $this->row('Redis', 'brand:redis', 'redis', $ok ? 'PING answered PONG' : 'No PONG reply', "{$ms} ms", $ok ? 'ok' : 'warn');
        } catch (\Throwable $e) {
            // Configured to use redis but can't reach it = real problem; otherwise informational.
            return $this->row('Redis', 'brand:redis', 'redis', $this->short($e), null, $usesRedis ? 'down' : 'idle');
        }
    }

    private function queueRow(): array
    {
        $conn = (string) config('queue.default');
        $failed = 0;
        $pending = null;

        try {
            if (Schema::hasTable('failed_jobs')) {
                $failed = (int) DB::table('failed_jobs')->count();
            }
            if ($conn === 'database' && Schema::hasTable('jobs')) {
                $pending = (int) DB::table('jobs')->count();
            }
        } catch (\Throwable) {
            // tables missing — leave as defaults
        }

        $detail = ucfirst($conn) . ' driver' . ($conn === 'sync' ? ' · jobs run inline' : '');

        return $this->row(
            'Queue',
            'line:list-ordered',
            null,
            $detail,
            $pending !== null ? "{$pending} waiting" : null,
            $failed > 0 ? 'warn' : 'ok',
            $failed > 0 ? "{$failed} failed" : null,
        );
    }

    private function whatsappRow(): array
    {
        // WhatsApp goes through Meta's Cloud API — report config health rather than pinging a local service.
        $configured = app(\App\Services\WhatsAppService::class)->isConfigured();
        $enabled = filter_var(config('services.whatsapp.enabled', false), FILTER_VALIDATE_BOOLEAN);
        $from = (string) config('services.whatsapp.phone_number_id');

        if ($configured && $enabled) {
            return $this->row('WhatsApp', 'brand:whatsapp', 'whatsapp', 'Meta Cloud API' . ($from ? ' · number id ' . $from : ''), null, 'ok', 'Sending');
        }
        if ($configured) {
            return $this->row('WhatsApp', 'brand:whatsapp', 'whatsapp', 'Meta Cloud API · credentials set, META_WHATSAPP_ENABLED is off', null, 'warn', 'Switched off');
        }

        return $this->row('WhatsApp', 'brand:whatsapp', 'whatsapp', 'Set META_WHATSAPP_PHONE_NUMBER_ID and the access token', null, 'idle', 'Not set up');
    }

    private function laravelRow(): array
    {
        $debug = (bool) config('app.debug');
        $env = (string) app()->environment();
        // Debug on in production leaks stack traces to visitors — flag it.
        $risky = $debug && $env === 'production';

        return $this->row('Laravel', 'brand:laravel', 'laravel', $env . ' · debug ' . ($debug ? 'on' : 'off'),
            'v' . app()->version(), $risky ? 'warn' : 'ok', $risky ? 'Debug is on' : null);
    }

    private function phpRow(): array
    {
        $opcacheOn = false;
        if (function_exists('opcache_get_status')) {
            try {
                $s = @opcache_get_status(false);
                $opcacheOn = is_array($s) && ($s['opcache_enabled'] ?? false);
            } catch (\Throwable) {
                // ignore
            }
        }

        $limitRaw = (string) ini_get('memory_limit');
        $limit = $this->parseBytes($limitRaw) > 0 ? $limitRaw : 'no limit';
        $detail = PHP_SAPI . ' · OPcache ' . ($opcacheOn ? 'on' : 'off')
            . ' · this request ' . $this->bytes(memory_get_peak_usage(true)) . ' / ' . $limit;

        return $this->row('PHP', 'brand:php', 'php', $detail, 'v' . PHP_VERSION, 'ok');
    }

    /** "3d 4h" from /proc/uptime; null where the OS doesn't expose it. */
    private function uptime(): ?string
    {
        $raw = @is_readable('/proc/uptime') ? (string) @file_get_contents('/proc/uptime') : '';
        $secs = (int) (float) strtok($raw, ' ');
        if ($secs <= 0) {
            return null;
        }
        $d = intdiv($secs, 86400);
        $h = intdiv($secs % 86400, 3600);
        $m = intdiv($secs % 3600, 60);

        return $d > 0 ? "{$d}d {$h}h" : ($h > 0 ? "{$h}h {$m}m" : "{$m}m");
    }

    // ---------------------------------------------------------------------
    //  Helpers
    // ---------------------------------------------------------------------

    /**
     * @param  int[]  $ticks  Threshold marks drawn on the bar, in percent.
     * @return array<string,mixed>
     */
    private function meter(string $label, string $icon, string $value, string $unit, string $detail, ?int $pct, string $status, array $ticks = []): array
    {
        return compact('label', 'icon', 'value', 'unit', 'detail', 'pct', 'status', 'ticks');
    }

    /**
     * @param  string|null  $note  Overrides the default status word (e.g. "3 failed").
     * @return array<string,mixed>
     */
    private function row(string $name, string $icon, ?string $brand, string $detail, ?string $metric, string $status, ?string $note = null): array
    {
        return compact('name', 'icon', 'brand', 'detail', 'metric', 'status', 'note');
    }

    private function cpuCount(): ?int
    {
        if (is_file('/proc/cpuinfo')) {
            $n = preg_match_all('/^processor\s*:/m', (string) @file_get_contents('/proc/cpuinfo'));
            if ($n > 0) {
                return $n;
            }
        }
        $env = (int) getenv('NUMBER_OF_PROCESSORS'); // Windows

        return $env > 0 ? $env : null;
    }

    /** Parse a php.ini shorthand size (e.g. "128M", "1G", "-1") into bytes; ≤0 means unlimited. */
    private function parseBytes(string $val): int
    {
        $val = trim($val);
        if ($val === '' || $val === '-1') {
            return -1;
        }
        $num = (int) $val;

        return match (strtolower(substr($val, -1))) {
            'g' => $num * 1024 * 1024 * 1024,
            'm' => $num * 1024 * 1024,
            'k' => $num * 1024,
            default => $num,
        };
    }

    private function bytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $n = (float) $bytes;
        while ($n >= 1024 && $i < count($units) - 1) {
            $n /= 1024;
            $i++;
        }

        return ($n >= 10 || $i === 0 ? number_format($n) : number_format($n, 1)) . ' ' . $units[$i];
    }

    private function short(\Throwable $e): string
    {
        $msg = trim($e->getMessage());

        return mb_strlen($msg) > 90 ? mb_substr($msg, 0, 87) . '…' : $msg;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Check now')
                ->icon('heroicon-m-arrow-path')
                ->color('gray')
                ->action(function (): void {
                    $this->refresh();
                    Notification::make()->title('Checked just now')->success()->send();
                }),
        ];
    }
}
