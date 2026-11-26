param(
    [string]$FtpHost = 'ftpupload.net',
    [string]$FtpUser = $env:BBA_FTP_USER,
    [string]$FtpPass = $env:BBA_FTP_PASS,
    [string]$RemoteHtdocs = '/htdocs',
    [string]$RemotePrivate = '/private',
    [switch]$DryRun
)

$ErrorActionPreference = 'Stop'

if (-not $FtpUser) {
    $FtpUser = Read-Host 'FTP user'
}
if (-not $FtpPass) {
    $FtpPass = Read-Host 'FTP password' -AsSecureString | ConvertFrom-SecureString -AsPlainText
}

$root = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$map = @(
    @{ Local = (Join-Path $root 'htdocs'); Remote = $RemoteHtdocs },
    @{ Local = (Join-Path $root 'private'); Remote = $RemotePrivate }
)

$uploaded = 0
foreach ($entry in $map) {
    if (-not (Test-Path -LiteralPath $entry.Local)) {
        throw "Missing directory: $($entry.Local)"
    }

    Get-ChildItem -LiteralPath $entry.Local -Recurse -File | ForEach-Object {
        $file = $_
        if ($file.Name -eq 'config.local.php' -or $file.Name -like '.git*') {
            return
        }
        if ($file.FullName -like '*\storage\logs\*' -and $file.Name -ne '.gitkeep') {
            return
        }

        $relative = $file.FullName.Substring($entry.Local.Length).Replace('\', '/').TrimStart('/')
        $url = 'ftp://' + $FtpHost + $entry.Remote.TrimEnd('/') + '/' + $relative

        if ($DryRun) {
            Write-Host "dry-run $url"
            return
        }

        curl.exe --silent --show-error --fail --ftp-create-dirs --user "${FtpUser}:${FtpPass}" --upload-file $file.FullName $url
        if ($LASTEXITCODE -ne 0) {
            throw "Upload failed: $url"
        }

        $script:uploaded++
        Write-Host "uploaded $relative"
    }
}

if ($DryRun) {
    Write-Host 'Dry run only; nothing was uploaded.'
} else {
    Write-Host "Done. $uploaded files uploaded to $FtpHost."
}
