# screenshot-upload.ps1

# Configuration
$apiToken = "YOUR_API_TOKEN_HERE"
$uploadUrl = "https://yourdomain.com/upload.php"
$tempFile = [System.IO.Path]::GetTempFileName() + ".png"

# Capture screenshot using built-in Windows capabilities
Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing

$screen = [System.Windows.Forms.Screen]::PrimaryScreen.Bounds
$bitmap = New-Object System.Drawing.Bitmap($screen.Width, $screen.Height)
$graphics = [System.Drawing.Graphics]::FromImage($bitmap)
$graphics.CopyFromScreen($screen.Location, [System.Drawing.Point]::Empty, $screen.Size)
$graphics.Dispose()

$bitmap.Save($tempFile, [System.Drawing.Imaging.ImageFormat]::Png)
$bitmap.Dispose()

# Check if file was created
if (-not (Test-Path $tempFile)) {
    [System.Windows.Forms.MessageBox]::Show("Screenshot capture failed", "Error")
    exit 1
}

# Upload file
$headers = @{
    "key" = $apiToken
}

try {
    $response = Invoke-RestMethod -Uri $uploadUrl -Method Post `
        -Headers $headers -Form @{"file" = Get-Item $tempFile} `
        -ErrorAction Stop
    
    if ($response.status -eq "success") {
        Set-Clipboard -Value $response.url
        [System.Windows.Forms.MessageBox]::Show("URL copied to clipboard:`n$($response.url)", "Success")
    } else {
        $errorMsg = $response.message ?? "Unknown error"
        [System.Windows.Forms.MessageBox]::Show("Upload failed: $errorMsg", "Error")
    }
} catch {
    $errorMsg = $_.Exception.Message
    [System.Windows.Forms.MessageBox]::Show("Upload failed: $errorMsg", "Error")
    exit 1
} finally {
    # Cleanup temporary file
    if (Test-Path $tempFile) {
        Remove-Item $tempFile -Force
    }
}
