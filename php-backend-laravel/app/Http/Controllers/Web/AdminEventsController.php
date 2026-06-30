<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\View\View;

final class AdminEventsController extends Controller
{
    public function indexJson(Request $request)
    {
        $this->authorize('viewAny', Event::class);

        $limit = (int) $request->query('limit', 50);
        $user = Auth::user();
        $q = $request->query('q');

        // If the user has a broad permission or is a super admin role, return all events.
        if ($user->hasRole('SUPER ADMIN') || $user->can('events.view.all')) {
            $base = Event::orderByDesc('created_at');
        } else {
            // Restrict events to those belonging to partners inside user's organization units.
            $orgIds = DB::table('user_organization_map')->where('user_id', $user->id)->pluck('organization_id')->toArray();
            if (empty($orgIds)) {
                return response()->json(['data' => []]);
            }
            $base = Event::join('users', 'events.partner_id', '=', 'users.id')
                ->join('user_organization_map as uom', 'uom.user_id', '=', 'users.id')
                ->whereIn('uom.organization_id', $orgIds)
                ->orderByDesc('events.created_at')
                ->select('events.*');
        }

        if ($q) {
            $base->where('events.title', 'like', "%{$q}%");
        }

        $events = $base->withSum(['bookings as tickets_sold' => function ($query) {
            $query->whereIn('status', ['PAID', 'CONFIRMED', 'paid', 'confirmed']);
        }], 'quantity')->withSum(['bookings as revenue' => function ($query) {
            $query->whereIn('status', ['PAID', 'CONFIRMED', 'paid', 'confirmed']);
        }], 'total_amount')->limit($limit)->get();

        $events->each(function ($event) {
            $event->tickets_sold = (int) ($event->tickets_sold ?? 0);
            $event->revenue = (float) ($event->revenue ?? 0.0);
        });

        return response()->json(['data' => $events]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Event::class);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'date' => ['nullable', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'venue' => ['nullable', 'string', 'max:255'],
            'price' => ['nullable', 'numeric'],
            'totalSlots' => ['nullable', 'integer'],
        ]);

        $user = Auth::user();

        $event = Event::create(array_merge($validated, [
            'description' => $request->input('description', ''),
            'category' => $request->input('category', 'GENERAL'),
            'booking_format' => $request->input('booking_format', 'HYBRID'),
            'visibility' => $request->input('visibility', 'PUBLIC'),
            'access_code' => $request->input('access_code'),
            'price' => isset($validated['price']) ? (float)$validated['price'] : 0.0,
            'total_slots' => isset($validated['totalSlots']) ? (int)$validated['totalSlots'] : 0,
            'available_slots' => isset($validated['totalSlots']) ? (int)$validated['totalSlots'] : 0,
            'status' => $request->input('status', 'DRAFT'),
            'partner_id' => $user->id,
        ]));

        return response()->json(['message' => 'Event created', 'data' => $event], 201);
    }

    public function update(Request $request, string $id)
    {
        $event = Event::find($id);
        if (! $event) {
            return response()->json(['error' => 'Event not found'], 404);
        }

        $this->authorize('update', $event);

        $data = $request->only(['title','date','location','venue','price','totalSlots','status']);
        if (isset($data['price'])) { $data['price'] = (float)$data['price']; }
        if (isset($data['totalSlots'])) { $data['total_slots'] = (int)$data['totalSlots']; unset($data['totalSlots']); }

        $event->fill($data);
        $event->save();

        return response()->json(['message' => 'Event updated', 'data' => $event]);
    }

    public function destroy(string $id)
    {
        $event = Event::find($id);
        if (! $event) {
            return response()->json(['error' => 'Event not found'], 404);
        }

        $this->authorize('delete', $event);

        $event->delete();
        return response()->json(['message' => 'Event deleted']);
    }

    public function create(): View
    {
        $this->authorize('create', Event::class);

        return view('admin.pages.events-new', ['title' => 'Create Event']);
    }

    public function edit(string $id): View
    {
        $event = Event::findOrFail($id);
        $this->authorize('update', $event);

        return view('admin.pages.events-edit', ['title' => 'Edit Event', 'id' => $id]);
    }
}
