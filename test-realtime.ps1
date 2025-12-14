#!/usr/bin/env pwsh
# Real-Time Updates API Test Script
# Tests all realtime endpoints to verify they're working

param(
    [string]$BackendUrl = "http://localhost:8000",
    [string]$Date = "2025-12-15"
)

Write-Host "=== Real-Time Updates API Test ===" -ForegroundColor Cyan
Write-Host "Backend URL: $BackendUrl" -ForegroundColor Gray
Write-Host "Test Date: $Date" -ForegroundColor Gray
Write-Host ""

# Function to test endpoint
function Test-Endpoint {
    param(
        [string]$Name,
        [string]$Endpoint,
        [hashtable]$QueryParams = @{}
    )

    Write-Host "Testing: $Name" -ForegroundColor Yellow
    Write-Host "  Endpoint: $Endpoint" -ForegroundColor Gray
    
    try {
        # Build URL with query parameters
        $url = "$BackendUrl$Endpoint"
        if ($QueryParams.Count -gt 0) {
            $queryString = ($QueryParams.GetEnumerator() | ForEach-Object { "$($_.Key)=$($_.Value)" }) -join "&"
            $url = "$url`?$queryString"
            Write-Host "  Query: $queryString" -ForegroundColor Gray
        }

        # Make request
        $response = Invoke-WebRequest -Uri $url -Headers @{"Accept"="application/json"} -UseBasicParsing -TimeoutSec 5
        $json = $response.Content | ConvertFrom-Json

        # Check success
        if ($json.success) {
            Write-Host "  ✓ Status: SUCCESS" -ForegroundColor Green
            Write-Host "  ✓ Response: Valid JSON returned" -ForegroundColor Green
            
            # Show data summary
            if ($json.changes) {
                Write-Host "  ℹ Changes detected:" -ForegroundColor Cyan
                if ($json.changes.slot_capacities) {
                    Write-Host "    - Slot capacities: $($json.changes.slot_capacities.count) records" -ForegroundColor Gray
                }
                if ($json.changes.appointment_settings) {
                    Write-Host "    - Settings updated: $($json.changes.appointment_settings.updated)" -ForegroundColor Gray
                }
            }
            if ($json.data) {
                Write-Host "  ℹ Data count: $($json.data.Count) records" -ForegroundColor Gray
            }
        } else {
            Write-Host "  ✗ Status: FAILED" -ForegroundColor Red
            Write-Host "  Error: $($json.message)" -ForegroundColor Red
        }
    } catch {
        Write-Host "  ✗ Error: $($_.Exception.Message)" -ForegroundColor Red
        return $false
    }

    Write-Host ""
    return $true
}

# Test endpoints
$results = @()

Write-Host "--- Endpoint 1: Polling for Updates ---" -ForegroundColor Magenta
$lastCheck = (Get-Date).AddSeconds(-30).ToString("yyyy-MM-ddTHH:mm:ssZ")
$results += Test-Endpoint -Name "Updates Endpoint" -Endpoint "/api/realtime/updates" -QueryParams @{"last_check" = $lastCheck}

Write-Host "--- Endpoint 2: Get Slot Capacities ---" -ForegroundColor Magenta
$results += Test-Endpoint -Name "Slot Capacities" -Endpoint "/api/realtime/slot-capacities" -QueryParams @{"date" = $Date; "startTime" = "08:00"; "endTime" = "17:00"}

Write-Host "--- Endpoint 3: Get Appointment Settings ---" -ForegroundColor Magenta
$results += Test-Endpoint -Name "Appointment Settings" -Endpoint "/api/realtime/appointment-settings"

# Summary
Write-Host "=== Test Summary ===" -ForegroundColor Cyan
$passCount = ($results | Where-Object { $_ -eq $true }).Count
$failCount = ($results | Where-Object { $_ -eq $false }).Count

Write-Host "Passed: $passCount / $($results.Count)" -ForegroundColor Green
Write-Host "Failed: $failCount / $($results.Count)" -ForegroundColor $(if ($failCount -gt 0) { "Red" } else { "Green" })

if ($failCount -eq 0) {
    Write-Host ""
    Write-Host "All tests passed! Real-time API is working." -ForegroundColor Green
    Write-Host ""
    Write-Host "Next steps:" -ForegroundColor Yellow
    Write-Host "1. Open http://localhost:3000 in browser" -ForegroundColor Gray
    Write-Host "2. Login as admin and make a change" -ForegroundColor Gray
    Write-Host "3. Open second browser window as regular user" -ForegroundColor Gray
    Write-Host "4. Watch for automatic updates within 10 seconds" -ForegroundColor Gray
} else {
    Write-Host ""
    Write-Host "Some tests failed. Check the errors above." -ForegroundColor Red
    Write-Host ""
    Write-Host "Troubleshooting:" -ForegroundColor Yellow
    Write-Host "1. Verify backend server is running on port 8000" -ForegroundColor Gray
    Write-Host "2. Check RealtimeUpdateController.php exists" -ForegroundColor Gray
    Write-Host "3. Verify routes are registered in routes/api.php" -ForegroundColor Gray
}
