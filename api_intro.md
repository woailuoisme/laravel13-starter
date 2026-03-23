# 好小乖 API 接口文档

## 概述

好小乖 API 是一个 restful Web 服务，为系统提供全面的接口端点。本文档概述了整个 API 中使用的通信协议、身份验证方法和响应格式。

## 通信协议

- **协议**: 支持 HTTP/1.1、 HTTP/2 、HTTP/3
- **数据格式**: JSON
- **字符编码**: UTF-8
- **内容类型**: `application/json`

## 服务地址

| 环境   | API URL                 | 描述    |
|------|---------------------|-------|
| 测试环境 | `https://dev.haoxiaoguai.xyz/api/v1` | 测试环境  |
| 生产环境 | `https://rest.haoxiaoguai.xyz/api/v1` | 生产服务器 |

| 环境   | 文档 URL                 | 描述   |
|------|---------------------|------|
| 测试环境 | `https://dev.haoxiaoguai.xyz/docs` | 测试环境 |
| 生产环境 | `https://rest.haoxiaoguai.xyz/docs` | 生产服务器 |

| 环境   | 后台管理 URL                 | 描述    |
|------|---------------------|-------|
| 测试环境 | `https://dev.haoxiaoguai.xyz/admin` | 测试环境  |
| 生产环境 | `https://rest.haoxiaoguai.xyz/admin` | 生产服务器 |

## 身份验证

### JWT Bearer Token

API 使用 JWT（JSON Web Token）进行身份验证。在 Authorization 头中包含令牌：

```http
Authorization: Bearer {your-jwt-token}
```

### 必要的Http Header

所有 API 请求必须包含以下头部：

```http
Content-Type: application/json
Accept: application/json
Authorization: Bearer {token}  # 受保护的端点需要
```

## API 版本控制

API 使用 URL 路径版本控制来维持向后兼容性：

- **当前版本**: `v1`
- **URL 格式**: `{base_url}/api/v1/{endpoint}`
- **示例**: `https://api.lunchbox.com/api/v1/users`

## 响应格式

### 标准响应结构

所有 API 响应都遵循一致的 JSON 格式：

```json
{
    "success": true,
    "code": 200,
    "message": "操作成功完成",
    "data": {}
}
```

### 响应字段说明

| 字段        | 类型      | 必需 | 描述                           |
|-----------|---------|----|------------------------------|
| `success` | boolean | ✅  | 指示请求是否成功（`true`）或失败（`false`） |
| `code`    | integer | ✅  | HTTP 状态码或自定义code             |
| `message` | string  | ✅  | 结果的人类可读描述                    |
| `data`    | any     | ❌  | 响应载荷（对象、数组、字符串、数字或 null）     |
| `errors`  | any     | ❌  | 错误数据验证信息                     |

### 仅开发/测试环境字段

这些字段仅在开发和测试环境中包含：

| 字段          | 类型     | 描述          |
|-------------|--------|-------------|
| `debugger`  | array  | 性能和调试信息     |
| `exception` | string | 异常类名（发生错误时） |
| `trace`     | array  | 用于调试的堆栈跟踪信息 |

⚠️ **注意**: 出于安全考虑，调试字段在生产环境中会自动移除。

## 响应示例

### 成功响应

```json
{
    "success": true,
    "code": 200,
    "message": "用户信息获取成功",
    "data": {
        "id": 1,
        "name": "张三",
        "email": "zhangsan@example.com",
        "created_at": "2024-01-15 10:30:00"
    }
}
```

### 错误响应

```json
{
    "success": false,
    "message": "验证失败",
    "code": 422,
    "errors": {
        "email": [
            "邮箱字段是必需的。"
        ]
    }
}
```

### 分页响应

```json
{
    "success": true,
    "code": 200,
    "message": "success",
    "data": {
        "items": [
            {},
            {}
        ],
        "meta": {
            "current_page": 1,
            "per_page": 15,
            "last_page": 10,
            "has_more": true,
            "total": 150,
            "from": 1,
            "to": 15
        }
    }
}
```

## HTTP 状态码

API 使用标准的 HTTP 状态码：

| 状态码   | 含义                    | 描述         |
|-------|-----------------------|------------|
| `200` | OK                    | 请求成功       |
| `201` | Created               | 资源创建成功     |
| `204` | No Content            | 请求成功，无返回内容 |
| `400` | Bad Request           | 请求格式无效     |
| `401` | Unauthorized          | 需要身份验证     |
| `403` | Forbidden             | 访问被拒绝      |
| `404` | Not Found             | 资源未找到      |
| `422` | Unprocessable Entity  | 验证失败       |
| `429` | Too Many Requests     | 超出速率限制     |
| `500` | Internal Server Error | 服务器错误      |

## 速率限制

- **默认限制**: 每 IP 地址每分钟 60 次请求
- **已认证用户**: 每用户每分钟 100 次请求
- **速率限制头部**: 所有响应中包含
    - `X-RateLimit-Limit`: 允许的最大请求数
    - `X-RateLimit-Remaining`: 当前窗口内剩余请求数
    - `X-RateLimit-Reset`: 速率限制重置时间

## 错误处理

### 验证错误

```json
{
    "success": false,
    "code": 422,
    "message": "提交的数据无效。",
    "errors": {
        "email": [
            "邮箱字段是必需的。"
        ],
        "password": [
            "密码至少需要 8 个字符。"
        ]
    }
}
```

### 通用错误格式

```json
{
    "success": false,
    "code": 404,
    "message": "资源未找到",
    "data": null
}
```

## 分页

列表端点支持使用以下查询参数进行分页：

- `page`: 页码（默认: 1）
- `per_page`: 每页项目数（默认: 10，最大: 100）
- `sort`: 排序字段
- `order`: 排序方向（`asc` 或 `desc`）

**请求示例**：

```
# 组合过滤：状态为active + 用户名包含“张三” + 创建时间在2025年1月1日到2025年12月31日 + 分页+排序
GET /api/v1/users?page=2&per_page=20&sort=created_at&order=desc&filter[status]=active&filter[name]=张三&filter[created_at_start]=2025-01-01&filter[created_at_end]=2025-12-31
```

## 日期格式

所有日期时区PRC

```
2024-01-15 10:30:00
```

## 技术支持

如需 API 支持和咨询：

- **文档**: [API 文档门户]
- **问题反馈**: 在项目仓库中创建 issue
- **联系方式**: ailuoga166@gmail.com
