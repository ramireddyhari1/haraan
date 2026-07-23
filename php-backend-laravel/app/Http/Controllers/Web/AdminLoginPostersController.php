<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

final class AdminLoginPostersController extends Controller
{
    public function index(): \Illuminate\Contracts\View\View
    {
        $posters = Ad::where('placement', 'login_poster')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('admin.pages.login-posters', [
            'title'   => 'Login Posters',
            'posters' => $posters,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title'      => 'nullable|string|max:255',
            'subtitle'   => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'image'      => 'nullable|image|max:4096',
        ]);

        $imageUrl = null;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('login-posters', 'public');
            $imageUrl = Storage::disk('public')->url($path);
        }

        Ad::create([
            'placement'  => 'login_poster',
            'title'      => $data['title'] ?? '',
            'subtitle'   => $data['subtitle'] ?? null,
            'image'      => $imageUrl,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active'  => true,
        ]);

        return redirect()->route('admin.login-posters')->with('success', 'Poster added.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $poster = Ad::where('placement', 'login_poster')->findOrFail($id);

        $data = $request->validate([
            'title'      => 'nullable|string|max:255',
            'subtitle'   => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'is_active'  => 'nullable|boolean',
            'image'      => 'nullable|image|max:4096',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('login-posters', 'public');
            $data['image'] = Storage::disk('public')->url($path);
        }

        $poster->update([
            'title'      => $data['title'] ?? $poster->title,
            'subtitle'   => $data['subtitle'] ?? $poster->subtitle,
            'sort_order' => $data['sort_order'] ?? $poster->sort_order,
            'is_active'  => isset($data['is_active']) ? (bool) $data['is_active'] : $poster->is_active,
            'image'      => $data['image'] ?? $poster->image,
        ]);

        return redirect()->route('admin.login-posters')->with('success', 'Poster updated.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $poster = Ad::where('placement', 'login_poster')->findOrFail($id);
        $poster->delete();

        return redirect()->route('admin.login-posters')->with('success', 'Poster deleted.');
    }

    public function toggleActive(int $id): RedirectResponse
    {
        $poster = Ad::where('placement', 'login_poster')->findOrFail($id);
        $poster->update(['is_active' => ! $poster->is_active]);

        return redirect()->route('admin.login-posters')->with('success', 'Poster status updated.');
    }
}
