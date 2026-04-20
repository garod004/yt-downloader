param(
    [string]$Hora = "02:00",
    [string]$TaskName = "RealAssessoriaBackupDiario"
)

$ErrorActionPreference = "Stop"

$projectDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$phpScript = Join-Path $projectDir "executar_backup_agendado.php"

if (-not (Test-Path $phpScript)) {
    throw "Arquivo nao encontrado: $phpScript"
}

$phpCmd = Get-Command php -ErrorAction SilentlyContinue
if ($null -eq $phpCmd) {
    $phpCandidates = @(
        "C:\\wamp64\\bin\\php\\php8.2.0\\php.exe",
        "C:\\wamp64\\bin\\php\\php8.1.0\\php.exe",
        "C:\\wamp64\\bin\\php\\php8.0.0\\php.exe",
        "C:\\wamp64\\bin\\php\\php7.4.0\\php.exe",
        "C:\\wamp64\\bin\\php\\php7.3.0\\php.exe"
    )

    $phpPath = $phpCandidates | Where-Object { Test-Path $_ } | Select-Object -First 1
    if ([string]::IsNullOrWhiteSpace($phpPath)) {
        throw "Nao foi possivel localizar o php.exe. Adicione o PHP no PATH ou ajuste o script."
    }
} else {
    $phpPath = $phpCmd.Source
}

# Valida formato HH:mm
if ($Hora -notmatch '^([01][0-9]|2[0-3]):[0-5][0-9]$') {
    throw "Hora invalida. Use o formato HH:mm, por exemplo 02:00"
}

$action = New-ScheduledTaskAction -Execute $phpPath -Argument "`"$phpScript`""
$trigger = New-ScheduledTaskTrigger -Daily -At $Hora
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -MultipleInstances IgnoreNew

try {
    $principalSystem = New-ScheduledTaskPrincipal -UserId "SYSTEM" -LogonType ServiceAccount -RunLevel Limited
    Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger $trigger -Settings $settings -Principal $principalSystem -Description "Backup diario automatico do banco de dados do sistema RealAssessoria" -Force | Out-Null
    Write-Output "Tarefa criada/atualizada com sucesso (conta SYSTEM)."
    Write-Output "Nome: $TaskName"
    Write-Output "Horario diario: $Hora"
    Write-Output "Comando: $phpPath \"$phpScript\""
} catch {
    try {
        $currentUser = "$env:USERDOMAIN\\$env:USERNAME"
        $principalUser = New-ScheduledTaskPrincipal -UserId $currentUser -LogonType Interactive -RunLevel Limited
        Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger $trigger -Settings $settings -Principal $principalUser -Description "Backup diario automatico do banco de dados do sistema RealAssessoria" -Force | Out-Null
        Write-Output "Tarefa criada/atualizada com sucesso (usuario atual, somente quando logado)."
        Write-Output "Nome: $TaskName"
        Write-Output "Horario diario: $Hora"
        Write-Output "Comando: $phpPath \"$phpScript\""
    } catch {
        $taskQuery = schtasks /Query /TN $TaskName 2>$null
        if ($LASTEXITCODE -eq 0) {
            Write-Output "Sem permissao para atualizar a tarefa, mas a tarefa existente foi mantida: $TaskName"
            Write-Output "Verifique se o horario esta correto em: Agendador de Tarefas > $TaskName"
        } else {
            throw "Falha ao registrar a tarefa agendada: $($_.Exception.Message)"
        }
    }
}
