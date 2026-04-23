param(
    [Parameter(Position = 0)]
    [string]$Task = "help",

    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]]$Args
)

$ErrorActionPreference = "Stop"

$projectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path

function Import-DeployEnv {
    $envFile = Join-Path $projectRoot ".env.deploy"

    if (-not (Test-Path $envFile)) {
        return
    }

    foreach ($line in Get-Content $envFile) {
        $trimmed = $line.Trim()

        if ($trimmed -eq "" -or $trimmed.StartsWith("#") -or -not $trimmed.Contains("=")) {
            continue
        }

        $key, $value = $trimmed.Split("=", 2)
        $value = $value.Trim().Trim('"').Trim("'")
        [Environment]::SetEnvironmentVariable($key.Trim(), $value, "Process")
    }
}

Import-DeployEnv

$composeFile = if ($env:COMPOSE_FILE) { $env:COMPOSE_FILE } else { Join-Path (Split-Path -Parent $projectRoot) "docker/compose.yaml" }
$service = if ($env:SERVICE) { $env:SERVICE } else { "photobooth-crm-php" }
$deployWorkDir = if ($env:DEPLOY_WORK_DIR) { $env:DEPLOY_WORK_DIR } else { ".deploy" }
$deployPublicDir = Join-Path (Join-Path $projectRoot $deployWorkDir) "public_html"

function Require-Command {
    param([Parameter(Mandatory = $true)][string]$Name)

    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) {
        throw "Missing $Name. Install it or add it to PATH."
    }
}

function Require-DeployEnv {
    Import-DeployEnv

    foreach ($name in @("SSH_HOST", "SSH_USER", "SSH_REMOTE_APP_DIR")) {
        if (-not [Environment]::GetEnvironmentVariable($name, "Process")) {
            throw "Missing $name in .env.deploy"
        }
    }

    Require-Command "ssh"
    Require-Command "rsync"
}

function Require-PublicDeployEnv {
    Require-DeployEnv

    foreach ($name in @("SSH_REMOTE_PUBLIC_DIR", "DEPLOY_SERVER_APP_PATH")) {
        if (-not [Environment]::GetEnvironmentVariable($name, "Process")) {
            throw "Missing $name in .env.deploy"
        }
    }
}

function Get-DeployLocalDir {
    if ($env:DEPLOY_LOCAL_DIR) {
        return $env:DEPLOY_LOCAL_DIR
    }

    return $projectRoot
}

function Get-SshPort {
    if ($env:SSH_PORT) {
        return $env:SSH_PORT
    }

    return "22"
}

function Invoke-Checked {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Command,

        [string[]]$CommandArgs = @()
    )

    & $Command @CommandArgs

    if ($LASTEXITCODE -ne 0) {
        exit $LASTEXITCODE
    }
}

function Invoke-Compose {
    param(
        [Parameter(Mandatory = $true)]
        [string[]]$ComposeCommand
    )

    Invoke-Checked -Command "docker" -CommandArgs (@("compose", "-f", $composeFile) + $ComposeCommand)
}

function Invoke-Artisan {
    param([Parameter(Mandatory = $true)][string[]]$ArtisanArgs)

    Invoke-Compose -ComposeCommand (@("exec", $service, "php", "artisan") + $ArtisanArgs)
}

function Get-PhpstanCommand {
    $windowsProxy = Join-Path $projectRoot "vendor/bin/phpstan.bat"
    $unixProxy = Join-Path $projectRoot "vendor/bin/phpstan"

    if (Test-Path $windowsProxy) {
        return $windowsProxy
    }

    return $unixProxy
}

function Invoke-DeployBuild {
    Push-Location $projectRoot
    try {
        Invoke-Checked -Command "composer" -CommandArgs @("install", "--no-dev", "--optimize-autoloader")
        Invoke-Checked -Command "npm" -CommandArgs @("install")
        Invoke-Checked -Command "npm" -CommandArgs @("run", "build")
        Invoke-Checked -Command "php" -CommandArgs @("artisan", "optimize:clear")
    }
    finally {
        Pop-Location
    }
}

function Invoke-DeployPublicPrepare {
    Require-PublicDeployEnv

    if (Test-Path $deployPublicDir) {
        Remove-Item $deployPublicDir -Recurse -Force
    }

    New-Item -ItemType Directory -Path $deployPublicDir -Force | Out-Null

    $sourcePublic = Join-Path (Get-DeployLocalDir) "public"
    Invoke-Checked -Command "rsync" -CommandArgs @("-a", "--exclude=storage/", "--exclude=hot", "$sourcePublic/", "$deployPublicDir/")

    $stubPath = Join-Path $projectRoot "deploy/hostinger-shared/index.php.stub"
    $indexPath = Join-Path $deployPublicDir "index.php"
    $indexContent = (Get-Content $stubPath -Raw).Replace("__APP_PATH__", $env:DEPLOY_SERVER_APP_PATH)
    Set-Content -Path $indexPath -Value $indexContent -NoNewline
}

