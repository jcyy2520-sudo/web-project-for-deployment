<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\ActionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ServiceController extends Controller
{
    public function index()
    {
        try {
            $services = Service::where('is_active', true)->orderBy('name')->get();
            
            return response()->json([
                'data' => $services,
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch services',
                'error' => $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    public function allServices()
    {
        try {
            // Get only active services for public use (booking, etc.)
            $services = Service::where('is_active', true)->orderBy('name')->get();
            
            // If no active services exist, sync from predefined appointment types
            if ($services->isEmpty()) {
                $this->syncDefaultAppointmentTypes();
                $services = Service::where('is_active', true)->orderBy('name')->get();
            }
            
            return response()->json([
                'data' => $services,
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch services',
                'error' => $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    /**
     * Get all services for admin panel (includes archived for manage view)
     */
    public function adminServices()
    {
        try {
            // Get all services (active and archived) for admin view
            $services = Service::withTrashed()->orderBy('name')->get();
            
            // If no services exist, sync from predefined appointment types
            if ($services->isEmpty()) {
                $this->syncDefaultAppointmentTypes();
                $services = Service::withTrashed()->orderBy('name')->get();
            }
            
            return response()->json([
                'data' => $services,
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch services',
                'error' => $e->getMessage(),
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
            // Get the predefined appointment types from the Appointment model
            $appointmentTypes = \App\Models\Appointment::getTypes();
            $servicesCreated = 0;

            // Create services for each appointment type
            foreach ($appointmentTypes as $key => $label) {
                if (!Service::where('name', $label)->exists()) {
                    Service::create([
                        'name' => $label,
                        'description' => "Predefined appointment type",
                        'price' => 0.00,
                        'duration' => 60,
                        'is_active' => true
                    ]);
                    $servicesCreated++;
                }
            }

            if ($servicesCreated > 0) {
                ActionLog::log('create', "Synced $servicesCreated default appointment types to services", 'Service', 0);
            }

            return response()->json([
                'message' => 'Default services synced successfully',
                'services_created' => $servicesCreated,
                'success' => true
            ]);
        } catch (\Exception $e) {
            \Log::error('Default service sync failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to sync default services',
                'error' => $e->getMessage(),
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
            // Get unique service_type values from appointments
            $appointmentServiceTypes = \DB::table('appointments')
                ->where('service_type', '!=', null)
                ->where('service_type', '!=', '')
                ->distinct()
                ->pluck('service_type')
                ->toArray();

            $servicesCreated = 0;
            
            // Create services for any that don't exist yet
            foreach ($appointmentServiceTypes as $serviceType) {
                if (!Service::where('name', $serviceType)->exists()) {
                    Service::create([
                        'name' => $serviceType,
                        'description' => "Service type from appointments",
                        'price' => 0.00,
                        'duration' => 60,
                        'is_active' => true
                    ]);
                    $servicesCreated++;
                }
            }

            if ($servicesCreated > 0) {
                ActionLog::log('create', "Synced $servicesCreated service types from appointments", 'Service', 0);
            }

            return response()->json([
                'message' => 'Services synced successfully',
                'services_created' => $servicesCreated,
                'success' => true
            ]);
        } catch (\Exception $e) {
            \Log::error('Service sync failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to sync services',
                'error' => $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    public function getStats()
    {
        try {
            // Get all services
            $services = Service::withTrashed()->get();
            
            $stats = $services->map(function($service) {
                // Count appointments by service_id (new way)
                $countByServiceId = \DB::table('appointments')
                    ->where('service_id', $service->id)
                    ->count();
                
                // Count appointments by service_type matching service name (legacy way)
                $countByServiceType = \DB::table('appointments')
                    ->where('service_type', $service->name)
                    ->count();
                
                // Total count is the sum of both methods
                $totalCount = $countByServiceId + $countByServiceType;
                
                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    'description' => $service->description,
                    'count' => $totalCount,
                    'is_active' => $service->is_active
                ];
            })
            ->filter(function($stat) {
                // Only return services that have been used (count > 0) or are active
                return $stat['count'] > 0 || $stat['is_active'];
            })
            ->sortByDesc('count')
            ->values();

            return response()->json([
                'data' => $stats,
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch service statistics',
                'error' => $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|unique:services,name|max:255',
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

            ActionLog::log('create', 'Created new service: ' . $service->name, 'Service', $service->id);

            // Clear stats cache when service is created
            $this->invalidateStatsCache();

            return response()->json([
                'message' => 'Service created successfully',
                'data' => $service,
                'success' => true
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create service',
                'error' => $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    public function update(Request $request, Service $service)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255|unique:services,name,' . $service->id,
                'description' => 'nullable|string|max:1000',
                'price' => 'nullable|numeric|min:0',
                'duration' => 'nullable|integer|min:15',
                'is_active' => 'boolean'
            ]);

            $oldName = $service->name;
            $oldPrice = $service->price;
            
            $service->update([
                'name' => $request->name,
                'description' => $request->description,
                'price' => $request->price,
                'duration' => $request->duration,
                'is_active' => $request->is_active ?? $service->is_active
            ]);

            ActionLog::log('update', "Updated service: $oldName -> {$service->name}" . ($oldPrice != $request->price ? " (Price: \$$oldPrice -> \${$request->price})" : ""), 'Service', $service->id);

            // Clear stats cache when service is updated (especially important for price changes)
            $this->invalidateStatsCache();

            return response()->json([
                'message' => 'Service updated successfully',
                'data' => $service,
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update service',
                'error' => $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    public function destroy(Service $service)
    {
        try {
            $serviceName = $service->name;
            $service->delete();

            ActionLog::log('delete', 'Deleted service: ' . $serviceName, 'Service', $service->id);

            // Clear stats cache when service is deleted
            $this->invalidateStatsCache();

            return response()->json([
                'message' => 'Service archived successfully',
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to archive service',
                'error' => $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    public function restore($id)
    {
        try {
            $service = Service::withTrashed()->findOrFail($id);
            $service->restore();

            ActionLog::log('restore', 'Restored service: ' . $service->name, 'Service', $service->id);

            // Clear stats cache when service is restored
            $this->invalidateStatsCache();

            return response()->json([
                'message' => 'Service restored successfully',
                'data' => $service,
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to restore service',
                'error' => $e->getMessage(),
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
                'error' => $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    public function permanentDelete($id)
    {
        try {
            $service = Service::withTrashed()->findOrFail($id);
            $serviceName = $service->name;
            $service->forceDelete();

            ActionLog::log('permanent_delete', 'Permanently deleted service: ' . $serviceName, 'Service', $id);

            // Clear stats cache when service is permanently deleted
            $this->invalidateStatsCache();

            return response()->json([
                'message' => 'Service permanently deleted',
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to permanently delete service',
                'error' => $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    /**
     * Invalidate all stats-related cache keys
     * This ensures that revenue calculations are updated when services change
     */
    private function invalidateStatsCache()
    {
        // Clear batch dashboard caches that include revenue calculations
        Cache::forget('admin_batch_dashboard_daily');
        Cache::forget('admin_batch_dashboard_weekly');
        Cache::forget('admin_batch_dashboard_monthly');
        Cache::forget('admin_batch_dashboard_yearly');
        Cache::forget('admin_batch_dashboard_all');
        
        // Clear the full dashboard load cache
        Cache::forget('admin_batch_full_load_daily');
        Cache::forget('admin_batch_full_load_weekly');
        Cache::forget('admin_batch_full_load_monthly');
        Cache::forget('admin_batch_full_load_yearly');
        Cache::forget('admin_batch_full_load_all');
    }
}
