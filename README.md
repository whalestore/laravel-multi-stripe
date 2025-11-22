## whalestore/laravel-multi-stripe

基于 `laravel/cashier-stripe` 的多 Stripe 账户（支持 test/live 环境）扩展包，在**不修改 Cashier 源码**的前提下，为同一个 Laravel 应用提供多账户能力。

> 注意：目前为初始实现阶段，推荐在测试环境验证后再用于生产。

---

### 快速开始（核心用法）

> 当前包尚未发布到 Packagist，**不能直接 `composer require whalestore/laravel-multi-stripe`**。  
> 请在你的 Laravel 项目中通过「本地 path 仓库」或「Git VCS 仓库」方式引入，见下面的说明。

1. **在你的 Laravel 项目中配置 Composer 仓库（推荐本地 path 方式）**

假设你的目录结构如下：

- `~/Codes/xuefei/laravel-multi-stripe`（本包）
- `~/Codes/xuefei/your-laravel-app`（你的 Laravel 项目）

在 `your-laravel-app/composer.json` 中增加：

```json
"repositories": [
  {
    "type": "path",
    "url": "../laravel-multi-stripe",
    "options": {
      "symlink": true
    }
  }
],
"require": {
  "whalestore/laravel-multi-stripe": "*"
}
```

然后在你的 Laravel 项目根目录执行：

```bash
cd ~/Codes/xuefei/your-laravel-app
composer update whalestore/laravel-multi-stripe
```

这样 Composer 会直接从本地路径加载当前开发版的包（通过 symlink，改动会立刻生效）。

> 如果你更想从 GitHub 仓库拉取，而不是本地路径，也可以这样配置：
>
> ```json
> "repositories": [
>   {
>     "type": "vcs",
>     "url": "git@github.com:whalestore/laravel-multi-stripe.git"
>   }
> ],
> "require": {
>   "whalestore/laravel-multi-stripe": "dev-main"
> }
> ```
>
> 然后执行：
>
> ```bash
> composer require whalestore/laravel-multi-stripe:"dev-main"
> ```

2. **在你的 Laravel 项目中发布配置**

```bash
php artisan vendor:publish --provider="Whalestore\LaravelMultiStripe\Providers\MultiStripeServiceProvider" --tag=config
```

3. **在 `config/multi-stripe.php` 中配置多个账户 + test/live 环境**（见下文示例）。

4. **在 Billable 模型中启用 `MultiBillable`，并增加账户字段**：

```php
use Illuminate\Foundation\Auth\User as Authenticatable;
use Whalestore\LaravelMultiStripe\Traits\MultiBillable;

class User extends Authenticatable
{
    use MultiBillable;

    // 数据表中增加两个字段：stripe_account_id, stripe_env（可选）
}
```

5. **正常使用 Cashier API，自动走多账户配置**：

```php
// 根据用户字段 / 路由 / 参数解析出账户+环境后，自动切换对应 Stripe key
$user->newSubscription('default', 'price_xxx')->create($paymentMethodId);

$user->charge(1000, $paymentMethodId); // 单位：分
```

---

### 配置多账户与 test/live 环境

发布后会生成 `config/multi-stripe.php`，你可以在其中配置多个逻辑账户，每个账户下再区分 `test` 与 `live`：

```php
return [
    'default_environment' => env('STRIPE_ENVIRONMENT', 'test'),

    'default_account' => 'us',

    'accounts' => [
        'us' => [
            'name' => 'US Main',

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
    ],
];
```

`default_account` 与 `default_environment` 会在无法从上下文解析时作为回退使用。

---

### 账户与环境解析（自定义参数）

默认解析器 `ConfigStripeAccountResolver` 支持从以下来源解析逻辑账户与环境（按优先级）：

1. **Billable 模型字段**
   - 例如：`users` 表增加字段 `stripe_account_id` 和 `stripe_env`：
   - 配置项（可选，自定义字段名）：
     - `resolver.options.billable_account_field`
     - `resolver.options.billable_environment_field`

2. **路由参数**

```php
Route::post('billing/{stripe_account}/checkout', ...);
```

3. **Query 参数 / Header**

- Query：
  - `?stripe_account=us&stripe_env=test`
- Header：
  - `X-Stripe-Account: us`
  - `X-Stripe-Env: test`

可通过 `config/multi-stripe.php` 中的 `resolver.options` 自定义参数名和 header 名称。

如需完全自定义解析逻辑，你可以实现 `Whalestore\LaravelMultiStripe\Contracts\StripeAccountResolver` 接口，并在配置中替换：

```php
'resolver' => [
    'class' => App\Billing\MyStripeResolver::class,
],
```

---

### 在模型中启用 MultiBillable（多账户感知）

