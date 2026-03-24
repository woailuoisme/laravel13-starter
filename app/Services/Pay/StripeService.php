<?php

declare(strict_types=1);

namespace App\Services\Pay;

use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Laravel\Cashier\Checkout;
use Laravel\Cashier\Exceptions\IncompletePayment;
use Laravel\Cashier\Invoice;
use Laravel\Cashier\Payment;
use Laravel\Cashier\Subscription;
use Stripe\Refund;
use Stripe\StripeClient;

/**
 * Stripe 支付服务类 (增强版)
 *
 * 封装 Laravel Cashier 功能，涵盖：单次支付、多类型订阅、试用、计费、发票、退款及 SCA。
 */
class StripeService
{
    protected StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('cashier.secret'));
    }

    // --- 结账与支付 (Checkout & Payment) ---

    /**
     * 创建结账会话 (单次付款 / 订阅)
     */
    public function checkout(User $user, string|array $priceId, int $quantity = 1, array $options = []): Checkout
    {
        $items = is_array($priceId) ? $priceId : [$priceId => $quantity];

        return $user->checkout($items, array_merge([
            'success_url' => $options['success_url'] ?? route('pay.success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $options['cancel_url'] ?? route('pay.cancel'),
        ], $options));
    }

    /**
     * 直接单次扣款 (后台扣费，可能触发 SCA)
     *
     * @throws IncompletePayment
     */
    public function charge(User $user, int $amountInCents, string $paymentMethodId, array $options = []): Payment
    {
        return $user->charge($amountInCents, $paymentMethodId, $options);
    }

    /**
     * 发起退款 (原生退款操作)
     */
    public function refund(string $paymentIntentId, ?int $amount = null, array $options = []): Refund
    {
        $params = array_merge(['payment_intent' => $paymentIntentId], $options);
        if ($amount) {
            $params['amount'] = $amount;
        }

        return $this->stripe->refunds->create($params);
    }

    // --- 订阅管理 (Subscription Management) ---

    /**
     * 创建订阅 (支持试用与优惠券)
     *
     * @throws IncompletePayment
     */
    public function subscribe(User $user, string $name, string $planId, string $paymentMethodId, array $options = []): Subscription
    {
        $builder = $user->newSubscription($name, $planId);

        // 优惠券
        if (! empty($options['coupon'])) {
            $builder->withCoupon($options['coupon']);
        }

        // 试用天数
        if (! empty($options['trial_days'])) {
            $builder->trialDays((int) $options['trial_days']);
        }

        return $builder->create($paymentMethodId);
    }

    /**
     * 切换订阅计划 (Proration)
     */
    public function swapSubscription(User $user, string $name, string $newPlanId): Subscription
    {
        return $user->subscription($name)->swap($newPlanId);
    }

    /**
     * 暂停/恢复订阅
     */
    public function toggleSubscription(User $user, string $name = 'default'): bool
    {
        $sub = $user->subscription($name);
        if ($sub->onGracePeriod()) {
            $sub->resume();

            return true;
        }

        $sub->cancel();

        return false;
    }

    /**
     * 记录按量计费使用情况
     */
    public function reportUsage(User $user, string $subscriptionName, int $quantity, ?string $priceId = null): void
    {
        $subscription = $user->subscription($subscriptionName);
        if ($priceId) {
            $subscription->recordUsage($quantity, $priceId);

            return;
        }
        $subscription->recordUsage($quantity);
    }

    // --- 发票与账单 (Invoices & Billing) ---

    /**
     * 获取用户所有发票
     */
    public function invoices(User $user): Collection
    {
        return $user->invoices();
    }

    /**
     * 下载发票 PDF
     */
    public function downloadInvoice(User $user, string $invoiceId): Response
    {
        return $user->downloadInvoice($invoiceId, [
            'vendor' => config('app.name'),
            'product' => 'Subscription Service',
        ]);
    }

    /**
     * 获取预期账单预览 (下期扣费金额)
     */
    public function upcomingInvoicePreview(User $user): ?Invoice
    {
        return $user->upcomingInvoice();
    }

    /**
     * 生成账单管理门户
     */
    public function billingPortalUrl(User $user, ?string $returnUrl = null): string
    {
        return $user->billingPortalUrl($returnUrl ?: config('app.url'));
    }

    // --- 支付方式管理 (Payment Methods) ---

    /**
     * 添加并设为默认支付方式 (SetupIntent)
     */
    public function updateDefaultPaymentMethod(User $user, string $paymentMethodId): string
    {
        $user->updateDefaultPaymentMethod($paymentMethodId);

        return $paymentMethodId;
    }

    /**
     * 获取用户所有已绑定的卡
     */
    public function paymentMethods(User $user): Collection
    {
        return $user->paymentMethods();
    }

    /**
     * 删除支付方式
     */
    public function deletePaymentMethod(User $user, string $paymentMethodId): void
    {
        $pm = $user->findPaymentMethod($paymentMethodId);
        $pm?->delete();
    }

    // --- 高级功能 (Advanced) ---

    /**
     * 同步客户信息到 Stripe
     */
    public function syncCustomer(User $user): void
    {
        $user->updateStripeCustomer([
            'email' => $user->email,
            'metadata' => [
                'user_id' => $user->id,
                'nickname' => $user->nickname,
            ],
        ]);
    }

    /**
     * 原生客户端出口
     */
    public function client(): StripeClient
    {
        return $this->stripe;
    }
}
