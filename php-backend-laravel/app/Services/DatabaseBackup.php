<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Throwable;

/**
 * A verified database backup, taken before anything destructive runs.
 *
 * SQLite (production today): `VACUUM INTO` writes a consistent snapshot through the live
 * connection — safe while the app is serving, and correct under WAL, where copying the file
 * would miss pages still sitting in the -wal file. The snapshot is then opened on its own,
 * must pass `PRAGMA integrity_check`, and must hold the same row counts as the source for
 * the tables a stats rebuild touches. A manifest (sha256 + counts) is written beside it.
 *
 * Any other driver: this class can't take the dump itself, so it verifies one the operator
 * made (it exists, isn't empty, and is recent) and refuses otherwise.
 */
final class DatabaseBackup
{
    /** Tables whose counts must match between the source and the backup. */
    public const VERIFIED_TABLES = [
        'users', 'live_matches', 'match_actions', 'match_events', 'player_match_stats',
        'player_career_batting', 'player_career_bowling', 'player_career_fielding',
    ];

    /** How old an operator-supplied dump may be. */
    public const MAX_EXTERNAL_AGE_HOURS = 24;

    /**
     * @return array{path: string, manifest: string, sha256: string, bytes: int, counts: array<string, int>}
     *
     * @throws RuntimeException when no verified backup could be produced
     */
    public function snapshot(string $directory, string $label): array
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            throw new RuntimeException(
                'Automatic backups are only supported on SQLite. Take a dump with your database tools and pass it with --backup-file.'
            );
        }

        if (! is_dir($directory) && ! @mkdir($directory, 0750, true) && ! is_dir($directory)) {
            throw new RuntimeException("Cannot create the backup directory {$directory}.");
        }
        if (! is_writable($directory)) {
            throw new RuntimeException("The backup directory {$directory} is not writable.");
        }

        $slug = preg_replace('/[^a-z0-9-]+/i', '-', $label) ?: 'backup';
        $path = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . 'haraan-' . $slug . '-' . now()->format('Ymd-His') . '.sqlite';
        if (file_exists($path)) {
            throw new RuntimeException("Refusing to overwrite an existing backup at {$path}.");
        }

        $source = $this->counts(DB::connection()->getPdo());

        try {
            DB::statement('VACUUM INTO ?', [$path]);
        } catch (Throwable $e) {
            @unlink($path);
            throw new RuntimeException('The database snapshot failed: ' . $e->getMessage(), 0, $e);
        }

        clearstatcache(true, $path);
        if (! is_file($path) || filesize($path) === 0) {
            throw new RuntimeException('The database snapshot was not written.');
        }

        $copy = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $integrity = (string) $copy->query('PRAGMA integrity_check')->fetchColumn();
        if (strtolower($integrity) !== 'ok') {
            throw new RuntimeException("The backup failed its integrity check: {$integrity}");
        }

        $copied = $this->counts($copy);
        $copy = null;
        foreach ($source as $table => $n) {
            if (($copied[$table] ?? -1) !== $n) {
                throw new RuntimeException("The backup's {$table} has " . ($copied[$table] ?? 'no') . " rows; the live database has {$n}.");
            }
        }

        $sha = hash_file('sha256', $path);
        $bytes = (int) filesize($path);
        $manifest = $path . '.json';
        file_put_contents($manifest, json_encode([
            'label' => $label,
            'created_at' => now()->toIso8601String(),
            'source' => (string) DB::connection()->getDatabaseName(),
            'sha256' => $sha,
            'bytes' => $bytes,
            'integrity_check' => 'ok',
            'row_counts' => $source,
            'restore' => 'Stop the app, replace the live database file with this one (and delete its -wal/-shm files), then start the app.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return ['path' => $path, 'manifest' => $manifest, 'sha256' => $sha, 'bytes' => $bytes, 'counts' => $source];
    }

    /**
     * Accept a dump the operator made themselves.
     *
     * @return array{path: string, sha256: string, bytes: int}
     */
    public function verifyExternal(string $path): array
    {
        clearstatcache(true, $path);
        if (! is_file($path)) {
            throw new RuntimeException("No backup file at {$path}.");
        }
        $bytes = (int) filesize($path);
        if ($bytes === 0) {
            throw new RuntimeException("The backup at {$path} is empty.");
        }
        $ageHours = (time() - (int) filemtime($path)) / 3600;
        if ($ageHours > self::MAX_EXTERNAL_AGE_HOURS) {
            throw new RuntimeException(sprintf(
                'The backup at %s is %.0f hours old; take a fresh one (at most %d hours) right before rebuilding.',
                $path, $ageHours, self::MAX_EXTERNAL_AGE_HOURS,
            ));
        }

        // A SQLite file offered as the backup must itself be a sound database.
        $head = (string) file_get_contents($path, false, null, 0, 16);
        if (str_starts_with($head, 'SQLite format 3')) {
            $copy = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $integrity = (string) $copy->query('PRAGMA integrity_check')->fetchColumn();
            if (strtolower($integrity) !== 'ok') {
                throw new RuntimeException("The backup failed its integrity check: {$integrity}");
            }
        }

        return ['path' => $path, 'sha256' => hash_file('sha256', $path), 'bytes' => $bytes];
    }

    /** @return array<string, int> */
    private function counts(PDO $pdo): array
    {
        $existing = array_flip($pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN));
        $out = [];
        foreach (self::VERIFIED_TABLES as $table) {
            if (isset($existing[$table])) {
                $out[$table] = (int) $pdo->query('SELECT COUNT(*) FROM "' . $table . '"')->fetchColumn();
            }
        }

        return $out;
    }
}
