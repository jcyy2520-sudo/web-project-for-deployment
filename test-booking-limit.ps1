# Test appointment booking limit enforcement
# This script will attempt to book 3 appointments when limit is 2

# Get the auth token first
$loginResponse = curl -s -X POST "http://localhost:8000/api/login" `
  -H "Content-Type: application/json" `
  -d @"
{
  "email": "user@example.com",
  "password": "password123"
}
"@

$loginData = $loginResponse | ConvertFrom-Json
$token = $loginData.data.token

Write-Host "Token: $token`n"

# Get today's date
$today = (Get-Date).ToString("yyyy-MM-dd")

# Attempt to book appointment 1
Write-Host "Booking appointment 1..."
$response1 = curl -s -X POST "http://localhost:8000/api/appointments" `
  -H "Authorization: Bearer $token" `
  -H "Content-Type: application/json" `
  -d @"
{
  "type": "consultation",
  "appointment_date": "$today",
  "appointment_time": "09:00",
  "notes": "First appointment"
}
"@

Write-Host "Response 1: $response1`n"

# Attempt to book appointment 2
Write-Host "Booking appointment 2..."
$response2 = curl -s -X POST "http://localhost:8000/api/appointments" `
  -H "Authorization: Bearer $token" `
  -H "Content-Type: application/json" `
  -d @"
{
  "type": "document_review",
  "appointment_date": "$today",
  "appointment_time": "10:00",
  "notes": "Second appointment"
}
"@

Write-Host "Response 2: $response2`n"

# Attempt to book appointment 3 (should fail if limit is 2)
Write-Host "Booking appointment 3 (should fail if limit is 2)..."
$response3 = curl -s -X POST "http://localhost:8000/api/appointments" `
  -H "Authorization: Bearer $token" `
  -H "Content-Type: application/json" `
  -d @"
{
  "type": "court_representation",
  "appointment_date": "$today",
  "appointment_time": "11:00",
  "notes": "Third appointment"
}
"@

Write-Host "Response 3: $response3`n"

# Check the daily limit info
Write-Host "Checking daily limit info..."
$limitResponse = curl -s -X GET "http://localhost:8000/api/appointment-settings/user-limit/1/$today" `
  -H "Authorization: Bearer $token"

Write-Host "Limit Info Response: $limitResponse`n"
