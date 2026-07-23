#!/usr/bin/env bash
# OpenPoly · M-01 一键跑（Git Bash / 任何 POSIX shell）
# 用法：bash run.sh

set -e
cd "$(dirname "$0")"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

info()  { echo -e "${CYAN}=== $1 ===${NC}"; }
ok()    { echo -e "${GREEN}  [OK]${NC} $1"; }
warn()  { echo -e "${YELLOW}  [WARN]${NC} $1"; }
err()   { echo -e "${RED}  [ERROR]${NC} $1"; }

# ----- 0. 环境检查 -----
info "0/5  环境检查"

if ! command -v composer >/dev/null 2>&1; then
  err "未检测到 composer"
  err "修复：PowerShell 跑 iwr -useb getcomposer.org/installer | iex"
  exit 1
fi
ok "composer: $(composer --version | head -n1)"

if ! command -v node >/dev/null 2>&1; then
  warn "未检测到 node，跳过 wp-env 步骤"
  HAS_NODE=0
else
  ok "node: $(node --version)"
  HAS_NODE=1
fi

if ! command -v docker >/dev/null 2>&1; then
  warn "未检测到 docker，wp-env 起不来"
  HAS_DOCKER=0
else
  ok "docker: $(docker --version)"
  HAS_DOCKER=1
fi

if ! command -v git >/dev/null 2>&1; then
  err "未检测到 git"
  exit 1
fi
ok "git: $(git --version)"

# ----- 1. composer install -----
info "1/5  装 Composer dev 依赖"
composer install --no-progress

# ----- 2. 4 项质量门禁 -----
info "2/5  4 项质量门禁"

echo "  --- lint ---"
composer lint

echo "  --- stan ---"
composer stan

echo "  --- test:unit ---"
composer test:unit

echo "  --- compat ---"
composer compat

# ----- 3. wp-env -----
info "3/5  wp-env 启动"
if [ "$HAS_NODE" -eq 0 ]; then
  warn "无 node，跳过"
elif [ "$HAS_DOCKER" -eq 0 ]; then
  warn "无 docker，wp-env 起不来"
else
  echo "  启动 WP 容器（首次约 2 分钟）..."
  npx wp-env start || warn "wp-env 启动失败，继续后续"
fi

# ----- 4. git commit -----
info "4/5  Git 第一次 commit"
if [ ! -d .git ]; then
  git init
  git branch -M main
fi
git add .
git commit -m "chore: M-01 仓库骨架与 wp-env 接入"

# ----- 5. push -----
info "5/5  推 GitHub"
if git remote -v | grep -q origin; then
  git push -u origin main || warn "push 失败，检查仓库/权限"
else
  echo "  [提示] 未配置 remote，需要你手动："
  echo "    1. 在 GitHub 创建空仓库 openpoly（不勾 README/.gitignore）"
  echo "    2. git remote add origin <你的仓库URL>"
  echo "    3. git push -u origin main"
fi

echo ""
echo -e "${GREEN}==> DONE. M-01 全部完成。${NC}"
