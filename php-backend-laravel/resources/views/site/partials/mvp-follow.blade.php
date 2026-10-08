@if(! empty($p['canFollow']) && ($p['playerId'] ?? '') !== '')
    <button type="button" class="mvp-follow {{ ! empty($p['isFollowing']) ? 'is-on' : '' }}" data-id="{{ $p['playerId'] }}" onclick="event.preventDefault(); mvpFollow(this)">{{ ! empty($p['isFollowing']) ? 'Following' : 'Follow' }}</button>
@endif
