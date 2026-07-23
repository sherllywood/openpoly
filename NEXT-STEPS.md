# M-01 落地完成 · 下一步怎么跑

> 状态：13 个文件已落地 → `D:\ML-tradyfox\openpoly\`
> 时间：2026-07-24
> 下一步：本地跑通 5 个质量门禁 + git init + 推到 GitHub

---

## 已落地的 15 个文件

```
D:\ML-tradyfox\openpoly\
├── openpoly.php                              # 插件主文件（GPL 头 + 常量 + 激活钩子）
├── composer.json                             # PSR-4 autoload + 7 个 dev 依赖
├── package.json                              # @wordpress/scripts
├── phpcs.xml                                 # WordPress-Core/Docs/Extra
├── phpstan.neon                              # level 6 + phpstan-wordpress
├── phpunit.xml                               # Brain Monkey testsuite
├── .wp-env.json                              # WP 6.5 + PHP 8.2
├── .editorconfig                             # tab + LF + UTF-8
├── .gitignore                                # 排除 vendor/node_modules/build 等
├── README.md                                 # 入口说明（指向 ../docs/）
├── src\Bootstrap\Activator.php               # 最小可运行 Activator（M-01 桩）
├── tests\unit\Bootstrap\ActivatorTest.php    # 1 个单测
└── .github\
    ├── workflows\lint.yml                    # PHPCS + PHPStan + Compat
    ├── workflows\test-unit.yml                # PHP 7.4/8.0/8.1/8.2/8.3 矩阵
    └── pull_request_template.md              # 10 项 PR 校验清单
```

---

## 你需要做的 5 步（在 PowerShell 或 Git Bash 都行）

### 第 1 步：本地依赖安装

```bash
cd D:\ML-tradyfox\openpoly
composer install
npm install
```

预期：composer 装 7 个 dev 依赖（phpcs / wpcs / phpcompatibility / phpstan / phpstan-wordpress / phpunit / brain/monkey），npm 装 @wordpress/scripts。

### 第 2 步：跑通 4 个质量门禁（必须全绿）

```bash
composer lint          # 1. PHPCS
composer stan          # 2. PHPStan level 6
composer test:unit     # 3. PHPUnit（应 1 个测试通过）
composer compat        # 4. PHPCompatibility 7.4-8.3
```

预期输出：
- `lint` 0 errors
- `stan` 0 errors
- `test:unit` OK (1 test, 1 assertion)
- `compat` 0 errors

**如果有任何一项报红**，把错误第一条贴回来，我即时修。

### 第 3 步：wp-env 启动 WP 站点

```bash
npx wp-env start
# 浏览器打开 http://localhost:8888
# 后台 admin / password
# 插件列表里看到 OpenPoly，激活
```

预期：激活后 `wp_options` 出现 `openpoly_schema_version = 0`。

验证命令：
```bash
npx wp-env run cli wp option get openpoly_schema_version
# 输出: 0
```

### 第 4 步：git init + 第一次 commit

```bash
cd D:\ML-tradyfox\openpoly
git init
git add .
git commit -m "chore: M-01 仓库骨架与 wp-env 接入

- openpoly.php 插件主文件 + 5 个质量配置
- src/Bootstrap/Activator.php 最小 Activator 桩
- tests/unit/Bootstrap/ActivatorTest.php 首个单测
- CI: lint.yml + test-unit.yml
- PR 模板 10 项校验清单"
git branch -M main
```

### 第 5 步：推到 GitHub（需要你创建空仓库）

```bash
# 在 GitHub 上创建空仓库 openpoly（不勾 README/.gitignore）
git remote add origin https://github.com/<your-org>/openpoly.git
git push -u origin main
```

推上去后 GitHub Actions 会自动跑 `lint.yml` + `test-unit.yml`，两条全绿就是 M-01 完成的硬证据。

---

## 失败回滚方案（任意一个出错）

| 步骤失败 | 怎么办 |
|---|---|
| `composer install` 失败 | 查 PHP 版本（要求 8.1+），锁文件冲突就 `rm -rf vendor composer.lock && composer install` |
| `lint` 报红 | `composer lint:fix` 自动修；修不掉的把错贴回来 |
| `stan` 报红 | 配置文件问题占 90%，把 `phpstan analyse` 输出贴回来 |
| `test:unit` 失败 | Brain Monkey 桩写错的话修正 ActivatorTest.php |
| `wp-env start` 起不来 | Docker 必须开；绑定 localhost:8888；若占用 `lsof -i :8888` 查谁占用 |
| `git push` 拒 | 仓库没建 / 没权限 / main 不是默认分支，按 GitHub 提示调整 |

**最坏情况**：所有文件都在，删仓库重来 5 分钟即可。

---

## 今天完成 M-01 为止的清单

- [ ] `composer install` 通过
- [ ] `npm install` 通过
- [ ] `composer lint` 通过
- [ ] `composer stan` 通过
- [ ] `composer test:unit` 通过（1 test）
- [ ] `composer compat` 通过
- [ ] `npx wp-env start` 启动成功
- [ ] 在 WP 后台激活 OpenPoly 插件
- [ ] `wp option get openpoly_schema_version` 返回 0
- [ ] `git init` + 第一次 commit
- [ ] 推到 GitHub，CI 两条 workflow 全绿

**11 步全绿 = M-01 完成。**

---

## 下一阶段：M-02（DI 容器 + Hook 注册器，2 人日）

M-01 合并后开新分支：

```bash
git checkout -b feature/M-02-di-container
```

按 02 架构 §2.2 §2.3 的伪代码实现：
- `src/Bootstrap/Container.php`（极简 DI 容器）
- `src/Bootstrap/Hookable.php`（Hook 声明接口）
- `src/Bootstrap/HookRegistrar.php`（统一消费）
- `src/Bootstrap/HookDefinition.php`（值对象）
- `src/Bootstrap/ServiceProvider.php`（模块注册规范）
- 对应单测 4-5 个

---

## 一个建议

第一次 push 前**先建本地分支**而不是直接发 main：

```bash
git checkout -b feature/M-01-bootstrap
git push -u origin feature/M-01-bootstrap
# GitHub 上开 PR (feature/M-01 → main)
# CI 跑通后再 merge 到 main
```

这给"出错可回滚"多一层保险。

---

— 完 —
