<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LocationController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Locations/Index', [
            'locations' => Location::withCount('nodes')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Location::create($request->validate([
            'short_code' => ['required', 'string', 'max:20', Rule::unique('locations', 'short_code')],
            'name' => ['required', 'string', 'max:120'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'description' => ['nullable', 'string', 'max:500'],
        ]));

        return back()->with('success', 'Location created.');
    }

    public function update(Request $request, Location $location): RedirectResponse
    {
        $location->update($request->validate([
            'short_code' => ['required', 'string', 'max:20', Rule::unique('locations', 'short_code')->ignore($location->id)],
            'name' => ['required', 'string', 'max:120'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'description' => ['nullable', 'string', 'max:500'],
        ]));

        return back()->with('success', 'Location updated.');
    }

    public function destroy(Location $location): RedirectResponse
    {
        if ($location->nodes()->exists()) {
            return back()->with('error', 'Move the nodes in this location first.');
        }

        $location->delete();

        return back()->with('success', 'Location deleted.');
    }
}
