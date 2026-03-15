<?php

namespace App\Http\Controllers;

use App\Models\RefundReason;
use App\Models\ActionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RefundReasonController extends Controller
{
    /**
     * Get all refund reasons (for settings page)
     */
    public function index(Request $request)
    {
        $type = $request->query('type');
        
        $query = RefundReason::orderBy('type')->orderBy('sort_order');
        
        if ($type && in_array($type, ['request', 'decline'])) {
            $query->where('type', $type);
        }

        return response()->json([
            'success' => true,
            'data' => $query->get()
        ]);
    }

    /**
     * Get active reasons only (for dropdowns in forms)
     */
    public function getActive(Request $request)
    {
        $type = $request->query('type');
        
        $query = RefundReason::active()->orderBy('sort_order');
        
        if ($type && in_array($type, ['request', 'decline'])) {
            $query->where('type', $type);
        }

        return response()->json([
            'success' => true,
            'data' => $query->get()
        ]);
    }

    /**
     * Create a new refund reason
     */
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:request,decline',
            'label' => 'required|string|max:255',
            'sort_order' => 'nullable|integer|min:0'
        ]);

        // Generate a key from the label
        $key = Str::snake(Str::lower($request->label));
        
        // Ensure key is unique within the same type
        $originalKey = $key;
        $counter = 1;
        while (RefundReason::where('key', $key)->where('type', $request->type)->exists()) {
            $key = $originalKey . '_' . $counter;
            $counter++;
        }

        $reason = RefundReason::create([
            'type' => $request->type,
            'key' => $key,
            'label' => $request->label,
            'is_active' => true,
            'is_default' => false,
            'sort_order' => $request->sort_order ?? RefundReason::where('type', $request->type)->max('sort_order') + 1,
            'created_by' => $request->user()->id,
        ]);

        ActionLog::log(
            'create',
            "Created new refund {$request->type} reason: {$request->label}",
            'RefundReason',
            $reason->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Refund reason created successfully',
            'data' => $reason
        ], 201);
    }

    /**
     * Update a refund reason
     */
    public function update(Request $request, $id)
    {
        $reason = RefundReason::findOrFail($id);

        $request->validate([
            'label' => 'sometimes|required|string|max:255',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0'
        ]);

        // Only allow editing label and active status for default reasons
        $updateData = [];
        
        if ($request->has('label')) {
            $updateData['label'] = $request->label;
        }
        if ($request->has('is_active')) {
            $updateData['is_active'] = $request->is_active;
        }
        if ($request->has('sort_order')) {
            $updateData['sort_order'] = $request->sort_order;
        }

        $reason->update($updateData);

        ActionLog::log(
            'update',
            "Updated refund reason: {$reason->label}",
            'RefundReason',
            $reason->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Refund reason updated successfully',
            'data' => $reason->fresh()
        ]);
    }

    /**
     * Delete a refund reason (only non-default ones)
     */
    public function destroy($id)
    {
        $reason = RefundReason::findOrFail($id);

        if ($reason->is_default) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete default system reasons. You can deactivate them instead.'
            ], 403);
        }

        $label = $reason->label;
        $reason->delete();

        ActionLog::log(
            'delete',
            "Deleted refund reason: {$label}",
            'RefundReason',
            $id
        );

        return response()->json([
            'success' => true,
            'message' => 'Refund reason deleted successfully'
        ]);
    }
}
