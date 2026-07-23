# OpenPoly · M-01 一键跑（PowerShell 兼容）
#
# 用法：
#   1. 在 PowerShell 中先执行：Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
#   2. 然后：.\run.ps1
#
# 或直接双击 run.bat

$ErrorActionPreference = 'Stop'
Set-Location -Path $PSScriptRoot

function Section($title) {
    Write-Host ""
    Write-Host "=== $title ===" -ForegroundColor Cyan
}

Section "1/5  装 Composer dev 依赖"
if (-not (Get-Command composer -ErrorAction SilentlyContinue)) {
    Write-Host "未检测到 composer，请先安装：iwr -useb getcomposer.org/installer | iex" -ForegroundColor Red
    exit 1
}
composer install --no-progress

Section "2/5  4 项质量门禁（lint / stan / test:unit / compat）"
composer lint
composer stan
composer test:unit
composer compat

Section "3/5  启动 wp-env（可选，需要 Docker）"
if (Get-Command npx.cmd -ErrorAction SilentlyContinue) {
    Write-Host "调用 npx wp-env start ..." -ForegroundColor Yellow
    npx.cmd wp-env start
} elseif (Get-Command npx -ErrorAction SilentlyContinue) {
    Write-Host "调用 npx wp-env start ..." -ForegroundColor Yellow
    npx wp-env start
} else {
    Write-Host "未检测到 npx，跳过 wp-env 启动（需要 Node 20+）" -ForegroundColor Yellow
}

Section "4/5  Git 第一次 commit"
if (-not (Test-Path .git)) {
    git init
    git branch -M main
}
git add .
git commit -m "chore: M-01 仓库骨架与 wp-env 接入

- openpoly.php 插件主文件 + 5 个质量配置
- src/Bootstrap/Activator.php 最小 Activator 桩
- tests/unit/Bootstrap/ActivatorTest.php 首个单测
- CI: lint.yml + test-unit.yml
- PR 模板 10 项校验清单"

Section "5/5  推到 GitHub（需要你确认 URL）"
$remote = gh repo view --json url -q .url 2>$null
if (-not $remote) {
    Write-Host "未配置 remote，请手动执行：git remote add origin <你的仓库URL>; git push -u origin main" -ForegroundColor Yellow
} else {
    git push -u origin main
}

Section "DONE"
Write-Host "M-01 全部完成。" -ForegroundColor Green
