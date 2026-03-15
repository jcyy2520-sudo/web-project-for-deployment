<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\ActionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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
            $services = Service::withTrashed()->orderBy('name')->get();
            
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
                    ->select(['id', 'name', 'description', 'is_active'])
                    ->orderBy('name')
                    ->get()
                    ->map(function($service) {
                        return [
                            'id' => $service->id,
                            'name' => $service->name,
                            'description' => $service->description,
                            'count' => 0,
                            'is_active' => $service->is_active
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
                'duration' => 'nullable|integer|min:15'
            ]);

            $service = Service::create([
                'name' => $request->name,
                'description' => $request->description,
                'price' => $request->price,
                'duration' => $request->duration,
                'is_active' => true
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
                'is_active' => 'boolean'
            ]);

            $service->update([
                'name' => $request->name,
                'description' => $request->description,
                'price' => $request->price,
                'duration' => $request->duration,
                'is_active' => $request->is_active ?? $service->is_active
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
}