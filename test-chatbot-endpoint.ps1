# Test the chatbot sendMessage endpoint
$baseUrl = "http://127.0.0.1:8000"

# Test guest message (no auth)
Write-Host "Testing Chatbot Endpoint..." -ForegroundColor Green
Write-Host "================================" -ForegroundColor Green

$payload = @{
    message = "How do I book an appointment?"
    conversation_id = "test_conv_001"
} | ConvertTo-Json

Write-Host "`nTest 1: Guest User (No Auth)" -ForegroundColor Cyan
Write-Host "Payload: $payload"

try {
    $response = Invoke-WebRequest -Uri "$baseUrl/api/chatbot/send" `
        -Method POST `
        -Body $payload `
        -ContentType "application/json" `
        -ErrorAction SilentlyContinue
    
    Write-Host "Status: $($response.StatusCode)" -ForegroundColor Green
    Write-Host "Response:" -ForegroundColor Green
    $response.Content | ConvertFrom-Json | ConvertTo-Json -Depth 3 | Write-Host
} catch {
    Write-Host "Error Status: $($_.Exception.Response.StatusCode)" -ForegroundColor Red
    Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
    if ($_.ErrorDetails) {
        Write-Host "Details: $($_.ErrorDetails)" -ForegroundColor Red
    }
}

Write-Host "`n================================" -ForegroundColor Green
Write-Host "Test Complete" -ForegroundColor Green
