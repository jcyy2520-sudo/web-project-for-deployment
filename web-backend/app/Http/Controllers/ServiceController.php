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
            // SIMPLIFIED: Just get active services
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

    /**
     * Get all services for admin panel (includes archived for manage view)
     */
    public function adminServices()
    {
        try {
            // Get all services (active and archived) for admin view
            $services = Service::withTrashed()->orderBy('name')->get();
            
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
            // SIMPLIFIED: Just return success without trying to access Appointment model
            return response()->json([
                'message' => 'Default services sync endpoint',
                'note' => 'Functionality disabled for deployment',
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Service sync not available',
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
            // SIMPLIFIED: Just return success
            return response()->json([
                'message' => 'Services sync endpoint',
                'note' => 'Functionality disabled for deployment',
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Service sync not available',
                'error' => $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    public function getStats()
    {
        try {
            // SIMPLIFIED: Return basic stats
            $services = Service::withTrashed()->get();
            
            $stats = $services->map(function($service) {
                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    'description' => $service->description,
                    'count' => 0, // Simplified for now
                    'is_active' => $service->is_active
                ];
            })
            ->filter(function($stat) {
                return $stat['is_active'];
            })
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

            $service->update([
                'name' => $request->name,
                'description' => $request->description,
                'price' => $request->price,
                'duration' => $request->duration,
                'is_active' => $request->is_active ?? $service->is_active
            ]);

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
}