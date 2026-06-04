<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Admin controller for the cities JSON editor.
 *
 * Provides a simple GET/POST interface to view and update the
 * {@code public/data/cities.json} file that drives city-related
 * features on the public site.
 *
 * Routes using this controller are expected to be wrapped with
 * the {@see \App\Http\Middleware\EnsureErpPortalKey} middleware.
 */
final class AdminCitiesController extends Controller
{
    /** @var string Resolved path to the cities data file. */
    private readonly string $citiesPath;

    public function __construct()
    {
        $this->citiesPath = public_path('data/cities.json');
    }

    /**
     * Show the cities JSON editor view.
     */
    public function edit(): View
    {
        $json = file_exists($this->citiesPath)
            ? (string) file_get_contents($this->citiesPath)
            : '[]';

        return view('admin.cities', ['json' => $json]);
    }

    /**
     * Persist the updated cities JSON to disk.
     */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'cities_json' => ['required', 'string'],
        ]);

        file_put_contents(
            $this->citiesPath,
            (string) $request->input('cities_json'),
        );

        return redirect()->back()->with('status', 'Cities updated');
    }
}
