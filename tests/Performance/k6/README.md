# k6 性能测试

这里放 k6 v2 的性能检查，用于本地和 CI 的 smoke / mini-load 覆盖。

## 目录结构

- `config/` - 可复用的负载 profile 和阈值
- `lib/` - 共享请求封装
- `run.mjs` - 使用 Node.js 加载 `.env` 后执行本机 k6
- `scenarios/` - 可执行的 smoke 和 mini-load 场景

## 命令

```bash
pnpm run k6:smoke
pnpm run k6:mini-load
make k6-root-smoke
make k6-root-load
```

默认目标地址来自 `.env` 中的 `K6_BASE_URL`，并由 `config/profiles.js` 的 `performanceConfig.baseUrl` 读取。需要临时压测 RoadRunner 或其他环境时覆盖：

```bash
K6_BASE_URL=http://127.0.0.1:8001 pnpm run k6:smoke
```

`mini-load` 固定使用 `.env` 中的 `K6_MINI_LOAD_URL`，默认 `http://0.0.0.0:8001/api`，100 个 VU 持续 1 分钟。
