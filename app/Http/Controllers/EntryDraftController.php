<?php

namespace App\Http\Controllers;

use App\Models\EntryDraft;
use App\Services\BranchAccess;
use App\Services\EntryDraftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EntryDraftController extends Controller
{
    public function store(Request $request, EntryDraftService $service): RedirectResponse
    {
        $request->validate(['kind' => 'required|in:booking,trip']);
        $draft = $service->save($request, $request->kind);

        return redirect()->route($draft->kind === 'booking' ? 'shipments.create' : 'dispatches.index', ['draft' => $draft->key])->with('success', __('Draft saved. Complete the missing details whenever ready.'));
    }

    public function photo(EntryDraft $draft, string $photo): BinaryFileResponse
    {
        BranchAccess::authorize(auth()->user(), $draft->branch_id);
        $image = collect($draft->photos ?? [])->firstWhere('id', $photo);
        abort_unless($image && Storage::disk('local')->exists($image['path']), 404);

        return response()->file(Storage::disk('local')->path($image['path']), ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
