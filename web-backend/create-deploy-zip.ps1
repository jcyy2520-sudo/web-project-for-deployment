# Laravel Backend Deployment ZIP Creator
# This script creates a lean deployment-ready ZIP file for cPanel upload
# Excludes: vendor, node_modules, tests, .git, storage/logs, .github.zip

Write-Host "================================" -ForegroundColor Cyan
Write-Host "Backend Deployment ZIP Creator" -ForegroundColor Cyan
Write-Host "================================" -ForegroundColor Cyan
Write-Host ""

$backendPath = "C:\laragon\www\web\web-backend"
$zipName = "github-deploy.zip"
$zipPath = Join-Path $backendPath $zipName

# Check if backend path exists
if (-not (Test-Path $backendPath)) {
    Write-Host "ERROR: Backend path not found: $backendPath" -ForegroundColor Red
    exit 1
}

Write-Host "Backend path: $backendPath" -ForegroundColor Green
Write-Host "Output ZIP: $zipPath" -ForegroundColor Green
Write-Host ""

# Remove old zip if it exists
if (Test-Path $zipPath) {
    Write-Host "Removing old ZIP file..." -ForegroundColor Yellow
    Remove-Item $zipPath -Force
}

# Define directories and files to exclude
$excludePatterns = @(
    'vendor',
    'node_modules',
    'tests',
    '.git',
    '.github',
    '.github.zip',
    'storage\logs',
    'storage\framework\cache',
    '.phpunit.result.cache',
    'package-lock.json'
)

Write-Host "Scanning files to include..." -ForegroundColor Cyan

# Get all items to include
$filesToZip = @()
Get-ChildItem -Path $backendPath -Force -Recurse | Where-Object {
    $relPath = $_.FullName.Substring($backendPath.Length + 1)
    $shouldExclude = $false
    
    foreach ($pattern in $excludePatterns) {
        if ($relPath -like "$pattern*" -or $_.Name -eq $pattern) {
            $shouldExclude = $true
            break
        }
    }
    
    return -not $shouldExclude
} | ForEach-Object {
    $filesToZip += $_
}

$itemCount = ($filesToZip | Measure-Object).Count
Write-Host "Found $itemCount items to include" -ForegroundColor Green
Write-Host ""

# Create ZIP file
Write-Host "Creating ZIP file..." -ForegroundColor Cyan
try {
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    
    $zipFile = [System.IO.Compression.ZipFile]::Open($zipPath, 'Create')
    
    $fileCount = 0
    foreach ($item in $filesToZip) {
        $relPath = $item.FullName.Substring($backendPath.Length + 1)
        
        if ($item.PSIsContainer) {
            # Add directory entry
            [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zipFile, $item.FullName, $relPath + "/") | Out-Null
        } else {
            # Add file
            [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zipFile, $item.FullName, $relPath) | Out-Null
            $fileCount++
        }
        
        if ($fileCount % 100 -eq 0) {
            Write-Host "  Processed $fileCount files..." -ForegroundColor Gray
        }
    }
    
    $zipFile.Dispose()
    Write-Host "ZIP file created successfully!" -ForegroundColor Green
} catch {
    Write-Host "ERROR: Failed to create ZIP file: $_" -ForegroundColor Red
    exit 1
}

# Display file size
$zipSize = (Get-Item $zipPath).Length / 1GB
$roundedSize = [math]::Round($zipSize, 2)
Write-Host ""
Write-Host "================================" -ForegroundColor Cyan
Write-Host "Deployment ZIP Summary" -ForegroundColor Cyan
Write-Host "================================" -ForegroundColor Cyan
Write-Host "File: $zipName" -ForegroundColor Green
Write-Host "Size: $roundedSize GB" -ForegroundColor Green
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
