<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Live server / infrastructure health for the ops team — the "is the box OK?" screen, so an
 * operator can see database, cache, Redis, queue, disk, memory, CPU load and the WhatsApp
 * bridge from /control without SSH. Polls itself every few seconds (wire:poll in the view).
 *
 * Every probe is defensive and cross-platform: the dev box is Windows, prod is Linux, so
 * anything that only exists on one (load average, /proc, redis) degrades to "n/a" rather
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

    /** @var array<int,array<string,mixed>> Health cards rendered by the view. */
    public array $cards = [];

    /** ISO timestamp of the last probe, shown in the header. */
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
        $this->cards = [
            $this->databaseCard(),
            $this->cacheCard(),
            $this->redisCard(),
            $this->queueCard(),
            $this->diskCard(),
            $this->memoryCard(),
            $this->cpuCard(),
            $this->runtimeCard(),
            $this->bridgeCard(),
        ];

        // Tag each card with a section so the view can group them into
        // Data & cache / Host resources / Services rather than one flat grid.
        $groups = [
            'Database' => 'data', 'Cache' => 'data', 'Redis' => 'data', 'Queue' => 'data',
            'Disk' => 'host', 'PHP memory' => 'host', 'CPU load' => 'host',
            'Runtime' => 'services', 'WhatsApp bridge' => 'services',
        ];
        foreach ($this->cards as &$card) {
            $card['group'] = $groups[$card['title']] ?? 'data';
        }
        unset($card);

        $this->checkedAt = now()->toDateTimeString();
    }

    // ---------------------------------------------------------------------
    //  Probes — each returns a card: title, value, sub, icon, status.
    //  status ∈ ok | warn | down | idle  (drives the colour in the view).
    // ---------------------------------------------------------------------

    private function databaseCard(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->select('select 1');
            $ms = (int) round((microtime(true) - $start) * 1000);

            $driver = DB::connection()->getDriverName();
            $sub = "{$driver} · responded in {$ms}ms";

            if ($driver === 'sqlite') {
                $path = DB::connection()->getConfig('database');
                if (is_string($path) && is_file($path)) {
                    $sub .= ' · ' . $this->bytes((int) filesize($path));
                }
                try {
                    $mode = DB::connection()->select('PRAGMA journal_mode');
                    $jm = strtoupper((string) ($mode[0]->journal_mode ?? ''));
                    if ($jm !== '') {
                        $sub .= " · {$jm}";
                    }
                } catch (\Throwable) {
                    // pragma unavailable — ignore
                }
            }

            return $this->card('Database', 'Connected', $sub, 'heroicon-o-circle-stack', $ms > 500 ? 'warn' : 'ok');
        } catch (\Throwable $e) {
            return $this->card('Database', 'Unreachable', $this->short($e), 'heroicon-o-circle-stack', 'down');
        }
    }

    private function cacheCard(): array
    {
        $store = config('cache.default');
        try {
            $key = 'server_status:probe';
            Cache::put($key, '1', 5);
            $ok = Cache::get($key) === '1';

            return $this->card(
                'Cache',
                $ok ? 'Writable' : 'Not writable',
                'store: ' . $store,
                'heroicon-o-bolt',
                $ok ? 'ok' : 'warn',
            );
        } catch (\Throwable $e) {
            return $this->card('Cache', 'Error', 'store: ' . $store . ' · ' . $this->short($e), 'heroicon-o-bolt', 'down');
        }
    }

    private function redisCard(): array
    {
        $usesRedis = in_array('redis', [config('cache.default'), config('session.driver'), config('queue.default')], true);

        if (! $usesRedis && ! extension_loaded('redis') && ! class_exists(\Predis\Client::class)) {
            return $this->card('Redis', 'Not configured', 'App uses file/database drivers', 'heroicon-o-server', 'idle');
        }

        try {
            $start = microtime(true);
            $pong = app('redis')->connection()->ping();
            $ms = (int) round((microtime(true) - $start) * 1000);
            $ok = $pong === true || $pong === 'PONG' || $pong === '+PONG' || (is_string($pong) && str_contains($pong, 'PONG'));

            return $this->card('Redis', $ok ? 'Connected' : 'No PONG', "responded in {$ms}ms", 'heroicon-o-server', $ok ? 'ok' : 'warn');
        } catch (\Throwable $e) {
            // Configured to use redis but can't reach it = real problem; otherwise informational.
            return $this->card('Redis', 'Unreachable', $this->short($e), 'heroicon-o-server', $usesRedis ? 'down' : 'idle');
        }
    }

    private function queueCard(): array
    {
        $conn = config('queue.default');
        $failed = 0;
        $pending = null;

        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('failed_jobs')) {
                $failed = (int) DB::table('failed_jobs')->count();
            }
            if ($conn === 'database' && \Illuminate\Support\Facades\Schema::hasTable('jobs')) {
                $pending = (int) DB::table('jobs')->count();
            }
        } catch (\Throwable) {
            // tables missing — leave as defaults
        }

        $sub = 'driver: ' . $conn;
        if ($pending !== null) {
            $sub .= " · {$pending} pending";
        }
        if ($conn === 'sync') {
            $sub .= ' · runs inline';
        }

        $status = $failed > 0 ? 'warn' : 'ok';
        $value = $failed > 0 ? "{$failed} failed jobs" : 'Healthy';

        return $this->card('Queue', $value, $sub, 'heroicon-o-queue-list', $status);
    }

    private function diskCard(): array
    {
        try {
            $free = @disk_free_space(base_path());
            $total = @disk_total_space(base_path());
            if ($free === false || $total === false || $total <= 0) {
                return $this->card('Disk', 'n/a', 'Unavailable on this host', 'heroicon-o-inbox-stack', 'idle');
            }

            $usedPct = (int) round((($total - $free) / $total) * 100);
            $status = $usedPct >= 90 ? 'down' : ($usedPct >= 80 ? 'warn' : 'ok');

            return $this->card(
                'Disk',
                $usedPct . '% used',
                $this->bytes((int) $free) . ' free of ' . $this->bytes((int) $total),
                'heroicon-o-inbox-stack',
                $status,
                $usedPct,
            );
        } catch (\Throwable $e) {
            return $this->card('Disk', 'n/a', $this->short($e), 'heroicon-o-inbox-stack', 'idle');
        }
    }

    private function memoryCard(): array
    {
        $used = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);
        $limitRaw = (string) ini_get('memory_limit');
        $limitBytes = $this->parseBytes($limitRaw);

        // Only a finite limit (-1 = unlimited on many prod fpm pools) yields a meaningful bar.
        $meter = null;
        $status = 'ok';
        if ($limitBytes > 0) {
            $meter = (int) min(100, round($used / $limitBytes * 100));
            $status = $meter >= 90 ? 'down' : ($meter >= 75 ? 'warn' : 'ok');
        }
        $limitLabel = $limitBytes > 0 ? $limitRaw : 'unlimited';

        return $this->card(
            'PHP memory',
            $this->bytes($used),
            'peak ' . $this->bytes($peak) . ' · limit ' . $limitLabel,
            'heroicon-o-cpu-chip',
            $status,
            $meter,
        );
    }

    private function cpuCard(): array
    {
        if (! function_exists('sys_getloadavg')) {
            return $this->card('CPU load', 'n/a', 'Not available on this platform', 'heroicon-o-cpu-chip', 'idle');
        }

        $load = sys_getloadavg();
        if ($load === false) {
            return $this->card('CPU load', 'n/a', 'Unavailable', 'heroicon-o-cpu-chip', 'idle');
        }

        $cores = $this->cpuCount();
        $one = round($load[0], 2);
        $sub = sprintf('5m %.2f · 15m %.2f', $load[1], $load[2]);
        if ($cores !== null) {
            $sub .= " · {$cores} cores";
        }

        // Load per core > 1.0 means saturated.
        $perCore = $cores !== null && $cores > 0 ? $load[0] / $cores : $load[0];
        $status = $perCore >= 1.5 ? 'down' : ($perCore >= 0.9 ? 'warn' : 'ok');
        $meter = (int) min(100, round($perCore * 100));

        return $this->card('CPU load', (string) $one, $sub, 'heroicon-o-signal', $status, $meter);
    }

    private function runtimeCard(): array
    {
        $opcache = function_exists('opcache_get_status');
        $opcacheOn = false;
        if ($opcache) {
            try {
                $s = @opcache_get_status(false);
                $opcacheOn = is_array($s) && ($s['opcache_enabled'] ?? false);
            } catch (\Throwable) {
                // ignore
            }
        }

        $sub = 'Laravel ' . app()->version()
            . ' · ' . (config('app.debug') ? 'DEBUG on' : 'prod')
            . ' · OPcache ' . ($opcacheOn ? 'on' : 'off');

        // Debug on in production is a real risk; flag it.
        $status = (config('app.debug') && app()->environment('production')) ? 'warn' : 'ok';

        return $this->card('Runtime', 'PHP ' . PHP_VERSION, $sub, 'heroicon-o-command-line', $status);
    }

    private function bridgeCard(): array
    {
        try {
            $res = Http::timeout(5)->get($this->bridgeBase() . '/status');
            if ($res->successful()) {
                $ready = (bool) $res->json('ready');

                return $this->card(
                    'WhatsApp bridge',
                    $ready ? 'Linked' : 'Awaiting scan',
                    $ready ? 'OTP + ticket delivery live' : 'Session dropped — re-link on WhatsApp / OTP page',
                    'heroicon-o-chat-bubble-left-right',
                    $ready ? 'ok' : 'warn',
                );
            }
        } catch (\Throwable) {
            // fall through
        }

        return $this->card('WhatsApp bridge', 'Unreachable', 'Bridge not responding on ' . $this->bridgeBase(), 'heroicon-o-chat-bubble-left-right', 'down');
    }

    // ---------------------------------------------------------------------
    //  Helpers
    // ---------------------------------------------------------------------

    /**
     * @param  int|null  $meter  0–100 fill for a progress bar (disk/mem/cpu); null = no bar.
     * @return array<string,mixed>
     */
    private function card(string $title, string $value, string $sub, string $icon, string $status, ?int $meter = null): array
    {
        return compact('title', 'value', 'sub', 'icon', 'status', 'meter');
    }

    private function bridgeBase(): string
    {
        $url = config('services.whatsapp.bridge_url', 'http://localhost:8090/api/send-message');

        return preg_replace('#/api/send-message/?$#', '', (string) $url) ?: 'http://localhost:8090';
    }

    private function cpuCount(): ?int
    {
        if (is_file('/proc/cpuinfo')) {
            $n = substr_count((string) @file_get_contents('/proc/cpuinfo'), 'processor');
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
        $unit = strtolower(substr($val, -1));
        $num = (int) $val;

        return match ($unit) {
            'g' => $num * 1024 * 1024 * 1024,
            'm' => $num * 1024 * 1024,
            'k' => $num * 1024,
            default => (int) $val,
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

        return mb_strlen($msg) > 80 ? mb_substr($msg, 0, 77) . '…' : $msg;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-m-arrow-path')
                ->action(function (): void {
                    $this->refresh();
                    Notification::make()->title('Refreshed')->success()->send();
                }),
        ];
    }
}
