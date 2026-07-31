$ErrorActionPreference = 'Stop'

$SkipH5 = $args -contains '-SkipH5'
$ConfigPath = Join-Path $PSScriptRoot '..\config\production.psd1'
$Config = Import-PowerShellDataFile (Resolve-Path $ConfigPath)
$ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..\lhqb')).Path
$H5Root = Join-Path $ProjectRoot 'h5'
$H5Output = Join-Path $H5Root '.output\public'
$PublicApp = Join-Path $ProjectRoot 'public\app'
$SshKey = Join-Path $env:USERPROFILE $Config.SshKey
$Server = $Config.Server
$Domain = $Config.Domain
$ProjectName = $Config.ProjectName
$Stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$Archive = Join-Path $env:TEMP "$ProjectName-release-$Stamp.tar.gz"
$RemoteArchive = "/tmp/$ProjectName-release-$Stamp.tar.gz"

if (-not (Test-Path $SshKey)) {
    throw "SSH 私钥不存在：$SshKey"
}
if ($Domain -notmatch '^[A-Za-z0-9.-]+$') {
    throw "正式域名格式不正确：$Domain"
}

if (-not $SkipH5) {
    Write-Host '1/7 正在检查 H5 依赖...'
    $NpmCommand = Get-Command npm.cmd -ErrorAction SilentlyContinue
    if (-not $NpmCommand -and (Test-Path 'D:\nodejs\npm.cmd')) {
        $NpmCommand = Get-Item 'D:\nodejs\npm.cmd'
    }
    if (-not $NpmCommand) {
        throw '未找到 npm.cmd，请先安装 Node.js。'
    }
    $NpmPath = $NpmCommand.Source
    if (-not $NpmPath) {
        $NpmPath = $NpmCommand.FullName
    }
    if (-not (Test-Path (Join-Path $H5Root 'node_modules'))) {
        Push-Location $H5Root
        try {
            & $NpmPath ci
            if ($LASTEXITCODE -ne 0) { throw 'H5 依赖安装失败。' }
        }
        finally {
            Pop-Location
        }
    }

    Write-Host '2/7 正在构建 H5 并同步到 public/app...'
    Push-Location $H5Root
    try {
        & $NpmPath run generate
        if ($LASTEXITCODE -ne 0) { throw 'H5 构建失败。' }
    }
    finally {
        Pop-Location
    }
    if (-not (Test-Path (Join-Path $H5Output 'index.html'))) {
        throw "H5 构建产物不完整：$H5Output"
    }
    robocopy $H5Output $PublicApp /MIR /NFL /NDL /NJH /NJS /NP | Out-Null
    $RobocopyCode = $LASTEXITCODE
    if ($RobocopyCode -ge 8) {
        throw "H5 同步到 public/app 失败，robocopy 退出码：$RobocopyCode"
    }
} else {
    Write-Host '1-2/7 已跳过 H5 构建，保留 public/app 现有产物。'
}

