<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Services\BranchAccess;
use App\Services\ShipmentPhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShipmentPhotoController extends Controller
{
    public function store(Request $request, Shipment $shipment, ShipmentPhotoService $photos): RedirectResponse
    {
        $photos->authorizeUpload($request->user(), $shipment);
        $request->validate(ShipmentPhotoService::rules(true));
        $photos->upload($shipment, $request->file('photos'), $request->user());

        return back()->with('success', __('Parcel photos saved.'));
    }

    public function show(Request $request, Shipment $shipment, string $photo): StreamedResponse
    {
        abort_unless(BranchAccess::canRead($request->user(), $shipment), 403);
        $entry = collect($shipment->photos ?? [])->firstWhere('id', $photo);
        abort_unless($entry && Storage::disk('local')->exists($entry['path']), 404);

        return Storage::disk('local')->response($entry['path'], null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'], 'inline');
    }
}
