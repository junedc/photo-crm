param(
    [Parameter(Position = 0)]
    [string]$Task = "help",

    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]]$Args
)

$ErrorActionPreference = "Stop"

$projectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$dockerRoot = Join-Path (Split-Path -Parent $projectRoot) "memodocker"
$composeArgs = @("compose")
$appService = "memoshot-app"

function Invoke-Compose {
    param(
        [Parameter(Mandatory = $true)]
        [string[]]$ComposeCommand
    )

    Push-Location $dockerRoot
    try {
        & docker @composeArgs @ComposeCommand
        if ($LASTEXITCODE -ne 0) {
            exit $LASTEXITCODE
        }
    }
    finally {
        Pop-Location
    }
}

function Invoke-Artisan {
    param(
        [Parameter(Mandatory = $true)]
        [string[]]$ArtisanArgs
    )

    Invoke-Compose -ComposeCommand @("exec", $appService, "php", "artisan") + $ArtisanArgs
}

function Show-Help {
    @"
MemoShot Windows task runner

Usage
  .\make.ps1 <task> [args]
  .\make.cmd <task> [args]

Docker tasks
  help             Show this help
  up               Start the Docker stack
  build            Rebuild and start the Laravel app container
  down             Stop the Docker stack
  restart          Restart the Laravel app container
  ps               Show docker compose service status
  logs             Show recent Laravel app logs
  shell            Open a shell inside the Laravel container

Laravel artisan tasks
  artisan ...      Run any artisan command
  migrate          Run php artisan migrate --force
  fresh            Run php artisan migrate:fresh --seed --force
  test [path]      Run php artisan test, optionally with a test path
  tinker           Open php artisan tinker
  optimize-clear   Run php artisan optimize:clear
  storage-link     Run php artisan storage:link

Examples
  .\make.cmd up
  .\make.cmd shell
  .\make.cmd migrate
  .\make.cmd artisan route:list
  .\make.cmd test tests/Feature/BookingTest.php
"@
}

switch ($Task.ToLowerInvariant()) {
    "help" {
        Show-Help
    }
    "up" {
        Invoke-Compose -ComposeCommand @("up", "-d")
    }
    "build" {
        Invoke-Compose -ComposeCommand @("up", "-d", "--build", $appService)
    }
    "down" {
        Invoke-Compose -ComposeCommand @("down")
    }
    "restart" {
        Invoke-Compose -ComposeCommand @("restart", $appService)
    }
    "ps" {
        Invoke-Compose -ComposeCommand @("ps")
    }
    "logs" {
        Invoke-Compose -ComposeCommand @("logs", $appService, "--tail", "100")
    }
    "shell" {
        Invoke-Compose -ComposeCommand @("exec", $appService, "sh")
    }
    "artisan" {
        if (-not $Args -or $Args.Count -eq 0) {
            throw "Provide an artisan command. Example: .\make.cmd artisan route:list"
        }

        Invoke-Artisan -ArtisanArgs $Args
    }
    "migrate" {
        Invoke-Artisan -ArtisanArgs @("migrate", "--force")
    }
    "fresh" {
        Invoke-Artisan -ArtisanArgs @("migrate:fresh", "--seed", "--force")
    }
    "test" {
        if ($Args -and $Args.Count -gt 0) {
            Invoke-Artisan -ArtisanArgs @("test") + $Args
        }
        else {
            Invoke-Artisan -ArtisanArgs @("test")
        }
    }
    "tinker" {
        Invoke-Artisan -ArtisanArgs @("tinker")
    }
    "optimize-clear" {
        Invoke-Artisan -ArtisanArgs @("optimize:clear")
    }
    "storage-link" {
        Invoke-Artisan -ArtisanArgs @("storage:link")
    }
    default {
        throw "Unknown task '$Task'. Run .\make.cmd help"
    }
}