Write-Host '3/7 正在打包项目源码...'
tar -C $ProjectRoot `
    --exclude='./data/conf' `
    --exclude='./data/runtime' `
    --exclude='./public/upload' `
    --exclude='./h5/node_modules' `
    --exclude='./h5/.output' `
    --exclude='./.git' `
    -czf $Archive .
if ($LASTEXITCODE -ne 0) { throw '项目打包失败。' }

try {
    Write-Host '4/7 正在上传发布包...'
    scp -i $SshKey -o BatchMode=yes $Archive "${Server}:$RemoteArchive"
    if ($LASTEXITCODE -ne 0) { throw '发布包上传失败。' }

    Write-Host '5/7 正在同步 lhqb 服务器部署命令...'
    $ServerScripts = @('lhqb-deploy.sh', 'lhqb-rollback.sh', 'lhqb-backup.sh', 'lhqb-health-check.sh')
    foreach ($ScriptName in $ServerScripts) {
        $LocalScript = Join-Path $PSScriptRoot $ScriptName
        scp -i $SshKey -o BatchMode=yes $LocalScript "${Server}:/tmp/$ScriptName"
        if ($LASTEXITCODE -ne 0) { throw "服务器脚本上传失败：$ScriptName" }
    }
    $DomainConfig = Join-Path $env:TEMP "$ProjectName-deploy.env"
    [IO.File]::WriteAllText($DomainConfig, "LHQB_DOMAIN=$Domain`n", [Text.UTF8Encoding]::new($false))
    scp -i $SshKey -o BatchMode=yes $DomainConfig "${Server}:/tmp/lhqb-deploy.env"
    Remove-Item -LiteralPath $DomainConfig -Force -ErrorAction SilentlyContinue
    if ($LASTEXITCODE -ne 0) { throw '正式域名配置上传失败。' }
    $PhpRuntimeConfig = Join-Path $PSScriptRoot '..\config\php-runtime.ini'
    scp -i $SshKey -o BatchMode=yes $PhpRuntimeConfig "${Server}:/tmp/lhqb-php-runtime.ini"
    if ($LASTEXITCODE -ne 0) { throw 'PHP 运行配置上传失败。' }
    ssh -i $SshKey -o BatchMode=yes $Server "install -m 0755 /tmp/lhqb-deploy.sh /usr/local/sbin/lhqb-deploy && install -m 0755 /tmp/lhqb-rollback.sh /usr/local/sbin/lhqb-rollback && install -m 0755 /tmp/lhqb-backup.sh /usr/local/sbin/lhqb-backup && install -m 0755 /tmp/lhqb-health-check.sh /usr/local/sbin/lhqb-health-check"
    if ($LASTEXITCODE -ne 0) { throw '服务器部署命令安装失败。' }
    ssh -i $SshKey -o BatchMode=yes $Server "mkdir -p /etc/lhqb && install -m 0644 /tmp/lhqb-deploy.env /etc/lhqb/deploy.env"
    if ($LASTEXITCODE -ne 0) { throw '服务器正式域名配置安装失败。' }
    ssh -i $SshKey -o BatchMode=yes $Server "install -m 0644 /tmp/lhqb-php-runtime.ini /etc/php/8.1/fpm/conf.d/99-lhqb.ini && install -m 0644 /tmp/lhqb-php-runtime.ini /etc/php/8.1/cli/conf.d/99-lhqb.ini"
    if ($LASTEXITCODE -ne 0) { throw '服务器 PHP 运行配置安装失败。' }
    $EmailConfig = Join-Path $ProjectRoot 'data\conf\email.php'
    if (-not (Test-Path $EmailConfig)) {
        throw "邮件配置不存在：$EmailConfig"
    }
    scp -i $SshKey -o BatchMode=yes $EmailConfig "${Server}:/tmp/lhqb-email.php"
    if ($LASTEXITCODE -ne 0) { throw '邮件配置上传失败。' }
    ssh -i $SshKey -o BatchMode=yes $Server "install -o root -g www-data -m 0640 /tmp/lhqb-email.php /data/lhqb/shared/data/conf/email.php && rm -f /tmp/lhqb-email.php"
    if ($LASTEXITCODE -ne 0) { throw '邮件配置安装失败。' }
    $MarketService = Join-Path $PSScriptRoot '..\systemd\lhqb-market.service'
    scp -i $SshKey -o BatchMode=yes $MarketService "${Server}:/tmp/lhqb-market.service"
    if ($LASTEXITCODE -ne 0) { throw '行情服务配置上传失败。' }
    ssh -i $SshKey -o BatchMode=yes $Server "mkdir -p /data/lhqb/logs/market /data/lhqb/shared/python/venvs && chown -R www-data:www-data /data/lhqb/logs/market && install -m 0644 /tmp/lhqb-market.service /etc/systemd/system/lhqb-market.service && systemctl daemon-reload && systemctl enable lhqb-market.service"
    if ($LASTEXITCODE -ne 0) { throw '行情服务安装失败。' }

    Write-Host '6/7 正在执行原子发布和自动验收...'
    ssh -i $SshKey -o BatchMode=yes $Server "'/usr/local/sbin/$ProjectName-deploy' '$RemoteArchive'"
    if ($LASTEXITCODE -ne 0) { throw '正式环境发布失败，服务器已尝试自动回滚。' }

    Write-Host "7/7 发布成功：https://$Domain"
}
finally {
    Remove-Item -LiteralPath $Archive -Force -ErrorAction SilentlyContinue
    ssh -i $SshKey -o BatchMode=yes $Server "rm -f -- '$RemoteArchive'" | Out-Null
}