- **替换 trait**：将原来的 Cashier `Billable` 替换为 `MultiBillable`（见上面的快速开始）。  
- `MultiBillable` 会在以下方法调用前自动解析「当前账户 + 环境」，并临时覆盖 `config('cashier.secret')`：
  - `newSubscription(string $subscription, string|string[] $prices)`
  - `charge($amount, $paymentMethod = null, array $options = [])`
- 调用结束后会恢复原始配置，因此不会影响其它与 Stripe 无关的逻辑。

另外，还提供一个辅助方法：

```php
// 获取当前用户对应账户+环境下的 StripeClient，可用于更灵活的自定义操作
$client = $user->stripeClient();
```

---

### 使用 Facade 显式选择账户与环境

你也可以使用 `MultiStripe` Facade 直接获得某个账户+环境的 `StripeClient`：

```php
use Whalestore\LaravelMultiStripe\Facades\MultiStripe;

// 指定账户和环境
$client = MultiStripe::client('us', 'test');

// 根据 billable（例如用户）自动解析
$client = MultiStripe::forBillable($user);
```

---

### Webhook 多账户支持

默认会注册如下路由（可在配置中修改路径）：

```php
POST /stripe/{account}/webhook
```

- `{account}` 对应 `config('multi-stripe.accounts')` 中的逻辑账户 ID。
- 如需区分环境，可以在 `config/multi-stripe.php` 中自定义 `webhook.path`，例如：

```php
'webhook' => [
    'path' => 'stripe/{account}/{environment}/webhook',
    'environment_placeholder' => 'environment',
],
```

控制器 `MultiStripeWebhookController` 会：

1. 根据路由解析出 `{account}` 和可选 `{environment}`；
2. 使用 `StripeAccountManager` 找到对应配置；
3. 将 `webhook_secret` 写入 `config('cashier.webhook.secret')`；
4. 委托 Cashier 原有的 `WebhookController` 进行事件解析和处理。

在 Stripe Dashboard 中，为每个 Stripe 账户配置对应的 Webhook URL 即可。

---

### 使用命令行为指定账户创建 Webhook（multi-stripe:webhook:sync）

为了简化多账户 webhook 的创建，本包提供了一个简单的 Artisan 命令：

```bash
php artisan multi-stripe:webhook:sync {account} {--env=test}
```

- **account**：必填，逻辑账户 ID（如 `us` / `eu`），需要已经在 `config/multi-stripe.php` 中配置。  
- **--env**：可选，`test` 或 `live`，不传时使用 `multi-stripe.default_environment`。

命令执行后会：

1. 读取对应账户+环境下的 Stripe secret；  
2. 根据 `multi-stripe.webhook.path` 和当前 app URL 生成 webhook URL（例如 `https://your-app.com/stripe/us/webhook`）；  
3. 使用 Stripe PHP SDK 在该账户下创建 webhook endpoint；  
4. 在命令行中输出创建成功的 endpoint ID 和（首次创建时）signing secret。  

你需要将返回的 signing secret 填入 `config/multi-stripe.php` 对应账户/环境的 `webhook_secret`（或其对应的 `.env` 环境变量），之后该账户的 webhook 即可由本包 + Cashier 正常处理。

---

### 中间件：请求级 Stripe 上下文

中间件 `multi-stripe.context` 会在一次请求周期中解析出当前 Stripe 账户+环境，并将对应的 `StripeAccountConfig` 与 `StripeContext` 绑定到容器中：

```php
Route::middleware(['api', 'multi-stripe.context'])
    ->group(function () {
        // 此处的请求会自动带上当前 Stripe 上下文
    });
```

后续你可以通过依赖注入获取：

```php
use Whalestore\LaravelMultiStripe\Support\StripeContext;

public function __invoke(StripeContext $context)
{
    $config = $context->config();

    // $config->accountId(), $config->environment(), $config->secret(), ...
}
```

---

### 与 Cashier 的关系与注意事项

- 本包**不修改 Cashier 源码**，仅在：
  - Webhook 入口前动态设置 `cashier.webhook.secret`；
  - 提供基于账户+环境的 `StripeClient`，方便在自定义逻辑中使用；
  - 通过 `MultiBillable` 为模型增加多账户能力。
- 对于高阶用法（如订阅生命周期、发票、账单等），建议先在单账户环境下用好 Cashier，再引入本包对多账户场景进行验证。

---

### 版本与兼容性

- 推荐 PHP：`^8.1`
- Laravel：`^10.0` 或 `^11.0`
- Cashier：`^15.0 | ^16.0`

遵循 SemVer：

- `0.x`：快速迭代阶段，API 可能有调整；
- `1.x`：稳定 API，向后兼容小版本更新。

详细兼容矩阵和更多示例会在后续版本的文档中补充完善。


