<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Shipment;
use App\Models\StorageMovement;
use App\Services\BranchAccess;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['branch_id' => 'nullable|integer|exists:branches,id', 'q' => 'nullable|string|max:150']);
        $user = $request->user();
        $id = $user->isAdmin() ? ($data['branch_id'] ?? Branch::where('code', $request->session()->get('dashboard_branch_code', 'YGN'))->value('id')) : $user->branch_id;
        BranchAccess::authorize($user, isset($data['branch_id']) ? (int) $data['branch_id'] : $id);
        $branch = Branch::findOrFail($id);
        $stock = Shipment::where('current_branch_id', $branch->id)->where('in_storage', true);
        $totals = (clone $stock)->get(['quantity', 'actual_weight']);
        $search = $data['q'] ?? '';
        $parcels = $stock->with(['originBranch', 'destinationBranch', 'dispatch'])->when($search !== '', fn ($query) => $query->where(fn ($match) => $match->where('tracking_number', 'like', '%'.$search.'%')->orWhere('storage_location', 'like', '%'.$search.'%')->orWhere('recipient_name', 'like', '%'.$search.'%')->orWhereHas('customer', fn ($customers) => $customers->where('name', 'like', '%'.$search.'%'))))->orderBy('stored_at')->orderBy('id')->paginate(20)->withQueryString();
        $movements = StorageMovement::with(['shipment', 'staff'])->where('branch_id', $branch->id)->latest('id')->paginate(20, ['*'], 'movements_page')->withQueryString();

        return view('inventory.index', compact('branch', 'parcels', 'movements') + ['parcelCount' => $totals->count(), 'pieces' => $totals->sum('quantity'), 'weight' => $totals->reduce(fn (string $sum, Shipment $shipment): string => bcadd($sum, $shipment->actual_weight, 3), '0.000')]);
    }

    public function move(Request $request, Shipment $shipment, InventoryService $inventory): RedirectResponse
    {
        $data = $request->validate(['storage_location' => 'required|string|max:100', 'notes' => 'nullable|string|max:2000']);
        $inventory->move($shipment, $request->user(), $data['storage_location'], $data['notes'] ?? null);

        return back()->with('success', __('Storage location updated.'));
    }

    public function returnToSender(Request $request, Shipment $shipment, InventoryService $inventory): RedirectResponse
    {
        $data = $request->validate(['notes' => 'required|string|max:2000']);
        $inventory->release($shipment, $request->user(), 'Returned to sender', $data['notes']);

        return back()->with('success', __('Physical return to sender recorded.'));
    }
}
