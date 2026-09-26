<?php

declare(strict_types=1);

namespace App\Support;

/**
 * What an inbound WhatsApp message says, whatever shape the provider sent it in:
 * typed text, a tapped list row / reply button (its id), or a shared location.
 *
 * Meta's shape is documented; MSG91 forwards something close to it but nests it
 * differently depending on the event, so {@see fromAny()} searches the payload for
 * the known keys rather than trusting one path.
 */
final class WhatsAppInbound
{
    /**
     * @param  array<string, mixed>  $message  one entry of Meta's `messages[]`
     * @return array{text: string, extra: array{reply_id?: string, location?: array{lat: float, lng: float}}}
     */
    public static function fromMeta(array $message): array
    {
        return match ((string) ($message['type'] ?? 'text')) {
            'location' => self::location($message['location'] ?? []),
            'interactive' => self::reply(
                $message['interactive']['list_reply'] ?? $message['interactive']['button_reply'] ?? [],
            ),
            // A quick-reply button on a template.
            'button' => ['text' => trim((string) ($message['button']['text'] ?? '')), 'extra' => array_filter(['reply_id' => $message['button']['payload'] ?? null])],
            default => ['text' => trim((string) ($message['text']['body'] ?? '')), 'extra' => []],
        };
    }

    /**
     * @param  array<string, mixed>  $payload  an MSG91 inbound event (content may be a JSON string)
     * @return array{text: string, extra: array{reply_id?: string, location?: array{lat: float, lng: float}}}
     */
    public static function fromAny(array $payload): array
    {
        $tree = self::decodeStrings($payload);

        if (($loc = self::find($tree, fn ($n) => isset($n['latitude'], $n['longitude']))) !== null) {
            return self::location($loc);
        }

        if (($reply = self::find($tree, fn ($n, $k) => in_array($k, ['list_reply', 'button_reply'], true) && isset($n['id']))) !== null) {
            return self::reply($reply);
        }

        return ['text' => '', 'extra' => []];
    }

    /** @param array<string, mixed> $loc */
    private static function location(array $loc): array
    {
        if (! is_numeric($loc['latitude'] ?? null) || ! is_numeric($loc['longitude'] ?? null)) {
            return ['text' => '', 'extra' => []];
        }

        $label = trim(implode(', ', array_filter([(string) ($loc['name'] ?? ''), (string) ($loc['address'] ?? '')])));

        return [
            'text' => 'Shared a location'.($label !== '' ? ": {$label}" : ''),
            'extra' => ['location' => ['lat' => (float) $loc['latitude'], 'lng' => (float) $loc['longitude']]],
        ];
    }

    /** @param array<string, mixed> $reply */
    private static function reply(array $reply): array
    {
        return [
            'text' => trim((string) ($reply['title'] ?? '')),
            'extra' => array_filter(['reply_id' => isset($reply['id']) ? (string) $reply['id'] : null]),
        ];
    }

    /** JSON-in-a-string fields (MSG91's `content`) become arrays so they can be searched. */
    private static function decodeStrings(mixed $node): mixed
    {
        if (is_string($node) && $node !== '' && ($node[0] === '{' || $node[0] === '[')) {
            $decoded = json_decode($node, true);

            return is_array($decoded) ? self::decodeStrings($decoded) : $node;
        }

        return is_array($node) ? array_map(self::decodeStrings(...), $node) : $node;
    }

    /** Depth-first search for the first array node the test accepts. */
    private static function find(mixed $node, callable $test, int|string|null $key = null, int $depth = 0): ?array
    {
        if (! is_array($node) || $depth > 8) {
            return null;
        }

        if ($test($node, $key)) {
            return $node;
        }

        foreach ($node as $k => $child) {
            if (($hit = self::find($child, $test, $k, $depth + 1)) !== null) {
                return $hit;
            }
        }

        return null;
    }
}
