## 项目初始化方案：whalestore/laravel-multi-stripe

本项目目标是基于官方包 `laravel/cashier-stripe`（下称 Cashier），实现一个**支持在同一 Laravel 应用中使用多个 Stripe 账户（含沙盒/正式环境）**的 Composer 扩展包，同时**不修改 Cashier 源码**，而是通过扩展和组合的方式实现多账户能力。

---

## 包元信息与基本约束

- **Git 仓库**：`git@github.com:whalestore/laravel-multi-stripe.git`
- **Composer 包名**：`whalestore/laravel-multi-stripe`
- **根命名空间**：`Whalestore\LaravelMultiStripe\`
- **主要依赖**：
  - `laravel/framework`: 支持 Laravel 10 / 11（具体版本后续在 `composer.json` 固定）
  - `laravel/cashier`: 依赖 Cashier Stripe 版本（例如 `^15` 或 `^16`，实际以最新稳定为准）
  - `stripe/stripe-php`: 版本需与 Cashier 内部兼容

**整体约束**：

- **完全依赖 Cashier**：不 fork、不修改其源码，通过 trait、service、facade、路由和配置进行扩展。
- **编码规范**：遵循 PSR-12，统一代码风格。
- **项目结构**：使用标准的 `src/` + `tests/` 结构。
- **Composer 集成**：正确配置 `autoload` 与 Laravel 自动发现（`extra.laravel.providers`）。
- **服务提供者**：在 `register` 中完成绑定，在 `boot` 中注册路由/中间件/发布配置等，注意路由缓存。
- **测试与文档优先**：提供基础单元测试、集成测试与清晰 README。
- **语义化版本控制（SemVer）**：对外文档声明与 Cashier 版本的兼容矩阵。

---

## 目标功能建模

### 要解决的业务场景

1. 在**一个 Laravel 应用**中，按「国家/地区/业务线/租户」使用**多个 Stripe 账户**。
2. 每个 Stripe 账户都需要支持：
   - **测试环境（sandbox/test）**
   - **正式环境（live/production）**
3. 根据不同上下文（请求参数、用户、租户等）**动态选择：哪一个 Stripe 账户 + 哪一个环境（test/live）**。
4. Webhook 需要能够针对不同账户（和环境）分别验证签名并分发事件。

### 与 Cashier 的关系

- Cashier 默认仅支持单账户、单 key，使用 `config('cashier.secret')` 等配置。
- 本包通过：
  - **多账户配置管理**
  - **上下文解析器（Resolver）**
  - **多账户 Stripe Client 工厂**
  - **包装 Billable trait**
  - **多账户 Webhook 控制器**
  
来为 Cashier 提供「多账户 + 多环境」能力。

---

## 目录结构设计

遵循 PSR-4 与 Laravel 生态的常见约定，预计目录结构如下：

- `src/`
  - `Providers/`
    - `MultiStripeServiceProvider.php`  
      **说明**：包的主服务提供者，负责配置合并、服务绑定、路由与中间件注册、资源发布等。
  - `Contracts/`
    - `StripeAccountResolver.php`  
      **说明**：账户解析器接口，对「如何从上下文解析 Stripe 账户 + 环境」进行抽象。
  - `Models/`
    - （可选）`StripeAccount.php`  
      **说明**：如果后续需要 DB 持久化账户信息，可以提供一个 Eloquent 模型；初期可以只用 config。
  - `Managers/`
    - `StripeAccountManager.php`  
      **说明**：统一管理多账户多环境配置，以「逻辑账户 + 环境」为单位返回配置对象。
  - `Resolvers/`
    - `ConfigStripeAccountResolver.php`  
      **说明**：基于配置的默认解析器，支持按自定义参数解析账户与环境（query/header/route 等）。
    - （预留）`CustomStripeAccountResolver.php`  
      **说明**：示例自定义解析器，文档中引导用户自行实现接口。
  - `Support/`
    - `StripeAccountConfig.php`  
      **说明**：描述「某个账户在某个环境下」的配置（secret、publishable_key、webhook_secret 等）的值对象。
    - `StripeLogicalAccount.php`（可选）  
      **说明**：描述逻辑账户（包含 test/live 两套配置）的值对象，便于 `StripeAccountManager` 内部维护。
  - `Traits/`
    - `MultiBillable.php`  
      **说明**：包装 Cashier 的 `Billable` trait，支持多账户；用户在 `User` 等模型上使用此 trait 替代原生 `Billable`。
  - `Http/`
    - `Middleware/`
      - `SetCurrentStripeContext.php`  
        **说明**：中间件，在请求生命周期早期解析出当前账户 + 环境，并绑定到容器或全局上下文。
    - `Controllers/`
      - `MultiStripeWebhookController.php`  
        **说明**：多账户 Webhook 控制器，路由形如 `stripe/{account}/webhook`，并支持环境维度。
  - `Facades/`
    - `MultiStripe.php`  
      **说明**：提供简单的门面，用于在业务中直接获取指定账户/环境的 Stripe Client 或进行高级操作。
  - `Services/`
    - `MultiStripeClientFactory.php`  
      **说明**：根据账户 + 环境创建/缓存 `\Stripe\StripeClient`，多处复用。
  - `Config/`
    - `multi-stripe.php`  
      **说明**：本包配置文件，负责描述多账户、多环境，以及解析器和 webhook 路由等。
- `tests/`
  - `Unit/`
  - `Feature/`
- `README.md`
- `composer.json`
- `phpunit.xml`（测试配置）

---

## 多账户 + 多环境配置模型

### 基本概念

- **逻辑账户（Logical Account）**：业务上的一个 Stripe 账户标识，如 `us`, `eu`, `apac`。
- **环境（Environment）**：`test` 或 `live`，对应 Stripe 的 sandbox / live key。
- **账户配置（StripeAccountConfig）**：某逻辑账户在某个环境下的完整配置。

### 配置文件结构（`config/multi-stripe.php` 草案）

```php
return [
    // 全局默认环境：test / live，可由 env 控制，也可在运行时动态覆盖
    'default_environment' => env('STRIPE_ENVIRONMENT', 'test'),

    // 已支持的环境列表，便于扩展（通常为 ['test', 'live']）
    'environments' => ['test', 'live'],

    // 逻辑账户列表，每个账户下区分 test / live
    'accounts' => [
        'us' => [
            'name' => 'US Main Account',

            'test' => [
                'secret'          => env('STRIPE_US_TEST_SECRET'),
                'publishable_key' => env('STRIPE_US_TEST_KEY'),
                'webhook_secret'  => env('STRIPE_US_TEST_WEBHOOK_SECRET'),
                'currency'        => 'usd',
            ],

            'live' => [
                'secret'          => env('STRIPE_US_LIVE_SECRET'),
                'publishable_key' => env('STRIPE_US_LIVE_KEY'),
                'webhook_secret'  => env('STRIPE_US_LIVE_WEBHOOK_SECRET'),
                'currency'        => 'usd',
            ],
        ],

        'eu' => [
            'name' => 'EU Main Account',
            // 同上，包含 test & live
        ],
    ],

    // 账户 + 环境的解析配置（默认实现使用 ConfigStripeAccountResolver）
    'resolver' => [
        'class' => \Whalestore\LaravelMultiStripe\Resolvers\ConfigStripeAccountResolver::class,

        'options' => [
            // 支持按请求参数解析账号，如 ?stripe_account=us
            'account_param'      => 'stripe_account',
            // 支持按请求参数解析环境，如 ?stripe_env=test
            'environment_param'  => 'stripe_env',

            // 支持通过 Header 设置账号与环境，例如：
            // X-Stripe-Account: us
            // X-Stripe-Env: test
            'account_header'     => 'X-Stripe-Account',
            'environment_header' => 'X-Stripe-Env',

            // 支持从路由参数解析，例如 route('x', ['stripe_account' => 'us'])
            'account_route_key'      => 'stripe_account',
            'environment_route_key'  => 'stripe_env',
        ],
    ],

    // Webhook 相关配置
    'webhook' => [
        // 路由路径模板：可高度自定义，默认 stripe/{account}/webhook
        // {account} 必须存在，可选增加 {environment} 占位符
        'path' => 'stripe/{account}/webhook',

        // 可允许使用 environment 作为路由参数时的占位符名（例如 stripe/{account}/{environment}/webhook）
        'environment_placeholder' => 'environment',

        // 路由中间件
        'middleware' => ['api'],
    ],
];
```

### StripeAccountManager 职责

- 从配置（以及未来可能的 DB）中加载账户与环境信息。
- 提供统一接口：
  - `get(string $accountId, ?string $environment = null): StripeAccountConfig`
  - `getByContext(StripeContext $context): StripeAccountConfig`
  - `defaultEnvironment(): string`
- 内部可以维护：
  - `StripeLogicalAccount`：包含一个账户下的多个环境配置。
  - 将「逻辑账户 + 环境」组合映射为具体 `StripeAccountConfig`。

---

## 上下文解析与自定义空间

### StripeAccountResolver 接口

接口大致形态（示意）：

```php
interface StripeAccountResolver
{
    /**
     * 根据当前请求 / 用户 / 上下文，解析出要使用的逻辑账户 ID 与环境。
     *
     * 返回形如：
     * [
     *     'account'     => 'us',
     *     'environment' => 'test', // 或 live
     * ]
     */
    public function resolve(?Model $billable = null): ?array;
}
```

### ConfigStripeAccountResolver：基于配置的默认解析器

- 优先顺序（可配置）示例：
  1. 显式传入的 `$billable` 对象上的字段（如 `stripe_account_id` / `stripe_env`），若在配置中开启。
  2. 当前请求的路由参数（例如 `stripe_account` / `stripe_env`）。
  3. 当前请求的 Query 参数或 Header。
  4. 全局默认环境 + 默认账户。
- 将「如何从请求中取值」设计成**高度可配置**：
  - 通过 `multi-stripe.php` 中的 `options` 指定参数名 / header 名 / route key 名。
  - 支持用户在配置中注入自定义回调（例如 `callable` 或 `invokable class`）实现更复杂逻辑。

### 自定义解析器扩展

- 用户可以自定义一个类实现 `StripeAccountResolver`，例如：
  - 按「租户 ID」或「域名」选择 Stripe 账户。
  - 按「用户所在国家」选择账户。
- 然后在 `config/multi-stripe.php` 中将 `resolver.class` 改为该自定义类。

---

## 多账户 Stripe Client 工厂

### MultiStripeClientFactory 职责

- 根据（账户 ID + 环境）或 `StripeAccountConfig` 实例返回对应的 `\Stripe\StripeClient`。
- 简单缓存：在一次请求内部，同一账户 + 环境只创建一个 `StripeClient`。
- 提供方法示例：
  - `for(string $accountId, ?string $environment = null): StripeClient`
  - `forConfig(StripeAccountConfig $config): StripeClient`
  - `forBillable(Model $billable): StripeClient`

### Facade：MultiStripe

- 提供更友好的调用方式，例如：

```php
MultiStripe::account('eu')->env('live')->client();
MultiStripe::forBillable($user)->client();
```

- 内部仍然使用 `MultiStripeClientFactory` 与 `StripeAccountManager`。

---

## 与 Cashier 的集成策略

### 不修改 Cashier 源码的基本思路

- 不直接改变 `config('cashier.secret')` 的使用方式，而是：
  - 在使用多账户能力时，主要通过本包提供的 `MultiBillable` trait + 工厂/Facade 来完成。
  - 尽量让用户体验接近原生 Cashier 调用方式，保持方法名和返回值兼容。

### MultiBillable trait 设计

- 内部 `use \Laravel\Cashier\Billable;` 保留原有 Cashier 能力。
- 重写/包装**关键方法**，在调用 Stripe 相关操作前：
  1. 使用 `StripeAccountResolver` 解析当前使用的账户 + 环境。
  2. 通过 `StripeAccountManager` 拿到对应 `StripeAccountConfig`。
  3. 通过 `MultiStripeClientFactory` 拿到正确的 `StripeClient`。
  4. 使用该 client 调用 Stripe API，或者将对应的 `api_key` / `stripe_options` 传入 Cashier。
- 重点方法示例（最终以 Cashier 当前公开 API 为准）：
  - `newSubscription(...)`
  - `charge(...)`
  - 一些涉及创建/更新/取消订阅、发起支付的入口方法。

### 迁移方式

- 原有模型：

```php
use Laravel\Cashier\Billable;

