<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumValues;
use Filament\Support\Contracts\HasLabel;

enum Gender: string implements HasLabel
{
    use EnumValues;

    case Male = 'male';
    case Female = 'female';
    case Unknown = 'unknown';

    /**
     * 获取多语言标签文本以适配 Filament 等 UI 展现
     */
    public function getLabel(): string
    {
        return __('enums.gender.'.$this->value);
    }
}
