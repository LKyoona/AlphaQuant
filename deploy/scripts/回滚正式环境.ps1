$ErrorActionPreference = 'Stop'

$ConfigPath = Join-Path $PSScriptRoot '..\config\production.psd1'
$Config = Import-PowerShellDataFile (Resolve-Path $ConfigPath)
$SshKey = Join-Path $env:USERPROFILE $Config.SshKey
$Server = $Config.Server
$Domain = $Config.Domain
$ProjectName = $Config.ProjectName

if (-not (Test-Path $SshKey)) {
    throw "SSH 私钥不存在：$SshKey"
}

ssh -i $SshKey -o BatchMode=yes $Server "/usr/local/sbin/$ProjectName-rollback"
if ($LASTEXITCODE -ne 0) {
    throw '回滚失败，服务器已尝试恢复回滚前版本。'
}

Write-Host "回滚成功：https://$Domain"