class User extends Authenticatable
{
    use Billable;
}
```

- 启用多账户：

```php
use Whalestore\LaravelMultiStripe\Traits\MultiBillable;

class User extends Authenticatable
{
    use MultiBillable;
}
```

- 若需要基于用户字段（如 `stripe_account_id`、`stripe_env`）决定账户与环境，可在解析器中读取这些字段。

---

## Webhook 多账户 + 多环境设计

### 路由结构

- 默认路由示例：
  - `POST /stripe/{account}/webhook`
- 允许用户通过配置进一步扩展，例如：
  - `POST /stripe/{account}/{environment}/webhook`
  - 或完全自定义路径，只要包含 `{account}` 占位符（`{environment}` 可选）。

### MultiStripeWebhookController 设计

- 控制器继承或组合 Cashier 原有 Webhook 控制器，例如：
  - 继承 `\Laravel\Cashier\Http\Controllers\WebhookController`，在入口处注入账户/环境上下文。
  - 或者内部实例化原控制器，将事件处理委托给 Cashier。
- 处理流程：
  1. 从当前请求的**路由参数 / Query 参数 / Header**中解析出 `account` 和 `environment`。
  2. 使用 `StripeAccountManager` 获取对应 `StripeAccountConfig`。
  3. 使用该配置的 `webhook_secret` 对 Stripe 发送的签名进行验证。
  4. 基于获得的 `StripeClient` / key 调用 Cashier 的事件处理逻辑。

### 单账户兼容

- 如果项目只配置了一个逻辑账户且只使用一种环境：
  - 用户仍然可以继续使用原生 Cashier 的 `/stripe/webhook`，不强制迁移。
  - 也可以统一使用本包提供的多账户 webhook 路由，但只启用一个账户/环境。

---

## 中间件与当前上下文绑定

### SetCurrentStripeContext 中间件

- 职责：
  1. 在请求开始处，使用 `StripeAccountResolver` 根据请求信息（参数、路由、Header）和当前用户解析 `account + environment`。
  2. 将解析结果封装为 `StripeContext`（内部可包含 `StripeAccountConfig`），绑定到容器中，例如：
     - `app()->instance(StripeContext::class, $context);`
  3. 方便后续的 `MultiBillable`、`MultiStripeClientFactory`、Webhook 控制器等直接从容器读取当前上下文，而无需重复解析。

- 用户可以按需要在路由组中启用此中间件，例如：

```php
Route::middleware(['api', 'multi-stripe.context'])->group(function () {
    // 此处所有请求默认自动带上 Stripe 上下文
});
```

---

## 服务提供者设计

### MultiStripeServiceProvider

- **register()**：
  - 合并配置：`$this->mergeConfigFrom(__DIR__.'/../Config/multi-stripe.php', 'multi-stripe');`
  - 注册单例：
    - `StripeAccountManager`
    - `MultiStripeClientFactory`
  - 绑定接口到实现：
    - `StripeAccountResolver` → 配置中的 `resolver.class`
  - 注册 Facade 对应的服务名（例如 `multi-stripe`）。

- **boot()**：
  - 发布配置文件：
    - `php artisan vendor:publish --provider="Whalestore\LaravelMultiStripe\Providers\MultiStripeServiceProvider" --tag=config`
  - 注册路由：
    - 使用 `Route::group` 注册 webhook 路由。
    - 注意 `app()->routesAreCached()` 情况，避免重复加载。
  - 注册中间件别名：
    - 如 `multi-stripe.context` → `SetCurrentStripeContext`。
  - （可选）发布迁移（如果未来需要 DB 存储多账户信息）。

---

## Composer 配置与自动发现

### composer.json 关键配置

- **名称与描述**
  - `"name": "whalestore/laravel-multi-stripe"`
  - `"description": "Multi Stripe account support for Laravel Cashier (Stripe) without modifying Cashier itself."`

- **autoload**

```json
"autoload": {
  "psr-4": {
    "Whalestore\\\\LaravelMultiStripe\\\\": "src/"
  }
},
"autoload-dev": {
  "psr-4": {
    "Whalestore\\\\LaravelMultiStripe\\\\Tests\\\\": "tests/"
  }
}
```

- **extra.laravel 自动发现**

```json
"extra": {
  "laravel": {
    "providers": [
      "Whalestore\\\\LaravelMultiStripe\\\\Providers\\\\MultiStripeServiceProvider"
    ]
  }
}
```

---

## 测试与文档策略

### 测试

- **Unit Tests**
  - `StripeAccountManagerTest`
    - 多账户多环境配置加载与获取。
    - 默认环境与默认账户解析。
  - `ConfigStripeAccountResolverTest`
    - 按 Query/Header/Route 参数解析账户 + 环境。
  - `MultiStripeClientFactoryTest`
    - 不同账户/环境生成不同的 `StripeClient`。
    - 相同账户/环境重复调用时从缓存返回。

- **Feature Tests**
  - 简化的 Laravel 应用集成测试：
    - 模型使用 `MultiBillable`，模拟不同账户/环境条件下发起订阅/扣款（通过 mock Stripe Client）。
    - Webhook：
      - 模拟 `POST /stripe/us/webhook`，验证使用 `us + default_env` 的 webhook secret。
      - 如启用 environment 路由参数，也测试 `POST /stripe/us/test/webhook` 与 `POST /stripe/us/live/webhook`。

### 文档（README）

- **核心章节**
  - 安装与版本要求。
  - 配置多个账户与 test/live 环境。
  - 账户与环境解析方式（自定义参数 / header / 路由 / 用户字段示例）。
  - 从 `Billable` 迁移到 `MultiBillable` 的步骤。
  - Webhook 多账户多环境配置与 Stripe Dashboard 设置示例。
  - 如何实现自定义 `StripeAccountResolver`。
  - 与 Cashier 版本兼容矩阵与 SemVer 策略。

---

## 版本控制与兼容策略

- 初期版本：`0.1.0`（实验性，API 可能调整）。
- 稳定版本：`1.0.0` 起遵守 SemVer：
  - `MAJOR`：可能破坏向后兼容，多半与 Cashier 重大版本变更有关。
  - `MINOR`：向后兼容的新功能。
  - `PATCH`：向后兼容的 bug 修复。
- 文档中维护「本包版本 ↔ Cashier 版本」对照表，方便用户选择正确版本组合。

---

## 后续实现步骤（编码阶段路线）

1. 创建基础文件：
   - `composer.json`
   - `src/Providers/MultiStripeServiceProvider.php`
   - `src/Config/multi-stripe.php`
   - `README.md` 草稿
2. 实现核心基础设施：
   - `StripeAccountConfig`、`StripeAccountManager`
   - `StripeAccountResolver` 接口与默认 `ConfigStripeAccountResolver`
   - `MultiStripeClientFactory` + `MultiStripe` 门面
3. 实现与 Cashier 集成：
   - `MultiBillable` trait，包装 Cashier 的关键 API。
4. 实现 Webhook 与中间件：
   - `MultiStripeWebhookController`
   - `SetCurrentStripeContext` 中间件
   - 路由注册与配置选项完善。
5. 编写基础测试与完善 README 文档。


