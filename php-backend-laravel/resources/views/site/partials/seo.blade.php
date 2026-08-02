{{--
    Shared search-engine / social metadata for every public site page.

    Pages (or their controllers) pass a `$seo` array to override the defaults:
      title       — the <title> text (the layout adds the "| Haraan" suffix)
      description — meta description, ~155 chars, no markup
      image       — absolute URL for og:image / twitter:image
      type        — og:type (website, article, profile, event…)
      canonical   — absolute URL; defaults to the current path WITHOUT the query
                    string, so ?page=/?city= variants don't split ranking signals
      robots      — defaults to index,follow on public pages and noindex on the
                    private lanes below (account, checkout, auth, consoles)
      jsonld      — one schema.org array, or a list of them
      breadcrumbs — [['name' => 'Events', 'url' => '…'], …] for the trail Google
                    shows in place of the raw URL; the current page comes last
                    and may omit its url
--}}
@php
    $seoIn = $seo ?? [];

    // Private lanes must never be indexed: they're behind auth, personalised, or
    // thin duplicates (search results). Matched on path so a new route in one of
    // these prefixes is covered without touching its controller.
    $seoPath = '/' . ltrim(request()->path(), '/');
    $seoPrivate = (bool) preg_match(
        '#^/(login|register|profile|bookings|support|notifications|search|partner|control|admin|t/)#',
        $seoPath,
    ) || preg_match('#/(book|pass|control|create)$#', $seoPath);

    $seoDefaultDesc = 'Haraan — book concerts, comedy, workshops and sports events near you, '
        . 'find turfs and courts to play, and follow live gully cricket scores.';

    // The default share image is a purpose-built 1200x630 card. The bare wordmark
    // that used to sit here was 1680x445 and transparent, so every client that
    // crops to 1.91:1 mangled it and composited the gaps onto black.
    $seoDefaultImage = asset('images/haraan-social-card.png');

    $seoMeta = array_merge([
        'title' => $title ?? 'Haraan',
        'description' => $seoDefaultDesc,
        'image' => $seoDefaultImage,
        'type' => 'website',
        'canonical' => url($seoPath === '/' ? '/' : $seoPath),
        'robots' => $seoPrivate ? 'noindex,nofollow' : 'index,follow,max-image-preview:large',
        'jsonld' => null,
        'breadcrumbs' => null,
    ], array_filter($seoIn, static fn ($v) => $v !== null && $v !== ''));

    $seoDesc = \Illuminate\Support\Str::limit(
        trim(preg_replace('/\s+/', ' ', strip_tags((string) $seoMeta['description']))),
        158,
    );

    // A single schema.org array or a list of them — normalise to a list.
    $seoBlocks = $seoMeta['jsonld'] ?? [];
    if (is_array($seoBlocks) && isset($seoBlocks['@type'])) {
        $seoBlocks = [$seoBlocks];
    }

    // Breadcrumbs let Google print "haraan.app › Events › Concert name" instead of
    // a bare URL. The last crumb is the current page, so its `item` is omitted —
    // schema.org treats a self-referencing last crumb as redundant.
    $seoCrumbs = array_values(array_filter((array) ($seoMeta['breadcrumbs'] ?? [])));
    if ($seoCrumbs !== [] && ! $seoPrivate) {
        $seoLast = count($seoCrumbs) - 1;
        $seoBlocks[] = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(
                static fn (int $i, array $c) => array_filter([
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'name' => $c['name'] ?? null,
                    'item' => $i === $seoLast ? null : ($c['url'] ?? null),
                ], static fn ($v) => $v !== null),
                array_keys($seoCrumbs),
                $seoCrumbs,
            ),
        ];
    }

    // Site-wide identity: helps Google resolve the brand entity and show a
    // sitelinks search box for haraan.app.
    // NB: this graph is assembled here rather than inline in the markup below —
    // Blade parses a bare `@context` in template text as a directive and would
    // compile it into the JSON.
    $seoSiteGraph = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'Organization',
                '@id' => url('/') . '#org',
                'name' => 'Haraan',
                'alternateName' => 'Haraan App',
                'url' => url('/'),
                // MUST be square: Google renders this as the round icon beside the
                // search result, so the wide wordmark that used to be here came out
                // as a white circle with unreadable "Haraan" text letterboxed in it.
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => asset('images/haraan-logo-square.png'),
                    'width' => 512,
                    'height' => 512,
                ],
                'image' => asset('images/haraan-logo-square.png'),
                'description' => 'Haraan — book concerts, comedy, workshops and sports events, '
                    . 'reserve cricket turfs and courts, and follow live gully-cricket scores.',
                // sameAs is the brand-verification signal: it links this entity to
                // its known profiles so Google resolves "haraan" to this brand.
                'sameAs' => [
                    'https://www.instagram.com/haraan_official/',
                ],
            ],
            [
                '@type' => 'WebSite',
                '@id' => url('/') . '#website',
                'name' => 'Haraan',
                'url' => url('/'),
                'publisher' => ['@id' => url('/') . '#org'],
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => [
                        '@type' => 'EntryPoint',
                        'urlTemplate' => url('/search') . '?q={search_term_string}',
                    ],
                    'query-input' => 'required name=search_term_string',
                ],
            ],
        ],
    ];
@endphp
<meta name="description" content="{{ $seoDesc }}">
<meta name="robots" content="{{ $seoMeta['robots'] }}">
<link rel="canonical" href="{{ $seoMeta['canonical'] }}">

<meta property="og:site_name" content="Haraan">
<meta property="og:type" content="{{ $seoMeta['type'] }}">
<meta property="og:title" content="{{ $seoMeta['title'] }}">
<meta property="og:description" content="{{ $seoDesc }}">
<meta property="og:url" content="{{ $seoMeta['canonical'] }}">
<meta property="og:image" content="{{ $seoMeta['image'] }}">
<meta property="og:image:alt" content="{{ $seoMeta['title'] }}">
@if ($seoMeta['image'] === $seoDefaultImage)
    {{-- Dimensions only for our own card: crawlers can lay the preview out before
         the image loads, but guessing them for a partner's poster would be a lie. --}}
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
@endif
<meta property="og:locale" content="en_IN">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seoMeta['title'] }}">
<meta name="twitter:description" content="{{ $seoDesc }}">
<meta name="twitter:image" content="{{ $seoMeta['image'] }}">
<meta name="twitter:image:alt" content="{{ $seoMeta['title'] }}">

@foreach ($seoBlocks as $seoBlock)
    <script type="application/ld+json">{!! json_encode($seoBlock, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endforeach

@unless ($seoPrivate)
    <script type="application/ld+json">{!! json_encode($seoSiteGraph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endunless
