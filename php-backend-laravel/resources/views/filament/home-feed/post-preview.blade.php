{{-- The post as a player sees it, for a moderator deciding what to do with it. --}}
<div style="display:flex;flex-direction:column;gap:14px">
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:8px">
        @forelse ($images as $src)
            <a href="{{ $src }}" target="_blank" rel="noopener" style="display:block;aspect-ratio:1;border-radius:10px;overflow:hidden;background:#f1f5f9">
                <img src="{{ $src }}" alt="" loading="lazy" style="width:100%;height:100%;object-fit:cover">
            </a>
        @empty
            <div style="color:#64748b;font-size:13px">No photo on file.</div>
        @endforelse
    </div>

    <div style="font-size:14px;line-height:1.5;color:#0f172a;white-space:pre-wrap">{{ $post->caption ?: 'No caption' }}</div>

    <div style="font-size:12px;color:#64748b">
        {{ $post->likes()->count() }} likes · posted {{ $post->created_at?->diffForHumans() }}
        @if (! $post->isVisible())
            · <strong style="color:#b45309">{{ \App\Models\PlayerPost::STATUSES[$post->status] ?? $post->status }}</strong>
            @if ($post->moderation_reason) — {{ $post->moderation_reason }} @endif
        @endif
    </div>

    <div>
        <div style="font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#64748b;margin-bottom:6px">Latest comments</div>
        @forelse ($comments as $c)
            <div style="padding:8px 0;border-top:1px solid #e2e8f0;font-size:13px;{{ $c->status !== 'visible' ? 'opacity:.5' : '' }}">
                <strong>{{ $c->user?->name ?? 'Player' }}</strong>
                <span style="color:#334155">{{ $c->body }}</span>
                @if ($c->status !== 'visible') <em style="color:#b45309">(hidden)</em> @endif
            </div>
        @empty
            <div style="color:#64748b;font-size:13px">No comments.</div>
        @endforelse
    </div>
</div>
