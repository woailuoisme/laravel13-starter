<?php

namespace App\Enums;

use App\Traits\EnumValues;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum FilamentNavigationGroup: string implements HasIcon, HasLabel
{
    use EnumValues;

    case DeviceManagement = 'device_management';
    case ProductManagement = 'product_management';
    case OrderManagement = 'order_management';
    case AdvertisementManagement = 'advertisement_management';
    case PromotionManagement = 'promotion_management';
    case GoodsManagement = 'goods_management';
    case FrontendManagement = 'frontend_management';
    case BackendManagement = 'backend_management';
    case PostManagement = 'post_management';
    case RegionManagement = 'region_management';
    case FeedbackManagement = 'feedback_management';
    case SystemSettings = 'system_settings';

    /**
     * 实现HasLabel接口的getLabel方法
     * 返回多语言标签
     */
    public function getLabel(): string|Htmlable|null
    {
        return $this->getLabelText();
    }

    /**
     * 获取标签文本
     */
    public function getLabelText(): string
    {
        return match ($this) {
            self::DeviceManagement => __('navigation.device_management'),
            self::ProductManagement => __('navigation.product_management'),
            self::OrderManagement => __('navigation.order_management'),
            self::AdvertisementManagement => __('navigation.advertisement_management'),
            self::PromotionManagement => __('navigation.promotion_management'),
            self::GoodsManagement => __('navigation.goods_management'),
            self::FrontendManagement => __('navigation.frontend_management'),
            self::BackendManagement => __('navigation.backend_management'),
            self::PostManagement => __('navigation.post_management'),
            self::RegionManagement => __('navigation.region_management'),
            self::FeedbackManagement => __('navigation.feedback_management'),
            self::SystemSettings => __('navigation.system_settings'),
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::DeviceManagement => 'heroicon-o-device-phone-mobile',
            self::ProductManagement => 'heroicon-o-cube',
            self::OrderManagement, self::GoodsManagement => 'heroicon-o-shopping-cart',
            self::AdvertisementManagement => 'heroicon-o-speaker-wave',
            self::PromotionManagement => 'heroicon-o-gift',
            self::FrontendManagement => 'heroicon-o-users',
            self::BackendManagement => 'heroicon-o-shield-check',
            self::PostManagement => 'heroicon-o-newspaper',
            self::RegionManagement => 'heroicon-o-map',
            self::FeedbackManagement => 'heroicon-o-chat-bubble-oval-left',
            self::SystemSettings => 'heroicon-o-cog-6-tooth',
        };
    }

    /**
     * 通过字符串值获取枚举实例
     */
    public static function fromString(string $value): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->value === $value || $case->getLabelText() === $value) {
                return $case;
            }
        }

        // 兼容旧版本的值
        return match ($value) {
            'Filament Shield' => self::BackendManagement,
            '系统管理' => self::SystemSettings,
            default => null,
        };
    }

    /**
     * 获取所有导航组及其标签的数组
     */
    public static function getOptions(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->getLabelText();
        }

        return $options;
    }
}
