<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Reads a clip's duration out of the container itself.
 *
 * The server has to know how long a video is before it accepts it, and it cannot take the
 * phone's word for it: durationMs arrives in the same multipart body as the file, so a
 * client that lies about it gets a longer clip past the limit. The duration therefore has
 * to be read from the bytes.
 *
 * There is no ffprobe on this machine and no media package in composer, and adding either
 * for one number would be a dependency the whole deployment then carries. An MP4 states
 * its own duration in the `mvhd` box, in the first few kilobytes, and reading it is a
 * short walk down the box tree — so that is what this does.
 *
 * It returns null rather than guessing. A container it cannot parse is a container whose
 * length is unknown, and the caller is expected to refuse it rather than hope: silently
 * accepting an unmeasurable clip is exactly how a 30-second video ends up in a pipeline
 * built for 10.
 *
 * Handles MP4/MOV/3GP (ISO base media). WebM is a different container entirely and comes
 * back null, which is honest — it is not in the upload allowlist.
 */
class Mp4Probe
{
    /** No sane `mvhd` is deeper than this; a file that is has something else wrong. */
    private const MAX_DEPTH = 6;

    /** Read at most this much looking for moov. It is normally at one end or the other. */
    private const SCAN_LIMIT = 8 * 1024 * 1024;

    /**
     * Duration in milliseconds, or null when it cannot be determined.
     */
    public static function durationMs(string $absolutePath): ?int
    {
        if (! is_readable($absolutePath)) {
            return null;
        }
        $handle = @fopen($absolutePath, 'rb');
        if ($handle === false) {
            return null;
        }

        try {
            $size = (int) filesize($absolutePath);

            return self::findMvhd($handle, 0, $size, 0);
        } catch (\Throwable) {
            // A truncated or malformed file reads as unknown, never as zero — zero would
            // sail through a "must be under ten seconds" check.
            return null;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Walk the box tree looking for moov → mvhd.
     *
     * @param  resource  $handle
     */
    private static function findMvhd($handle, int $offset, int $end, int $depth): ?int
    {
        if ($depth > self::MAX_DEPTH || $offset >= $end) {
            return null;
        }

        while ($offset < $end) {
            if (fseek($handle, $offset) !== 0) {
                return null;
            }
            $header = fread($handle, 8);
            if ($header === false || strlen($header) < 8) {
                return null;
            }

            $boxSize = unpack('N', substr($header, 0, 4))[1] ?? 0;
            $type = substr($header, 4, 4);
            $headerLength = 8;

            if ($boxSize === 1) {
                // 64-bit size, carried in the eight bytes after the type.
                $large = fread($handle, 8);
                if ($large === false || strlen($large) < 8) {
                    return null;
                }
                $high = unpack('N', substr($large, 0, 4))[1];
                $low = unpack('N', substr($large, 4, 4))[1];
                $boxSize = ($high << 32) | $low;
                $headerLength = 16;
            } elseif ($boxSize === 0) {
                // Runs to the end of the file.
                $boxSize = $end - $offset;
            }

            if ($boxSize < $headerLength) {
                return null;
            }

            if ($type === 'mvhd') {
                return self::readMvhd($handle, $offset + $headerLength);
            }

            // moov holds mvhd; the others can contain moov on files written oddly.
            if (in_array($type, ['moov', 'trak', 'mdia'], true)) {
                $found = self::findMvhd(
                    $handle,
                    $offset + $headerLength,
                    min($offset + $boxSize, $end),
                    $depth + 1,
                );
                if ($found !== null) {
                    return $found;
                }
            }

            $offset += $boxSize;
            if ($depth === 0 && $offset > self::SCAN_LIMIT && $offset < $end) {
                // Top level only: mdat can be gigabytes, and seeking past it is free, but
                // a file whose moov is nowhere near either end is not one we can cheaply
                // read. Keep walking — fseek does not read the payload.
                continue;
            }
        }

        return null;
    }

    /**
     * mvhd: version byte, three flag bytes, then times. Timescale is ticks per second and
     * duration is in those ticks, so the milliseconds are duration / timescale * 1000.
     *
     * @param  resource  $handle
     */
    private static function readMvhd($handle, int $offset): ?int
    {
        if (fseek($handle, $offset) !== 0) {
            return null;
        }
        $version = fread($handle, 1);
        if ($version === false || $version === '') {
            return null;
        }
        // Skip the three flag bytes.
        fread($handle, 3);

        if (ord($version) === 1) {
            // 64-bit: creation and modification are 8 bytes each.
            fread($handle, 16);
            $timescaleRaw = fread($handle, 4);
            $durationRaw = fread($handle, 8);
            if ($timescaleRaw === false || $durationRaw === false
                || strlen($timescaleRaw) < 4 || strlen($durationRaw) < 8) {
                return null;
            }
            $timescale = unpack('N', $timescaleRaw)[1];
            $high = unpack('N', substr($durationRaw, 0, 4))[1];
            $low = unpack('N', substr($durationRaw, 4, 4))[1];
            $duration = ($high << 32) | $low;
        } else {
            // 32-bit: creation and modification are 4 bytes each.
            fread($handle, 8);
            $timescaleRaw = fread($handle, 4);
            $durationRaw = fread($handle, 4);
            if ($timescaleRaw === false || $durationRaw === false
                || strlen($timescaleRaw) < 4 || strlen($durationRaw) < 4) {
                return null;
            }
            $timescale = unpack('N', $timescaleRaw)[1];
            $duration = unpack('N', $durationRaw)[1];
        }

        if ($timescale <= 0 || $duration <= 0) {
            return null;
        }

        // 0xFFFFFFFF is the conventional "unknown" duration. Treated as unknown, not as
        // an absurdly long clip that would be refused for the wrong reason.
        if ($duration === 0xFFFFFFFF) {
            return null;
        }

        return (int) round($duration / $timescale * 1000);
    }
}
