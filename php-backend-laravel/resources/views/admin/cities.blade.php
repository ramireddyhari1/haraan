<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Manage Cities — Admin</title>
    <link rel="stylesheet" href="/css/site.css">
    <style>body{font-family:Inter,system-ui,Arial,sans-serif;padding:24px;background:#f7fafc}.editor-card{max-width:900px;margin:24px auto;background:#fff;padding:18px;border-radius:10px;box-shadow:0 8px 24px rgba(3,12,30,0.06)}textarea{width:100%;height:420px;padding:12px;border:1px solid #e6edf3;border-radius:8px;font-family:monospace;font-size:13px}button{padding:10px 14px;border-radius:8px}</style>
</head>
<body>
    <div class="editor-card">
        <h1>Manage Cities</h1>
        @if(session('status'))<div style="margin:8px 0;padding:8px;background:#e6ffed;border:1px solid #cdeacb">{{ session('status') }}</div>@endif
        <form method="POST" action="/admin/cities?key={{ request()->query('key') }}">
            @csrf
            <label for="cities_json">cities.json</label>
            <textarea id="cities_json" name="cities_json">{{ $json }}</textarea>
            <div style="margin-top:12px;display:flex;gap:8px"><button class="btn btn--solid" type="submit">Save</button><a class="btn btn--ghost" href="/">Back to site</a></div>
        </form>
    </div>
</body>
</html>