function Invoke-DeploySsh {
    param([bool]$Delete = $false)

    Require-DeployEnv

    $port = Get-SshPort
    $remote = "$($env:SSH_USER)@$($env:SSH_HOST):$($env:SSH_REMOTE_APP_DIR)/"
    $local = "$(Get-DeployLocalDir)/"
    $rsyncArgs = @(
        "-avz",
        "-e", "ssh -p $port",
        "--exclude=.git/",
        "--exclude=.idea/",
        "--exclude=.deploy/",
        "--exclude=node_modules/",
        "--exclude=.env",
        "--exclude=.env.deploy",
        "--exclude=.env.prod",
        "--exclude=.phpunit.result.cache",
        "--exclude=public/hot",
        "--exclude=storage/logs/",
        "--exclude=storage/framework/cache/",
        "--exclude=storage/framework/sessions/",
        "--exclude=storage/framework/views/"
    )

    if ($Delete) {
        $rsyncArgs += "--delete"
    }

    Invoke-Checked -Command "ssh" -CommandArgs @("-p", $port, "$($env:SSH_USER)@$($env:SSH_HOST)", "mkdir -p $($env:SSH_REMOTE_APP_DIR)")
    Invoke-Checked -Command "rsync" -CommandArgs ($rsyncArgs + @($local, $remote))
}

function Invoke-DeployPublicSsh {
    param([bool]$Delete = $false)

    Invoke-DeployPublicPrepare

    $port = Get-SshPort
    $remote = "$($env:SSH_USER)@$($env:SSH_HOST):$($env:SSH_REMOTE_PUBLIC_DIR)/"
    $rsyncArgs = @(
        "-avz",
        "-e", "ssh -p $port",
        "--exclude=storage/",
        "--exclude=hot"
    )

    if ($Delete) {
        $rsyncArgs += "--delete"
    }

    Invoke-Checked -Command "ssh" -CommandArgs @("-p", $port, "$($env:SSH_USER)@$($env:SSH_HOST)", "mkdir -p $($env:SSH_REMOTE_PUBLIC_DIR)")
    Invoke-Checked -Command "rsync" -CommandArgs ($rsyncArgs + @("$deployPublicDir/", $remote))
}

function Show-Help {
    @"
Photobooth CRM Windows task runner

Usage
  .\make.ps1 <task> [args]
  .\make.cmd <task> [args]

Docker and Laravel
  help                  Show this help
  shell                 Open a shell inside the Laravel container
  ssh                   Alias for shell
  artisan ...           Run php artisan inside the Laravel container
  phpstan               Run ./vendor/bin/phpstan analyse --memory-limit=2G

Deployment
  deploy-build          Composer install, npm install, npm run build, optimize clear
  deploy-public-prepare Build the temporary .deploy/public_html folder
  deploy-ssh            Upload Laravel app with rsync over SSH
  deploy-ssh-public     Upload generated public_html with rsync over SSH
  deploy-ssh-all        Upload app and public_html without rebuilding
  deploy-all            Build, then upload app and public_html
  deploy-ssh-delete     Same as deploy-ssh, but deletes removed remote files
  deploy-ssh-public-delete
                        Same as deploy-ssh-public, but deletes removed remote files

Examples
  .\make.cmd shell
  .\make.cmd artisan migrate
  .\make.cmd phpstan
  .\make.cmd deploy-all
"@
}

switch ($Task.ToLowerInvariant()) {
    "help" {
        Show-Help
    }
    "shell" {
        Invoke-Compose -ComposeCommand @("exec", $service, "sh")
    }
    "ssh" {
        Invoke-Compose -ComposeCommand @("exec", $service, "sh")
    }
    "artisan" {
        if (-not $Args -or $Args.Count -eq 0) {
            throw "Provide an artisan command. Example: .\make.cmd artisan route:list"
        }

        Invoke-Artisan -ArtisanArgs $Args
    }
    "phpstan" {
        Push-Location $projectRoot
        try {
            Invoke-Checked -Command (Get-PhpstanCommand) -CommandArgs @("analyse", "--memory-limit=2G")
        }
        finally {
            Pop-Location
        }
    }
    "deploy-build" {
        Invoke-DeployBuild
    }
    "deploy-public-prepare" {
        Invoke-DeployPublicPrepare
    }
    "deploy-ssh" {
        Invoke-DeploySsh -Delete $false
    }
    "deploy-ssh-delete" {
        Invoke-DeploySsh -Delete $true
    }
    "deploy-ssh-public" {
        Invoke-DeployPublicSsh -Delete $false
    }
    "deploy-ssh-public-delete" {
        Invoke-DeployPublicSsh -Delete $true
    }
    "deploy-ssh-all" {
        Invoke-DeploySsh -Delete $false
        Invoke-DeployPublicSsh -Delete $false
    }
    "deploy-all" {
        Invoke-DeployBuild
        Invoke-DeploySsh -Delete $false
        Invoke-DeployPublicSsh -Delete $false
    }
    default {
        throw "Unknown task '$Task'. Run .\make.cmd help"
    }
}
