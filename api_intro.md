# 接口说明

## 概述

本文档定义了通信协议、身份验证、响应格式及速率限制等关键技术规范。

## 通信协议

- **协议**: 支持 HTTP/1.1、HTTP/2 及 HTTP/3 (QUIC)
- **数据格式**: JSON
- **字符编码**: UTF-8
- **内容类型**: `application/json`

## 服务地址

| 环境     | URL                                    | 描述         |
| -------- | -------------------------------------- | ------------ |
| 测试环境 | `https://dev.haoxiaoguai.xyz/api/v1`   | 测试服务器   |
| 生产环境 | `https://rest.haoxiaoguai.xyz/api/v1`  | 正式服务器   |
| API 文档 | `https://rest.haoxiaoguai.xyz/docs`    | 在线接口文档 |
| 后台管理 | `https://rest.haoxiaoguai.xyz/admin`   | 系统管理后台 |

## 身份验证

### JWT Bearer Token

API 使用 **JWT (JSON Web Token)** 进行身份验证。受保护的接口需要在 HTTP Header 中包含：

```http
Authorization: Bearer <your-jwt-token>
```

> [!TIP]
>
> 1. 您可以通过 `api/v1/auth/login` 接口获取 Token。
> 2. Token 有效期及刷新策略请参考“用户认证”节下的具体说明。

### 必须的 HTTP Header

所有 API 请求均应包含以下头部以确保正确的交互：

```http
Content-Type: application/json
Accept: application/json
Authorization: Bearer <token>  # 仅受保护接口需要
```

## API 版本控制

API 采用 URL 路径版本控制，以确保向后兼容性：

- **当前版本**: `v1`
- **URL 格式**: `{base_url}/api/v1/{endpoint}`
- **示例**: `https://rest.haoxiaoguai.xyz/api/v1/users`

## 响应格式

### 标准响应结构

所有响应均遵循一致的 JSON 格式，便于前端解析：

```json
{
    "success": true,
    "code": 200,
    "message": "操作成功完成",
    "data": {}
}
```

### 响应字段说明

| 字段      | 类型    | 必需 | 描述                                     |
| :-------- | :------ | :--- | :--------------------------------------- |
| `success` | boolean | ✅    | 表示业务处理是否成功（`true`/`false`）   |
| `code`    | integer | ✅    | 业务状态码（详见下表）                   |
| `message` | string  | ✅    | 针对结果的人类可读描述                   |
| `data`    | any     | ❌    | 响应数据（对象、数组或空）               |
| `errors`  | object  | ❌    | 验证失败时的具体字段错误信息             |

---

## 业务状态码 (Code) 说明

`code` 字段用于标识具体的业务执行结果。当 `success` 为 `false` 时，前端应根据 `code` 执行相应的逻辑。

| Code  | 描述                  | 建议处理方式                            |
| :---- | :-------------------- | :-------------------------------------- |
| `200` | **OK**                | 正常处理数据                            |
| `201` | **Created**           | 提示创建成功                            |
| `400` | **Bad Request**       | 通用错误提示                            |
| `401` | **Unauthorized**      | 跳转登录页面 / 清除本地 Token           |
| `403` | **Forbidden**         | 提示权限不足                            |
| `404` | **Not Found**         | 提示资源不存在                          |
| `422` | **Validation Failed** | 在表单下方展示 `errors` 字段中的错误    |
| `429` | **Too Many Requests** | 提示稍后再试                            |
| `500` | **Internal Error**    | 提示系统繁忙                            |

> [!NOTE]
> 在本项目中，默认情况下 `code` 与 HTTP 状态码保持一致。如果未来引入特定业务错误（如：余额不足、邀请码失效），将在此处补充 5 位数的自定义错误码。

---

### 调试模式 (仅限非生产环境)

在开发/测试模式下，响应中可能包含调试字段：

| 字段        | 类型    | 描述                                   |
| :---------- | :------ | :------------------------------------- |
| `debug`     | object  | 包含数据库查询、缓存命中等性能调试信息 |
| `exception` | string  | 发生错误时的异常类名                   |
| `trace`     | array   | 调试用的代码堆栈跟踪信息               |

> [!WARNING]
> 出于安全考虑，生产环境会自动移除所有调试相关字段。

## 列表查询规范

本 API 列表端点支持功能强大的过滤、排序和关联加载功能。

### 1. 过滤 (Filtering)

使用 `filter[字段名]` 语法进行筛选：

- **基本过滤**: `GET /users?filter[name]=张三`
- **多个过滤**: `GET /users?filter[name]=张三&filter[status]=active`
- **范围过滤** (如适用): `GET /orders?filter[created_at_start]=2024-01-01&filter[created_at_end]=2024-01-31`

### 2. 排序 (Sorting)

使用 `sort` 参数进行排序：

- **升序**: `GET /users?sort=id`
- **降序**: `GET /users?sort=-id` (字段名前加 `-`)
- **多字段排序**: `GET /users?sort=-id,name`

### 3. 关联加载 (Inclusion)

使用 `include` 参数加载关联资源（如：获取用户时顺便获取他的角色）：

- **加载关联**: `GET /users?include=roles,profile`

### 4. 字段筛选 (Fields Scaling)

使用 `fields[表名]` 仅返回指定字段：

- **限定字段**: `GET /users?fields[users]=id,nickname,email`

### 5. 分页 (Pagination)

列表接口采用标准分页：

- `page`: 页码（默认: 1）
- `per_page`: 每页记录数（默认: 15，最大: 100）

**综合示例**：
`GET /api/v1/users?include=roles&filter[status]=active&sort=-created_at&page=1&per_page=20`

### 分页响应示例

```json
{
    "success": true,
    "code": 200,
    "message": "资源列表获取成功",
    "data": {
        "items": [
            { "id": 1, "name": "Item 1" },
            { "id": 2, "name": "Item 2" }
        ],
        "meta": {
            "total": 150,
            "per_page": 15,
            "current_page": 1,
            "last_page": 10
        }
    }
}
```

## 技术支持

- **反馈 Issue**: 在项目仓库提交反馈
- **技术支持**: <ailuoga166@gmail.com>
