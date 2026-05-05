<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\ServiceUnavailability;
use App\Models\ActionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class ServiceController extends Controller
{
    /**
     * Clear services-related caches after mutations.
     */
    private function clearServicesCache()
    {
        try {
            Cache::forget('public_services');
            Cache::forget('public_init_data');
            Cache::forget('services_active');
            Cache::forget('services_all_active');
            Cache::forget('services_admin_all');
            Cache::forget('services_stats');
            foreach (['daily', 'weekly', 'monthly', 'yearly'] as $timeframe) {
                Cache::forget("cashier_dashboard_stats_{$timeframe}");
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to clear services cache: ' . $e->getMessage());
            // Fallback: flush all cache to ensure stale data is never served
            try {
                Cache::flush();
            } catch (\Exception $flushErr) {
                \Log::error('Cache flush also failed: ' . $flushErr->getMessage());
            }
        }
    }

    public function index()
    {
        try {
            $services = Cache::remember('services_active', 120, function () {
                return Service::where('is_active', true)->orderBy('name')->get();
            });

            // Append current unavailability info for public consumers
            $services->each(function ($service) {
                $unavailability = $service->getCurrentUnavailability();
                $service->is_unavailable = $unavailability !== null;
                $service->unavailability_reason = $unavailability?->reason;
                $service->unavailability_category = $unavailability?->reason_category;
                $service->unavailable_until = $unavailability?->is_global ? null : $unavailability?->unavailable_until;
            });
            
            return response()->json([
                'data' => $services,
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch services',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
                'success' => false
            ], 500);
        }
    }

    public function allServices()
    {
        try {
            $services = Cache::remember('services_all_active', 120, function () {
                return Service::where('is_active', true)
                    ->orderBy('name')
                    ->get();
            });

            // Append current unavailability info
            $services->each(function ($service) {
                $unavailability = $service->getCurrentUnavailability();
                $service->is_unavailable = $unavailability !== null;
                $service->unavailability_reason = $unavailability?->reason;
                $service->unavailability_category = $unavailability?->reason_category;
                $service->unavailable_until = $unavailability?->is_global ? null : $unavailability?->unavailable_until;
            });
            
            return response()->json([
                'data' => $services,
                'count' => $services->count(),
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch services',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
                'success' => false
            ], 500);
        }
    }

    /**
     * Get all services for admin panel (includes archived for manage view)
     * NOTE: No caching here — admin must always see fresh data after edits/deletes
     */
    public function adminServices()
    {
        try {
            $services = Service::withTrashed()
                ->with(['unavailabilities' => function ($q) {
                    $q->where('is_active', true)->orderBy('created_at', 'desc');
                }])
                ->orderBy('name')
                ->get();

            // Append computed unavailability status
            $services->each(function ($service) {
                $unavailability = $service->getCurrentUnavailability();
                $service->is_unavailable = $unavailability !== null;
                $service->current_unavailability = $unavailability;
            });
            
            return response()->json([
                'data' => $services,
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch services',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
                'success' => false
            ], 500);
        }
    }

    /**
     * Sync default appointment types from Appointment model to Services table
     * This creates Service entries for all predefined appointment types
     */
    public function syncDefaultAppointmentTypes()
    {
        try {
            // SIMPLIFIED: Just return success without trying to access Appointment model
            return response()->json([
                'message' => 'Default services sync endpoint',
                'note' => 'Functionality disabled for deployment',
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Service sync not available',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
                'success' => false
            ], 500);
        }
    }

    /**
     * Sync service types from existing appointments to Services table (legacy)
     * This creates Service entries for any service_type found in appointments
     */
    public function syncServicesFromAppointments()
    {
        try {
            // SIMPLIFIED: Just return success
            return response()->json([
                'message' => 'Services sync endpoint',
                'note' => 'Functionality disabled for deployment',
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Service sync not available',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
                'success' => false
            ], 500);
        }
    }

    public function getStats()
    {
        try {
            $stats = Cache::remember('services_stats', 120, function () {
                return Service::where('is_active', true)
                    ->select(['id', 'name', 'description', 'public_requirements', 'is_active'])
                    ->orderBy('name')
                    ->get()
                    ->map(function($service) {
                        $unavailability = $service->getCurrentUnavailability();
                        return [
                            'id' => $service->id,
                            'name' => $service->name,
                            'description' => $service->description,
                            'public_requirements' => $service->public_requirements,
                            'count' => 0,
                            'is_active' => $service->is_active,
                            'is_unavailable' => $unavailability !== null,
                            'unavailability_reason' => $unavailability?->reason,
                            'unavailability_category' => $unavailability?->reason_category,
                            'unavailable_until' => ($unavailability && !$unavailability->is_global) ? $unavailability->unavailable_until : null,
                        ];
                    })
                    ->values();
            });

            return response()->json([
                'data' => $stats,
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch service statistics',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
                'success' => false
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            // Convert empty strings to null for optional fields
            $data = $request->all();
            if (isset($data['price']) && $data['price'] === '') $data['price'] = null;
            if (isset($data['duration']) && $data['duration'] === '') $data['duration'] = null;
            if (isset($data['description']) && $data['description'] === '') $data['description'] = null;
            $request->merge($data);

            $request->validate([
                'name' => 'required|string|max:255|unique:services,name,NULL,id,deleted_at,NULL',
                'description' => 'nullable|string|max:1000',
                'price' => 'nullable|numeric|min:0',
                'duration' => 'nullable|integer|min:15',
                'public_requirements' => 'nullable|array',
                'public_requirements.*' => 'nullable|string',
                'internal_staff_notes' => 'nullable|string|max:2000'
            ]);

            $service = Service::create([
                'name' => $request->name,
                'description' => $request->description,
                'price' => $request->price,
                'duration' => $request->duration,
                'is_active' => true,
                'public_requirements' => $request->public_requirements ?? null,
                'internal_staff_notes' => $request->internal_staff_notes ?? null
            ]);

            // Clear cache so new service appears immediately
            $this->clearServicesCache();

            ActionLog::log('create', "Created service: {$service->name} (ID: {$service->id})", 'Service', $service->id);

            return response()->json([
                'message' => 'Service created successfully',
                'data' => $service,
                'success' => true
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'success' => false
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create service',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
                'success' => false
            ], 500);
        }
    }

    public function update(Request $request, Service $service)
    {
        try {
            // Convert empty strings to null for optional fields
            $data = $request->all();
            if (isset($data['price']) && $data['price'] === '') $data['price'] = null;
            if (isset($data['duration']) && $data['duration'] === '') $data['duration'] = null;
            if (isset($data['description']) && $data['description'] === '') $data['description'] = null;
            $request->merge($data);

            $request->validate([
                'name' => 'required|string|max:255|unique:services,name,' . $service->id,
                'description' => 'nullable|string|max:1000',
                'price' => 'nullable|numeric|min:0',
                'duration' => 'nullable|integer|min:15',
                'is_active' => 'boolean',
                'public_requirements' => 'nullable|array',
                'public_requirements.*' => 'nullable|string',
                'internal_staff_notes' => 'nullable|string|max:2000'
            ]);

            $service->update([
                'name' => $request->name,
                'description' => $request->description,
                'price' => $request->price,
                'duration' => $request->duration,
                'is_active' => $request->is_active ?? $service->is_active,
                'public_requirements' => $request->has('public_requirements') ? $request->public_requirements : $service->public_requirements,
                'internal_staff_notes' => $request->has('internal_staff_notes') ? $request->internal_staff_notes : $service->internal_staff_notes
            ]);

            // Clear cache so changes appear immediately
            $this->clearServicesCache();

            ActionLog::log('update', "Updated service: {$service->name} (ID: {$service->id})", 'Service', $service->id);

            return response()->json([
                'message' => 'Service updated successfully',
                'data' => $service,
                'success' => true
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'success' => false
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update service',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
                'success' => false
            ], 500);
        }
    }

    public function destroy(Service $service)
    {
        try {
            $serviceName = $service->name;
            $serviceId = $service->id;
            $service->delete();

            // Clear cache so changes appear immediately
            $this->clearServicesCache();

            ActionLog::log('archive', "Archived service: {$serviceName} (ID: {$serviceId})", 'Service', $serviceId);

            return response()->json([
                'message' => 'Service archived successfully',
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to archive service',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
                'success' => false
            ], 500);
        }
    }

    public function restore($id)
    {
        try {
            $service = Service::withTrashed()->findOrFail($id);
            $service->restore();

            // Clear cache so restored service appears immediately
            $this->clearServicesCache();

            ActionLog::log('restore', "Restored service: {$service->name} (ID: {$service->id})", 'Service', $service->id);

            return response()->json([
                'message' => 'Service restored successfully',
                'data' => $service,
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to restore service',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
                'success' => false
            ], 500);
        }
    }

    public function getArchived()
    {
        try {
            $services = Service::onlyTrashed()->orderBy('deleted_at', 'desc')->get();
            
            return response()->json([
                'data' => $services,
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch archived services',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
                'success' => false
            ], 500);
        }
    }

    public function permanentDelete($id)
    {
        try {
            $service = Service::withTrashed()->findOrFail($id);
            $serviceName = $service->name;
            $serviceId = $service->id;
            $service->forceDelete();

            // Clear cache so deletion is reflected immediately
            $this->clearServicesCache();

            ActionLog::log('permanent_delete', "Permanently deleted service: {$serviceName} (ID: {$serviceId})", 'Service', $serviceId);

            return response()->json([
                'message' => 'Service permanently deleted',
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to permanently delete service',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
                'success' => false
            ], 500);
        }
    }

    // ==================== SERVICE AVAILABILITY MANAGEMENT ====================

    /**
     * Get predefined unavailability reason categories.
     */
    public function getReasonCategories()
    {
        return response()->json([
            'data' => ServiceUnavailability::REASON_CATEGORIES,
            'success' => true,
        ]);
    }

    /**
     * Get all unavailabilities for a service.
     */
    public function getUnavailabilities(Service $service)
    {
        try {
            $unavailabilities = $service->unavailabilities()
                ->with('creator:id,first_name,last_name')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'data' => $unavailabilities,
                'success' => true,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch unavailabilities',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
                'success' => false,
            ], 500);
        }
    }

    /**
     * Set a service as unavailable.
     */
    public function setUnavailable(Request $request, Service $service)
    {
        try {
            $request->validate([
                'reason' => 'required|string|max:500',
                'reason_category' => 'required|string|in:' . implode(',', array_keys(ServiceUnavailability::REASON_CATEGORIES)),
                'is_global' => 'required|boolean',
                'unavailable_from' => 'nullable|required_if:is_global,false|date',
                'unavailable_until' => 'nullable|required_if:is_global,false|date|after:unavailable_from',
            ]);

            $unavailability = ServiceUnavailability::create([
                'service_id' => $service->id,
                'reason' => $request->reason,
                'reason_category' => $request->reason_category,
                'is_global' => $request->is_global,
                'unavailable_from' => $request->is_global ? null : $request->unavailable_from,
                'unavailable_until' => $request->is_global ? null : $request->unavailable_until,
                'is_active' => true,
                'created_by' => $request->user()->id,
            ]);

            $this->clearServicesCache();

            ActionLog::log(
                'service_unavailable',
                "Marked service \"{$service->name}\" as unavailable: {$request->reason}",
                'Service',
                $service->id
            );

            return response()->json([
                'message' => "Service \"{$service->name}\" has been marked as unavailable",
                'data' => $unavailability->load('creator:id,first_name,last_name'),
                'success' => true,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'success' => false,
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to set service as unavailable',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
                'success' => false,
            ], 500);
        }
    }

    /**
     * Reactivate a service (deactivate an unavailability record).
     */
    public function setAvailable(Request $request, Service $service, ServiceUnavailability $unavailability)
    {
        try {
            if ($unavailability->service_id !== $service->id) {
                return response()->json([
                    'message' => 'Unavailability record does not belong to this service',
                    'success' => false,
                ], 403);
            }

            $unavailability->update(['is_active' => false]);

            $this->clearServicesCache();

            ActionLog::log(
                'service_available',
                "Reactivated service \"{$service->name}\" (removed unavailability: {$unavailability->reason})",
                'Service',
                $service->id
            );

            return response()->json([
                'message' => "Service \"{$service->name}\" is now available again",
                'data' => $unavailability,
                'success' => true,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to reactivate service',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
                'success' => false,
            ], 500);
        }
    }

    /**
     * Reactivate a service by deactivating ALL active unavailabilities at once.
     */
    public function setFullyAvailable(Request $request, Service $service)
    {
        try {
            $count = $service->unavailabilities()
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $this->clearServicesCache();

            ActionLog::log(
                'service_available',
                "Fully reactivated service \"{$service->name}\" ({$count} unavailability records cleared)",
                'Service',
                $service->id
            );

            return response()->json([
                'message' => "Service \"{$service->name}\" is now fully available",
                'cleared_count' => $count,
                'success' => true,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to reactivate service',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
                'success' => false,
            ], 500);
        }
    }

    /**
     * Update an existing unavailability record.
     */
    public function updateUnavailability(Request $request, Service $service, ServiceUnavailability $unavailability)
    {
        try {
            if ($unavailability->service_id !== $service->id) {
                return response()->json([
                    'message' => 'Unavailability record does not belong to this service',
                    'success' => false,
                ], 403);
            }

            $request->validate([
                'reason' => 'sometimes|required|string|max:500',
                'reason_category' => 'sometimes|required|string|in:' . implode(',', array_keys(ServiceUnavailability::REASON_CATEGORIES)),
                'is_global' => 'sometimes|required|boolean',
                'unavailable_from' => 'nullable|date',
                'unavailable_until' => 'nullable|date|after:unavailable_from',
                'is_active' => 'sometimes|boolean',
            ]);

            $unavailability->update($request->only([
                'reason', 'reason_category', 'is_global',
                'unavailable_from', 'unavailable_until', 'is_active',
            ]));

            $this->clearServicesCache();

            return response()->json([
                'message' => 'Unavailability updated successfully',
                'data' => $unavailability->fresh()->load('creator:id,first_name,last_name'),
                'success' => true,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'success' => false,
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update unavailability',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
                'success' => false,
            ], 500);
        }
    }

    /**
     * Delete an unavailability record permanently.
     */
    public function deleteUnavailability(Service $service, ServiceUnavailability $unavailability)
    {
        try {
            if ($unavailability->service_id !== $service->id) {
                return response()->json([
                    'message' => 'Unavailability record does not belong to this service',
                    'success' => false,
                ], 403);
            }

            $unavailability->delete();
            $this->clearServicesCache();

            return response()->json([
                'message' => 'Unavailability record deleted',
                'success' => true,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete unavailability',
                'error' => config('app.debug') ? $e->getMessage() : 'An internal error occurred',
                'success' => false,
            ], 500);
        }
    }
}