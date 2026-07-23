# OpenPoly

GPLv2 open-source WordPress multilingual plugin — WPML functional clone.

**Status:** early development (v0.5.0-dev, milestone MVP). Not yet released.

## Documentation

Full project documentation lives in `../docs/` (one level up):

- [`../docs/00-最终开发文档.md`](../docs/00-最终开发文档.md) — entry point
- [`../docs/01-需求规格说明书.md`](../docs/01-需求规格说明书.md) — PRD
- [`../docs/02-技术架构设计.md`](../docs/02-技术架构设计.md) — architecture
- [`../docs/03-数据库设计.md`](../docs/03-数据库设计.md) — schema
- [`../docs/04-模块拆解与开发路线图.md`](../docs/04-模块拆解与开发路线图.md) — roadmap
- [`../docs/05-开发环境与工具链.md`](../docs/05-开发环境与工具链.md) — tooling
- [`../docs/06-测试与发布策略.md`](../docs/06-测试与发布策略.md) — testing & release
- [`../docs/07-翻译网关与计量定价设计.md`](../docs/07-翻译网关与计量定价设计.md) — gateway
- [`../docs/08-竞品深度复盘与差异化策略.md`](../docs/08-竞品深度复盘与差异化策略.md) — competitive analysis
- [`../docs/CHANGELOG.md`](../docs/CHANGELOG.md) — changelog
- [`../docs/CONTRIBUTING.md`](../docs/CONTRIBUTING.md) — how to contribute
- [`../docs/SECURITY.md`](../docs/SECURITY.md) — security disclosure
- [`../docs/LEGAL.md`](../docs/LEGAL.md) — legal boundaries

## Quick start (5 minutes)

```bash
composer install
npm install
npx wp-env start
# Browse http://localhost:8888 — admin/password
```

## Quality gates

```bash
composer lint          # PHPCS
composer stan          # PHPStan level 6
composer test:unit     # PHPUnit (Brain Monkey)
composer compat        # PHPCompatibility 7.4-8.3
```

## License

GPLv2 or later. See [LICENSE](LICENSE) (TBD) and [`../docs/LEGAL.md`](../docs/LEGAL.md).
