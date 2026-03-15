<?php

namespace App\Http\Controllers;

use App\Models\LandingPageSection;
use App\Models\LandingPageItem;
use App\Models\LandingPageSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class LandingPageController extends Controller
{
    /**
     * Public endpoint: Get all landing page content (sections + settings) in a single call.
     * Cached for 10 minutes (landing page content rarely changes).
     */
    public function index(): JsonResponse
    {
        $data = Cache::remember('landing_page_content', 600, function () {
            $sections = LandingPageSection::visible()
                ->with('items')
                ->orderBy('sort_order')
                ->get()
                ->keyBy('section_key');

            $settings = LandingPageSetting::all()
                ->groupBy('group')
                ->map(function ($group) {
                    return $group->mapWithKeys(function ($setting) {
                        return [$setting->key => $setting->typed_value];
                    });
                });

            return [
                'sections' => $sections,
                'settings' => $settings,
            ];
        });

        return response()->json(['data' => $data]);
    }

    // ==================== ADMIN ENDPOINTS ====================

    /**
     * Admin: List all sections (including hidden)
     */
    public function adminListSections(): JsonResponse
    {
        $sections = Cache::remember('landing_page_admin_sections', 120, function () {
            return LandingPageSection::with('allItems')
                ->orderBy('sort_order')
                ->get();
        });

        return response()->json(['data' => $sections]);
    }

    /**
     * Admin: Get a single section with items
     */
    public function adminGetSection(int $id): JsonResponse
    {
        $section = LandingPageSection::with('allItems')->findOrFail($id);
        return response()->json(['data' => $section]);
    }

    /**
     * Admin: Update a section
     */
    public function adminUpdateSection(Request $request, int $id): JsonResponse
    {
        $section = LandingPageSection::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:2000',
            'badge_text' => 'nullable|string|max:255',
            'button_primary_text' => 'nullable|string|max:100',
            'button_primary_link' => 'nullable|string|max:500',
            'button_secondary_text' => 'nullable|string|max:100',
            'button_secondary_link' => 'nullable|string|max:500',
            'image_url' => 'nullable|string|max:500',
            'image_alt' => 'nullable|string|max:255',
            'metadata' => 'nullable|array',
            'sort_order' => 'nullable|integer',
            'is_visible' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'validation_failed', 'messages' => $validator->errors()], 422);
        }

        $section->update($validator->validated());
        $this->clearCache();

        return response()->json(['data' => $section->fresh('allItems'), 'message' => 'Section updated']);
    }

    /**
     * Admin: Create a new item in a section
     */
    public function adminCreateItem(Request $request, int $sectionId): JsonResponse
    {
        $section = LandingPageSection::findOrFail($sectionId);

        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:2000',
            'icon' => 'nullable|string|max:50',
            'image_url' => 'nullable|string|max:500',
            'step_number' => 'nullable|string|max:10',
            'link' => 'nullable|string|max:500',
            'metadata' => 'nullable|array',
            'sort_order' => 'nullable|integer',
            'is_visible' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'validation_failed', 'messages' => $validator->errors()], 422);
        }

        $item = $section->allItems()->create($validator->validated());
        $this->clearCache();

        return response()->json(['data' => $item, 'message' => 'Item created'], 201);
    }

    /**
     * Admin: Update an item
     */
    public function adminUpdateItem(Request $request, int $itemId): JsonResponse
    {
        $item = LandingPageItem::findOrFail($itemId);

        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:2000',
            'icon' => 'nullable|string|max:50',
            'image_url' => 'nullable|string|max:500',
            'step_number' => 'nullable|string|max:10',
            'link' => 'nullable|string|max:500',
            'metadata' => 'nullable|array',
            'sort_order' => 'nullable|integer',
            'is_visible' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'validation_failed', 'messages' => $validator->errors()], 422);
        }

        $item->update($validator->validated());
        $this->clearCache();

        return response()->json(['data' => $item->fresh(), 'message' => 'Item updated']);
    }

    /**
     * Admin: Delete an item
     */
    public function adminDeleteItem(int $itemId): JsonResponse
    {
        $item = LandingPageItem::findOrFail($itemId);
        $item->delete();
        $this->clearCache();

        return response()->json(['message' => 'Item deleted']);
    }

    /**
     * Admin: List all settings
     */
    public function adminListSettings(): JsonResponse
    {
        $settings = LandingPageSetting::orderBy('group')->orderBy('key')->get();
        return response()->json(['data' => $settings]);
    }

    /**
     * Admin: Update a setting
     */
    public function adminUpdateSetting(Request $request, int $id): JsonResponse
    {
        $setting = LandingPageSetting::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'value' => 'nullable|string|max:5000',
            'label' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'validation_failed', 'messages' => $validator->errors()], 422);
        }

        $setting->update($validator->validated());
        $this->clearCache();

        return response()->json(['data' => $setting->fresh(), 'message' => 'Setting updated']);
    }

    /**
     * Admin: Bulk update settings
     */
    public function adminBulkUpdateSettings(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'settings' => 'required|array',
            'settings.*.key' => 'required|string|max:255',
            'settings.*.value' => 'nullable|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'validation_failed', 'messages' => $validator->errors()], 422);
        }

        foreach ($request->settings as $settingData) {
            LandingPageSetting::where('key', $settingData['key'])
                ->update(['value' => $settingData['value']]);
        }

        $this->clearCache();

        return response()->json(['message' => 'Settings updated']);
    }

    private function clearCache(): void
    {
        Cache::forget('landing_page_content');
        Cache::forget('landing_page_admin_sections');
    }
}
