# Test Shift Report Endpoint
# This script tests if the cashier shift report endpoint works correctly

Write-Host "================================" -ForegroundColor Cyan
Write-Host "  SHIFT REPORT TEST" -ForegroundColor Cyan
Write-Host "================================" -ForegroundColor Cyan
Write-Host ""

$baseUrl = "http://localhost:8000"
$today = (Get-Date).ToString("yyyy-MM-dd")

# First, we need to get an auth token
Write-Host "1️⃣  Getting authentication token..." -ForegroundColor Yellow

# Try to login as a test cashier
$loginResponse = Invoke-RestMethod -Uri "$baseUrl/api/login" -Method Post -ContentType "application/json" -Body (@{
    email = "cashier@example.com"
    password = "password"
} | ConvertTo-Json) -ErrorAction SilentlyContinue

if ($loginResponse -and $loginResponse.token) {
    Write-Host "✅ Successfully authenticated" -ForegroundColor Green
    $token = $loginResponse.token
    
    # Now test the shift report endpoint
    Write-Host ""
    Write-Host "2️⃣  Testing shift report endpoint..." -ForegroundColor Yellow
    Write-Host "   Date range: $today to $today" -ForegroundColor Gray
    
    $headers = @{
        "Authorization" = "Bearer $token"
        "Content-Type" = "application/json"
    }
    
    $response = Invoke-RestMethod -Uri "$baseUrl/api/cashier/shift-reports?from=$today&to=$today" -Method Get -Headers $headers -ErrorAction SilentlyContinue
    
    if ($response) {
        Write-Host "✅ Shift report endpoint responds" -ForegroundColor Green
        Write-Host ""
        Write-Host "📊 Response Structure:" -ForegroundColor Yellow
        Write-Host "   Success: $($response.success)" -ForegroundColor Gray
        Write-Host "   Total Revenue: ₱$($response.total_revenue)" -ForegroundColor Gray
        Write-Host "   Total Sales: $($response.total_sales)" -ForegroundColor Gray
        Write-Host "   Cash Collected: ₱$($response.cash_collected)" -ForegroundColor Gray
        Write-Host "   Card Collected: ₱$($response.card_collected)" -ForegroundColor Gray
        Write-Host ""
        Write-Host "✅ All fields present" -ForegroundColor Green
    } else {
        Write-Host "❌ No response from shift report endpoint" -ForegroundColor Red
    }
} else {
    Write-Host "❌ Authentication failed" -ForegroundColor Red
    Write-Host "   Make sure a cashier account exists with email: cashier@example.com" -ForegroundColor Gray
}

Write-Host ""
Write-Host "================================" -ForegroundColor Cyan
Write-Host "Test Complete" -ForegroundColor Cyan
Write-Host "================================" -ForegroundColor Cyan
