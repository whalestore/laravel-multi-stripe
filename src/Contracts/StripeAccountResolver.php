<?php

declare(strict_types=1);

namespace Whalestore\LaravelMultiStripe\Contracts;

use Illuminate\Database\Eloquent\Model;

interface StripeAccountResolver
{
    /**
     * 根据当前上下文解析 Stripe 逻辑账户与环境。
     *
     * 返回形如：
     * [
     *     'account'     => 'us',
     *     'environment' => 'test',
     * ]
     * 若无法解析则返回 null。
     *
     * @return array{account: string, environment: string}|null
     */
    public function resolve(?Model $billable = null): ?array;
}


