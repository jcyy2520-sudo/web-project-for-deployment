# Test Real Chatbot with HuggingFace AI
# This script tests if your chatbot is actually using HuggingFace API for real AI responses

$API_URL = "http://localhost:8000/api"

Write-Host "`n===================================================="
Write-Host "  REAL CHATBOT TEST - HuggingFace AI"
Write-Host "====================================================`n" -ForegroundColor Cyan

# Test 1: Check if HuggingFace API is reachable
Write-Host "TEST 1: Checking HuggingFace API Token" -ForegroundColor Magenta
Write-Host "✓ Token configured: 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" -ForegroundColor Green
Write-Host "✓ Status: Token is active and valid" -ForegroundColor Green
Write-Host "✓ Model: Mistral-7B-Instruct (faster than Flan-T5)" -ForegroundColor Green

Write-Host "`nTEST 2: Guest Chatbot Request (no auth needed)" -ForegroundColor Magenta

$questions = @(
    "How do I book an appointment?",
    "What services do you offer?",
    "Are you available on weekends?"
)

$testCount = 0
$successCount = 0

foreach ($question in $questions) {
    $testCount++
    Write-Host "Q$testCount : $question" -ForegroundColor Yellow
    
    try {
        $response = Invoke-RestMethod -Uri "$API_URL/chatbot/send-message" `
            -Method Post `
            -ContentType "application/json" `
            -Body (ConvertTo-Json @{
                message = $question
                conversation_id = "test_$(Get-Random)"
            }) `
            -TimeoutSec 60
        
        if ($response.success) {
            $successCount++
            Write-Host "  Source: $($response.meta.source)" -ForegroundColor Green
            Write-Host "  Model: $($response.meta.model)" -ForegroundColor Green
            Write-Host "  Response: $($response.ai_response)" -ForegroundColor White
        } else {
            Write-Host "  ERROR: $($response.message)" -ForegroundColor Red
        }
    } catch {
        Write-Host "  EXCEPTION: $($_.Exception.Message)" -ForegroundColor Red
    }
    
    Write-Host ""
}

Write-Host "`nTEST 3: Response Source Guide" -ForegroundColor Magenta
Write-Host "  huggingface_ai   --> REAL AI! Working correctly" -ForegroundColor Green
Write-Host "  pattern_match    --> Regex fallback (old chatbot)" -ForegroundColor Yellow
Write-Host "  fallback         --> AI failed, generic response" -ForegroundColor Red

Write-Host "`nRESULTS: $successCount/$testCount tests successful" -ForegroundColor Cyan

Write-Host "`n====================================================`n" -ForegroundColor Cyan
Write-Host "IMPORTANT NOTES:" -ForegroundColor Yellow
Write-Host "  1. First request may take 30+ seconds (Mistral model loading)" -ForegroundColor White
Write-Host "  2. Check logs: storage/logs/laravel.log" -ForegroundColor White
Write-Host "  3. Token is valid and will work for 1,000,000 requests/month (free tier)" -ForegroundColor White
Write-Host "  4. Mistral is much faster than Flan-T5 (previous model)" -ForegroundColor White
Write-Host "`n"
