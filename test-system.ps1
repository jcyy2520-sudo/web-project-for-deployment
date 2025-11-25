# System Testing Script for Windows PowerShell
# Tests both backend and frontend connectivity

Write-Host "================================" -ForegroundColor Cyan
Write-Host "  SYSTEM DEPLOYMENT TEST SUITE" -ForegroundColor Cyan
Write-Host "================================" -ForegroundColor Cyan
Write-Host ""

# Configuration
$renderBackendUrl = $args[0] -or "http://localhost:8000"
$vercelFrontendUrl = $args[1] -or "http://localhost:3000"

Write-Host "📋 Testing Configuration:" -ForegroundColor Yellow
Write-Host "  Backend URL: $renderBackendUrl"
Write-Host "  Frontend URL: $vercelFrontendUrl"
Write-Host ""

# Test counter
$testsPassed = 0
$testsFailed = 0

# Test function
function Test-Endpoint {
  param(
    [string]$name,
    [string]$method,
    [string]$url,
    [int]$expectedStatus
  )

  Write-Host -NoNewline "Testing $name... "
  
  try {
    $response = Invoke-WebRequest -Uri $url -Method $method -UseBasicParsing -ErrorAction Stop
    $statusCode = $response.StatusCode
    $body = $response.Content
  }
  catch {
    $statusCode = $_.Exception.Response.StatusCode.Value
    $body = $_.Exception.Response.Content
  }

  if ($statusCode -eq $expectedStatus) {
    Write-Host "✅ PASS" -ForegroundColor Green -NoNewline
    Write-Host " (Status: $statusCode)"
    return $true
  }
  else {
    Write-Host "❌ FAIL" -ForegroundColor Red -NoNewline
    Write-Host " (Status: $statusCode, Expected: $expectedStatus)"
    return $false
  }
}

Write-Host "🔧 BACKEND TESTS" -ForegroundColor Magenta
Write-Host "================" 
Write-Host ""

# Test health endpoint
if (Test-Endpoint "Backend Health" "GET" "$renderBackendUrl/api/health" 200) {
  $testsPassed++
} else {
  $testsFailed++
}

# Test CSRF token endpoint
if (Test-Endpoint "CSRF Token" "GET" "$renderBackendUrl/sanctum/csrf-cookie" 204) {
  $testsPassed++
} else {
  $testsFailed++
}

# Test user endpoint (should fail without auth)
if (Test-Endpoint "User Endpoint (No Auth)" "GET" "$renderBackendUrl/api/user" 401) {
  $testsPassed++
} else {
  $testsFailed++
}

# Try to get appointments
if (Test-Endpoint "Appointments List" "GET" "$renderBackendUrl/api/appointments" 401) {
  $testsPassed++
} else {
  $testsFailed++
}

Write-Host ""
Write-Host "💻 FRONTEND TESTS" -ForegroundColor Magenta
Write-Host "================"
Write-Host ""

# Test frontend is serving
if (Test-Endpoint "Frontend Home" "GET" "$vercelFrontendUrl/" 200) {
  $testsPassed++
} else {
  $testsFailed++
}

Write-Host ""
Write-Host "================================" -ForegroundColor Cyan
Write-Host "  TEST SUMMARY" -ForegroundColor Cyan
Write-Host "================================" -ForegroundColor Cyan
Write-Host "Passed: " -NoNewline
Write-Host "$testsPassed" -ForegroundColor Green
Write-Host "Failed: " -NoNewline
Write-Host "$testsFailed" -ForegroundColor Red
Write-Host ""

if ($testsFailed -eq 0) {
  Write-Host "✅ All tests passed!" -ForegroundColor Green
  exit 0
}
else {
  Write-Host "❌ Some tests failed" -ForegroundColor Red
  exit 1
}
