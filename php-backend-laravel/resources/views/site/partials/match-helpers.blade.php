@php
/*
 | Shared PHP helpers for the web ActionBoard match-detail pages. Ported from the
 | app (CrexUI.teamShortCode / sanitizeScore, ball colouring, stat maths) so the web
 | tabs render identically. Guarded with function_exists so the partial can be
 | @included by both the layout and each tab view without redeclaration errors.
 */

if (!function_exists('hrn_team_code')) {
    /** Compact hero code from a raw team name (mirrors CrexUI.teamShortCode). */
    function hrn_team_code(string $raw): string
    {
        $name = trim($raw);
        if ($name === '') return '?';
        if (mb_strlen($name) <= 4 && $name === mb_strtoupper($name)) return $name;

        $words = preg_split('/[\s\-_]+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($words) >= 2) {
            return mb_strtoupper(implode('', array_map(fn ($w) => mb_substr($w, 0, 1), array_slice($words, 0, 4))));
        }
        $w = strtolower(preg_replace('/[^a-z0-9]/i', '', $words[0] ?? ''));
        $suffixes = ['palle','palli','pally','halli','nagaram','nagar','puram','palem','valasa','cherla','konda','gudem','peta','pet','wada','vada','giri','puri','pur','bad'];
        foreach ($suffixes as $suf) {
            if (strlen($w) > strlen($suf) + 1 && str_ends_with($w, $suf)) {
                $stem = substr($w, 0, -strlen($suf));
                return mb_substr(mb_strtoupper($stem[0] . $suf[0]), 0, 3);
            }
        }
        return mb_strtoupper(mb_substr($w, 0, 3));
    }
}

if (!function_exists('hrn_sanitize_score')) {
    /** Clamp wickets to 0..10 so a bad row can't render "504/30". */
    function hrn_sanitize_score(string $raw): string
    {
        $slash = strpos($raw, '/');
        if ($slash === false) return $raw;
        $runs = trim(substr($raw, 0, $slash));
        $rest = trim(substr($raw, $slash + 1));
        if (!preg_match('/^(\d+)(.*)$/', $rest, $m)) return $raw;
        $wkts = min((int) $m[1], 10);
        return "{$runs}/{$wkts}{$m[2]}";
    }
}

if (!function_exists('hrn_ball_kind')) {
    /** Colour bucket for a ball chip: six | four | wicket | extra | dot | run. */
    function hrn_ball_kind($ball): string
    {
        $b = strtoupper(trim((string) $ball));
        return match (true) {
            $b === '6' => 'six',
            $b === '4' => 'four',
            $b === 'W' => 'wicket',
            in_array($b, ['WD','NB','B','LB'], true) => 'extra',
            $b === '0' || $b === '•' || $b === '' => 'dot',
            default => 'run',
        };
    }
}

if (!function_exists('hrn_initial')) {
    function hrn_initial(?string $name): string
    {
        $n = trim((string) $name);
        return $n === '' ? '?' : mb_strtoupper(mb_substr($n, 0, 1));
    }
}

if (!function_exists('hrn_sr')) {
    function hrn_sr(int $runs, int $balls): string
    {
        return $balls > 0 ? number_format($runs / $balls * 100, 1) : '0.0';
    }
}

if (!function_exists('hrn_econ')) {
    function hrn_econ(int $runs, int $balls): string
    {
        return $balls > 0 ? number_format($runs / $balls * 6, 2) : '0.00';
    }
}

if (!function_exists('hrn_overs_from_balls')) {
    function hrn_overs_from_balls(int $balls): string
    {
        return intdiv($balls, 6) . '.' . ($balls % 6);
    }
}

if (!function_exists('hrn_mono_color')) {
    /** Deterministic vivid colour from a name — so monogram avatars/crests read as a
     *  real, varied roster instead of a wall of identical grey placeholder circles. */
    function hrn_mono_color(?string $name): string
    {
        $palette = ['#2563EB','#16A34A','#7C3AED','#DB2777','#EA580C','#0891B2','#D97706','#0D9488','#4F46E5','#BE123C'];
        $key = mb_strtolower(trim((string) $name));
        return $palette[abs(crc32($key)) % count($palette)];
    }
}

if (!function_exists('hrn_parse_rb')) {
    /** "34 (19)" -> ['runs' => 34, 'balls' => 19]. */
    function hrn_parse_rb(?string $s): array
    {
        if (preg_match('/(\d+)\s*\((\d+)\)/', (string) $s, $m)) {
            return ['runs' => (int) $m[1], 'balls' => (int) $m[2]];
        }
        return ['runs' => 0, 'balls' => 0];
    }
}
@endphp
