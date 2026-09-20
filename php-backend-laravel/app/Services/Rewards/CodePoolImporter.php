<?php

declare(strict_types=1);

namespace App\Services\Rewards;

use App\Models\AdminAction;
use App\Models\RewardCode;
use App\Models\RewardCodePool;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Imports sponsor codes into a pool: one per line (or the first column of a CSV). Codes are
 * encrypted at rest; duplicates (within the file or the pool) are skipped by their HMAC. The
 * audit log records counts only — a code never appears in it.
 */
final class CodePoolImporter
{
    public const MAX_PER_IMPORT = 20000;

    /** @return array{added: int, duplicates: int, invalid: int} */
    public function import(RewardCodePool $pool, string $text): array
    {
        $added = 0;
        $duplicates = 0;
        $invalid = 0;
        $seen = [];

        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        foreach (array_slice($lines, 0, self::MAX_PER_IMPORT) as $line) {
            $code = trim((string) str_getcsv($line)[0], " \t\"'");
            if ($code === '' || strcasecmp($code, 'code') === 0) {
                continue;
            }
            if (mb_strlen($code) > 120 || ! preg_match('/^[\x21-\x7E]+$/', $code)) {
                $invalid++;

                continue;
            }

            $hash = RewardCode::hashOf($code);
            if (isset($seen[$hash])) {
                $duplicates++;

                continue;
            }
            $seen[$hash] = true;

            try {
                RewardCode::query()->create(['pool_id' => $pool->id, 'code' => $code, 'code_hash' => $hash]);
                $added++;
            } catch (UniqueConstraintViolationException) {
                $duplicates++;
            }
        }

        AdminAction::log('reward_codes.imported', [
            'pool_id' => $pool->id, 'pool' => $pool->name, 'added' => $added, 'duplicates' => $duplicates, 'invalid' => $invalid,
        ], $pool);

        return ['added' => $added, 'duplicates' => $duplicates, 'invalid' => $invalid];
    }
}
