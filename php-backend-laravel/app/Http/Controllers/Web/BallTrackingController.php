<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\PlatformRules;
use Illuminate\Contracts\View\View;

/**
 * haraan.app/ball-tracking — the public page for Haraan ball tracking, so a search for
 * "haraan ball tracking" (or "cricket ball tracking app") lands on haraan.app rather than
 * nothing. Every word on it is in /control → Platform → Ball tracking page (the
 * `ball_tracking_web` rules section); this controller only parses and assembles schema.
 */
class BallTrackingController extends Controller
{
    public const PLAY_URL = 'https://play.google.com/store/apps/details?id=com.haraan.app';

    public function show(): View
    {
        abort_unless(PlatformRules::bool('ball_tracking_web.enabled'), 404);

        $heading = PlatformRules::string('ball_tracking_web.heading');
        $features = self::pairs(PlatformRules::string('ball_tracking_web.features'), 10);
        $steps = self::pairs(PlatformRules::string('ball_tracking_web.steps'), 6);
        $why = self::pairs(PlatformRules::string('ball_tracking_web.why'), 6);
        $faqs = PlatformRules::bool('ball_tracking_web.faq_enabled')
            ? self::pairs(PlatformRules::string('ball_tracking_web.faq'), 10)
            : [];
        $description = PlatformRules::string('ball_tracking_web.seo_description');
        $url = url('/ball-tracking');

        // Built here, never inline in Blade: a bare `@context` in a template compiles as a directive.
        $jsonld = [[
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => $heading,
            'alternateName' => ['Haraan ball tracker', 'Haraan cricket ball tracking'],
            'url' => $url,
            'description' => $description,
            'applicationCategory' => 'SportsApplication',
            'operatingSystem' => 'Android',
            'downloadUrl' => self::PLAY_URL,
            'installUrl' => self::PLAY_URL,
            'image' => asset('images/haraan-logo-square.png'),
            'featureList' => array_map(static fn (array $f): string => $f['title'], $features),
            'publisher' => ['@id' => url('/').'#org'],
            'isPartOf' => ['@id' => url('/').'#website'],
        ]];
        if ($faqs !== []) {
            $jsonld[] = [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => array_map(static fn (array $f): array => [
                    '@type' => 'Question',
                    'name' => $f['title'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['text']],
                ], $faqs),
            ];
        }
        if ($steps !== []) {
            $jsonld[] = [
                '@context' => 'https://schema.org',
                '@type' => 'HowTo',
                'name' => 'How to track a cricket ball with Haraan',
                'step' => array_map(static fn (int $i, array $s): array => [
                    '@type' => 'HowToStep',
                    'position' => $i + 1,
                    'name' => $s['title'],
                    'text' => $s['text'],
                ], array_keys($steps), $steps),
            ];
        }

        return view('site.ball-tracking', [
            'title' => PlatformRules::string('ball_tracking_web.seo_title'),
            'heading' => $heading,
            'intro' => PlatformRules::string('ball_tracking_web.intro'),
            'features' => $features,
            'steps' => $steps,
            'why' => $why,
            'whyTitle' => PlatformRules::string('ball_tracking_web.why_title'),
            'faqs' => $faqs,
            'ctaLabel' => PlatformRules::string('ball_tracking_web.cta_label'),
            'playUrl' => self::PLAY_URL,
            'seo' => [
                'description' => $description,
                'canonical' => $url,
                'jsonld' => $jsonld,
                'breadcrumbs' => [
                    ['name' => 'Haraan', 'url' => url('/')],
                    ['name' => 'Ball tracking'],
                ],
            ],
        ]);
    }

    /**
     * "Title | Text" lines → [['title' => …, 'text' => …], …]; blank or half lines are skipped.
     *
     * @return list<array{title: string, text: string}>
     */
    private static function pairs(string $raw, int $max): array
    {
        $out = [];
        foreach (preg_split('/\R/', $raw) ?: [] as $line) {
            [$title, $text] = array_map('trim', explode('|', $line, 2)) + [1 => ''];
            if ($title === '' || $text === '') {
                continue;
            }
            $out[] = ['title' => $title, 'text' => $text];
            if (count($out) >= $max) {
                break;
            }
        }

        return $out;
    }
}
