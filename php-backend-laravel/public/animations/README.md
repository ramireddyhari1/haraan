Place your 404 Lottie JSON file here so the site can load the animation.

1. Copy the provided JSON to this folder and name it `404.json`.

Windows PowerShell example:

```powershell
Copy-Item "C:\Users\harih\Downloads\lottieflow-404-12-1-000000-easey.json" -Destination "public\animations\404.json"
```

After copying, open `http://127.0.0.1:9001/404-test` to preview the 404 page.

If you prefer a different filename or location, update the `src` attribute in `resources/views/errors/404.blade.php`.
