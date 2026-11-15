@php
    /** @var array<int, string> $quickReplies */
@endphp
<div class="stc-compose"
     x-data="{
        grow() { const t = this.$refs.box; t.style.height = 'auto'; t.style.height = Math.min(t.scrollHeight, 160) + 'px' },
        use(text) { $wire.set('replyBody', text, false); this.$refs.box.value = text; this.$nextTick(() => { this.grow(); this.$refs.box.focus() }) },
     }">
    <div class="stc-quick" role="list" aria-label="Saved replies">
        @foreach ($quickReplies as $text)
            <button type="button" class="stc-chip" role="listitem" title="{{ $text }}" x-on:click="use(@js($text))">
                {{ \Illuminate\Support\Str::limit($text, 42) }}
            </button>
        @endforeach
    </div>
    <form class="stc-box" wire:submit.prevent="sendReply">
        <textarea x-ref="box"
                  rows="1"
                  wire:model="replyBody"
                  x-on:input="grow()"
                  x-on:keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); $wire.sendReply().then(() => { $refs.box.value = ''; grow() }) }"
                  placeholder="Write a reply…"
                  maxlength="4000"
                  aria-label="Reply"></textarea>
        <button type="submit" class="stc-send" wire:loading.attr="disabled" wire:target="sendReply">
            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3.1 2.3a.75.75 0 0 0-.98.95l2.06 6.1H10a.75.75 0 0 1 0 1.5H4.18l-2.06 6.1a.75.75 0 0 0 .98.95l15-7a.75.75 0 0 0 0-1.36l-15-6.24Z"/></svg>
            Send
        </button>
    </form>
    <p class="stc-hint">Enter to send · Shift + Enter for a new line · Sending marks the chat “waiting on user”.</p>
</div>
