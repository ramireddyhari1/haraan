@extends('admin.partials.layout')

@section('content')
<section class="card hero" style="margin-bottom:14px; background: linear-gradient(135deg, #f0f7ff 0%, #e8f0fe 100%); border-left: 5px solid #2563eb;">
    <span class="eyebrow">App Content</span>
    <h2 style="margin: 0; font-size: 26px;">Login Screen Posters</h2>
    <p style="margin: 0; font-size: 14px;">Manage the carousel images shown on the app's login screen. Changes go live immediately — no app update needed.</p>
</section>

@if(session('success'))
<div style="background:#dcfce7; color:#16a34a; padding:12px 16px; border-radius:10px; margin-bottom:14px; font-weight:600;">
    ✓ {{ session('success') }}
</div>
@endif

<div style="display:grid; grid-template-columns: 1.5fr 1fr; gap:14px; align-items:start;">

    {{-- Poster list --}}
    <section class="card" style="padding:16px;">
        <h3 style="margin:0 0 14px; font-size:16px;">Current Posters</h3>

        @if($posters->isEmpty())
            <p style="color:#64748b; font-size:14px;">No posters yet. Add one using the form on the right.</p>
        @else
        <div style="display:flex; flex-direction:column; gap:14px;">
            @foreach($posters as $poster)
            <div style="display:grid; grid-template-columns:80px 1fr auto; gap:12px; align-items:center; padding:12px; border:1px solid #e2e8f0; border-radius:12px; background:#fafafa;">
                {{-- Thumbnail --}}
                <div style="width:80px; height:60px; border-radius:8px; overflow:hidden; background:#e2e8f0; flex-shrink:0;">
                    @if($poster->image)
                        <img src="{{ $poster->image }}" alt="Poster" style="width:100%; height:100%; object-fit:cover;">
                    @else
                        <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:12px;">No image</div>
                    @endif
                </div>

                {{-- Info --}}
                <div>
                    <div style="font-weight:700; font-size:14px; color:#0f172a;">{{ $poster->title ?: '(untitled)' }}</div>
                    @if($poster->subtitle)
                    <div style="font-size:12px; color:#64748b; margin-top:2px;">{{ $poster->subtitle }}</div>
                    @endif
                    <div style="margin-top:6px; display:flex; gap:8px; align-items:center;">
                        <span style="font-size:11px; padding:2px 8px; border-radius:20px; background:{{ $poster->is_active ? '#dcfce7' : '#f1f5f9' }}; color:{{ $poster->is_active ? '#16a34a' : '#64748b' }}; font-weight:600;">
                            {{ $poster->is_active ? 'Active' : 'Hidden' }}
                        </span>
                        <span style="font-size:11px; color:#94a3b8;">Order: {{ $poster->sort_order }}</span>
                    </div>
                </div>

                {{-- Actions --}}
                <div style="display:flex; flex-direction:column; gap:6px; align-items:flex-end;">
                    <form method="post" action="{{ route('admin.login-posters.toggle', $poster->id) }}" style="margin:0;">
                        @csrf
                        <button type="submit" style="background:{{ $poster->is_active ? '#fef9c3' : '#dcfce7' }}; color:{{ $poster->is_active ? '#92400e' : '#166534' }}; border:0; border-radius:8px; padding:5px 10px; font-size:12px; font-weight:700; cursor:pointer;">
                            {{ $poster->is_active ? 'Hide' : 'Show' }}
                        </button>
                    </form>

                    <button onclick="openEdit({{ $poster->id }}, '{{ addslashes($poster->title ?? '') }}', '{{ addslashes($poster->subtitle ?? '') }}', {{ $poster->sort_order ?? 0 }}, {{ $poster->is_active ? 'true' : 'false' }})"
                        style="background:#eff6ff; color:#2563eb; border:0; border-radius:8px; padding:5px 10px; font-size:12px; font-weight:700; cursor:pointer;">
                        Edit
                    </button>

                    <form method="post" action="{{ route('admin.login-posters.delete', $poster->id) }}" onsubmit="return confirm('Delete this poster?')" style="margin:0;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" style="background:#fee2e2; color:#dc2626; border:0; border-radius:8px; padding:5px 10px; font-size:12px; font-weight:700; cursor:pointer;">
                            Delete
                        </button>
                    </form>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </section>

    {{-- Add poster form --}}
    <section class="card" style="padding:16px;">
        <h3 style="margin:0 0 14px; font-size:16px;">Add New Poster</h3>
        <form method="post" action="{{ route('admin.login-posters.store') }}" enctype="multipart/form-data">
            @csrf
            <div style="display:flex; flex-direction:column; gap:12px;">
                <div>
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:4px; color:#334155;">Poster Image</label>
                    <input type="file" name="image" accept="image/*" required style="width:100%; font-size:13px;">
                    <div style="font-size:11px; color:#94a3b8; margin-top:4px;">Recommended: 1080×2400px, JPG/PNG, max 4 MB</div>
                </div>
                <div>
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:4px; color:#334155;">Title (optional)</label>
                    <input type="text" name="title" placeholder="e.g. Summer Sports Season" style="width:100%; box-sizing:border-box; padding:10px 12px; border:1px solid #e2e8f0; border-radius:10px; font-size:14px;">
                </div>
                <div>
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:4px; color:#334155;">Subtitle (optional)</label>
                    <input type="text" name="subtitle" placeholder="e.g. Book your spot now" style="width:100%; box-sizing:border-box; padding:10px 12px; border:1px solid #e2e8f0; border-radius:10px; font-size:14px;">
                </div>
                <div>
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:4px; color:#334155;">Sort Order</label>
                    <input type="number" name="sort_order" value="0" min="0" style="width:100%; box-sizing:border-box; padding:10px 12px; border:1px solid #e2e8f0; border-radius:10px; font-size:14px;">
                    <div style="font-size:11px; color:#94a3b8; margin-top:4px;">Lower number = shown first</div>
                </div>
                <button type="submit" style="background:linear-gradient(90deg,#2563eb,#4f46e5); color:#fff; border:0; border-radius:12px; padding:12px; font-size:15px; font-weight:700; cursor:pointer;">
                    Upload Poster
                </button>
            </div>
        </form>
    </section>
