<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Shipment;
use App\Services\BranchAccess;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['branch_id' => 'nullable|integer|exists:branches,id', 'tab' => 'nullable|in:outgoing,incoming,stored,collected']);
        $id = $request->user()->isAdmin() ? ($data['branch_id'] ?? Branch::where('code', 'YGN')->value('id')) : $request->user()->branch_id;
        abort_unless($id, 403);
        if (! $request->user()->isAdmin() && isset($data['branch_id'])) {
            BranchAccess::authorize($request->user(), (int) $data['branch_id']);
        }
        $branch = Branch::findOrFail($id);
        $outgoing = Shipment::with(['originBranch', 'destinationBranch', 'dispatch'])->where('origin_branch_id', $id)->where('current_branch_id', $id)->where('in_storage', true)->whereNotIn('status', ['Collected', 'Cancelled'])->latest('id')->paginate(12, ['*'], 'outgoing')->withQueryString();
        $incoming = Shipment::with(['originBranch', 'destinationBranch', 'dispatch'])->where('destination_branch_id', $id)->awaitingArrival()->latest('id')->paginate(12, ['*'], 'incoming')->withQueryString();
        $stored = Shipment::with(['originBranch', 'destinationBranch', 'dispatch'])->where('current_branch_id', $id)->where('destination_branch_id', $id)->where('in_storage', true)->whereIn('status', ['Arrived at Branch', 'Ready for Collection', 'Exception'])->latest('id')->paginate(12, ['*'], 'stored')->withQueryString();
        $collected = Shipment::with(['originBranch', 'destinationBranch', 'dispatch'])->where('destination_branch_id', $id)->where('status', 'Collected')->latest('collected_at')->limit(12)->get();

        $activeTab = $data['tab'] ?? ($outgoing->isNotEmpty() ? 'outgoing' : ($incoming->isNotEmpty() ? 'incoming' : ($stored->isNotEmpty() ? 'stored' : 'collected')));

        return view('branches.index', compact('branch', 'outgoing', 'incoming', 'stored', 'collected', 'activeTab'));
    }
}
