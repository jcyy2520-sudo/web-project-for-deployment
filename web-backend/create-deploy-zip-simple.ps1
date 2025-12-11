# Simple Laravel Backend Deployment ZIP Creator
# Uses 7-Zip for reliable compression

$backendPath = "C:\laragon\www\web\web-backend"
$zipName = "github-deploy.zip"
$zipPath = Join-Path $backendPath $zipName

Write-Host "================================" -ForegroundColor Cyan
Write-Host "Backend Deployment ZIP Creator" -ForegroundColor Cyan
Write-Host "================================" -ForegroundColor Cyan
Write-Host ""

# Check if backend path exists
if (-not (Test-Path $backendPath)) {
    Write-Host "ERROR: Backend path not found: $backendPath" -ForegroundColor Red
    exit 1
}

# Remove old zip if it exists
if (Test-Path $zipPath) {
    Write-Host "Removing old ZIP file..." -ForegroundColor Yellow
    Remove-Item $zipPath -Force
}

Write-Host "Creating ZIP file (excluding unnecessary directories)..." -ForegroundColor Cyan
Write-Host ""

# Try to use 7-Zip if available
$sevenZipPath = "C:\Program Files\7-Zip\7z.exe"

if (Test-Path $sevenZipPath) {
    Write-Host "Using 7-Zip for compression..." -ForegroundColor Green
    
    Push-Location $backendPath
    
    & $sevenZipPath a -r "$zipPath" "*" `
        -x!vendor `
        -x!node_modules `
        -x!tests `
        -x!.git `
        -x!.github `
        -x!"storage\logs" `
        -x!"storage\framework\cache" `
        -x!".phpunit.result.cache" `
        -x!"github-deploy.zip" | Out-Null
    
    Pop-Location
    
} else {
    Write-Host "7-Zip not found, using PowerShell compression..." -ForegroundColor Yellow
    
    # List of items to exclude
    $excludeList = @('vendor', 'node_modules', 'tests', '.git', '.github', 'storage\logs', 'storage\framework\cache', '.phpunit.result.cache', 'github-deploy.zip')
    
    # Create temporary folder for filtering
    $tempFolder = Join-Path $env:TEMP "backend-deploy-temp"
    
    if (Test-Path $tempFolder) {
        Remove-Item $tempFolder -Recurse -Force
    }
    New-Item -ItemType Directory -Path $tempFolder | Out-Null
    
    Write-Host "Copying files to temporary location..." -ForegroundColor Gray
    
    # Copy files, excluding the ones we don't need
    Get-ChildItem -Path $backendPath -Force | ForEach-Object {
        if ($_.Name -notin $excludeList) {
            Copy-Item -Path $_.FullName -Destination (Join-Path $tempFolder $_.Name) -Recurse -Force -ErrorAction SilentlyContinue
        }
    }
    
    Write-Host "Compressing files..." -ForegroundColor Gray
    
    # Compress the temp folder
    Compress-Archive -Path "$tempFolder\*" -DestinationPath $zipPath -Force
    
    # Cleanup
    Remove-Item $tempFolder -Recurse -Force
}

# Display file size
if (Test-Path $zipPath) {
    $zipSize = (Get-Item $zipPath).Length / 1GB
    $roundedSize = [math]::Round($zipSize, 2)
    $sizeMB = [math]::Round((Get-Item $zipPath).Length / 1MB, 2)
    
    Write-Host ""
    Write-Host "================================" -ForegroundColor Cyan
    Write-Host "Deployment ZIP Summary" -ForegroundColor Cyan
    Write-Host "================================" -ForegroundColor Cyan
    Write-Host "File: $zipName" -ForegroundColor Green
    Write-Host "Size: $sizeMB MB ($roundedSize GB)" -ForegroundColor Green
    Write-Host "Location: $zipPath" -ForegroundColor Green
    Write-Host ""
    
    if ($zipSize -le 1.17) {
        Write-Host "SUCCESS! ZIP is ready for cPanel upload (fits within 1.17 GB limit)" -ForegroundColor Green
    } else {
        Write-Host "WARNING: ZIP size is $roundedSize GB, which exceeds 1.17 GB limit" -ForegroundColor Yellow
    }
    
    Write-Host ""
    Write-Host "Next Steps:" -ForegroundColor Cyan
    Write-Host "1. Upload $zipName to cPanel File Manager" -ForegroundColor White
    Write-Host "2. Extract ZIP in your public_html folder" -ForegroundColor White
    Write-Host "3. In cPanel Terminal, run: composer install --no-dev --optimize-autoloader" -ForegroundColor White
    Write-Host "4. Update .env file with production credentials" -ForegroundColor White
    Write-Host "5. Run migrations: php artisan migrate --force" -ForegroundColor White
    Write-Host ""
    
    Write-Host "Process complete!" -ForegroundColor Green
} else {
    Write-Host "ERROR: ZIP file was not created" -ForegroundColor Red
    exit 1
}
