param(
    [string]$source_path,
    [string]$plugin_name,
    [string]$exclude
)

$sourcePath = $source_path
$destinationPath = Join-Path $sourcePath ($plugin_name + '.zip')

# Convert the exclude parameter into an array
$excludePatterns = $exclude -split ';' | Where-Object { $_ -ne '' }

# Define the function to create the zip file
function Add-Zip {
    param(
        [string]$sourcePath,
        [string]$destinationPath,
        [string[]]$excludePatterns,
        [string]$containerFolder
    )

    # Remove existing zip file if it exists
    if (Test-Path -Path $destinationPath) {
        Remove-Item -Path $destinationPath
    }

    # Create a temporary directory
    $tempDir = [System.IO.Path]::GetTempPath()
    $tempCopyPath = Join-Path $tempDir ([System.IO.Path]::GetRandomFileName())
    $containerPath = Join-Path $tempCopyPath $containerFolder

    # Create the container folder
    New-Item -ItemType Directory -Path $containerPath | Out-Null

    # Function to check if a path should be excluded
    function Should-Exclude {
        param (
            [string]$path,
            [string[]]$patterns
        )

        foreach ($pattern in $patterns) {
            if ($path -like "*$pattern*") {
                return $true
            }
        }
        return $false
    }

    # Copy source directory to the temporary folder, preserving structure
    $allItems = Get-ChildItem -Path $sourcePath -Recurse -Force
    foreach ($item in $allItems) {
        if (-not (Should-Exclude -path $item.FullName -patterns $excludePatterns)) {
            $destination = $item.FullName.Replace($sourcePath, $containerPath)
            if ($item.PSIsContainer) {
                if (-not (Test-Path -Path $destination)) {
                    New-Item -ItemType Directory -Path $destination -Force | Out-Null
                }
            } else {
                $parentDir = Split-Path -Parent $destination
                if (-not (Test-Path -Path $parentDir)) {
                    New-Item -ItemType Directory -Path $parentDir -Force | Out-Null
                }
                Copy-Item -Path $item.FullName -Destination $destination -Force
            }
        }
    }

    # Use 7-Zip to compress the container folder
    $sevenZipPath = "C:\Program Files\7-Zip\7z.exe"  # Adjust path if necessary
    $arguments = "a", "`"$destinationPath`"", "`"$containerPath`""

    try {
        & $sevenZipPath @arguments
        Write-Output "Zip file created successfully at $destinationPath"
    } catch {
        Write-Error "Failed to create zip file: $_"
    }

    # Clean up the temporary directory
    Remove-Item -Path $tempCopyPath -Recurse -Force
}

# Call the function with defined variables
Add-Zip -sourcePath $sourcePath -destinationPath $destinationPath -excludePatterns $excludePatterns -containerFolder $plugin_name