</div>

{{-- Edit modal --}}
<div id="edit-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:16px; padding:24px; width:420px; max-width:90vw;">
        <h3 style="margin:0 0 16px; font-size:17px;">Edit Poster</h3>
        <form id="edit-form" method="post" enctype="multipart/form-data">
            @csrf
            <div style="display:flex; flex-direction:column; gap:12px;">
                <div>
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:4px;">Replace Image (optional)</label>
                    <input type="file" name="image" accept="image/*" style="width:100%; font-size:13px;">
                </div>
                <div>
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:4px;">Title</label>
                    <input type="text" id="edit-title" name="title" style="width:100%; box-sizing:border-box; padding:10px 12px; border:1px solid #e2e8f0; border-radius:10px; font-size:14px;">
                </div>
                <div>
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:4px;">Subtitle</label>
                    <input type="text" id="edit-subtitle" name="subtitle" style="width:100%; box-sizing:border-box; padding:10px 12px; border:1px solid #e2e8f0; border-radius:10px; font-size:14px;">
                </div>
                <div>
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:4px;">Sort Order</label>
                    <input type="number" id="edit-order" name="sort_order" min="0" style="width:100%; box-sizing:border-box; padding:10px 12px; border:1px solid #e2e8f0; border-radius:10px; font-size:14px;">
                </div>
                <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:4px;">
                    <button type="button" onclick="closeEdit()" style="background:#f1f5f9; color:#334155; border:0; border-radius:10px; padding:10px 18px; font-weight:600; cursor:pointer;">Cancel</button>
                    <button type="submit" style="background:#2563eb; color:#fff; border:0; border-radius:10px; padding:10px 18px; font-weight:700; cursor:pointer;">Save Changes</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function openEdit(id, title, subtitle, order, isActive) {
    document.getElementById('edit-title').value = title;
    document.getElementById('edit-subtitle').value = subtitle;
    document.getElementById('edit-order').value = order;
    document.getElementById('edit-form').action = '/admin/login-posters/' + id;
    document.getElementById('edit-modal').style.display = 'flex';
}
function closeEdit() {
    document.getElementById('edit-modal').style.display = 'none';
}
document.getElementById('edit-modal').addEventListener('click', function(e) {
    if (e.target === this) closeEdit();
});
</script>
@endsection
