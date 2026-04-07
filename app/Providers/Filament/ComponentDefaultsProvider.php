<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use BezhanSalleh\LanguageSwitch\LanguageSwitch;
use CodeWithDennis\FilamentSelectTree\SelectTree;
use Filament\Actions\Exports\Models\Export;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\SpatieMediaLibraryImageEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\VerticalAlignment;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Hugomyb\FilamentMediaAction\Actions\MediaAction;
use Illuminate\Support\ServiceProvider;
use Malzariey\FilamentDaterangepickerFilter\Fields\DateRangePicker;
use Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter;

/**
 * Filament 全局组件默认配置提供者
 *
 * 集中管理所有 Filament 核心组件与插件组件的全局默认行为 (configureUsing)，
 * 保证整个后台在 UI 交互与视觉规范上的一致性。
 */
class ComponentDefaultsProvider extends ServiceProvider
{
    /** 默认图片 URL */
    private const string DEFAULT_IMAGE_URL = 'images/filament/default_image_2.svg';

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->configurePolymorphicExports();
        $this->configureTextColumns();
        $this->configureImageComponents();
        $this->configureToggleComponents();
        $this->configureInputComponents();
        $this->configureLayoutComponents();
        $this->configureTableGlobalDefaults();
        $this->configureMediaActions();

        LanguageSwitch::configureUsing(static function (LanguageSwitch $switch): void {
            $switch->locales(['zh_CN','en'])->visible(); // also accepts a closure
        });

        SelectTree::configureUsing(static function (SelectTree $selectTree): void {
            $selectTree->enableBranchNode(true)
                ->multiple(false)
                ->searchable();
        });
    }

    /**
     * 配置导出的多态关联
     */
    private function configurePolymorphicExports(): void
    {
        Export::polymorphicUserRelationship();
    }

    /**
     * 配置文本列的全局默认行为
     */
    private function configureTextColumns(): void
    {
        TextColumn::configureUsing(static function (TextColumn $column): void {
            $column->alignCenter()
                ->verticalAlignment(VerticalAlignment::Center);

            // 自动识别日期/时间字段并进行标准格式化
            $name = $column->getName();
            $isDateTime = preg_match('/(_at|date|time|published|created|updated|deleted)/i', $name);

            if ($isDateTime) {
                $column->dateTime(config('app.date_formats.datetime', 'Y-m-d H:i:s'));
            }
        });
    }

    /**
     * 配置所有图片相关组件的默认行为
     */
    private function configureImageComponents(): void
    {
        $configureImage = static function ($component): void {
            $component->defaultImageUrl(url(self::DEFAULT_IMAGE_URL))
                ->visibility('public')
                ->checkFileExistence(false);
        };

        ImageColumn::configureUsing($configureImage);
        SpatieMediaLibraryImageColumn::configureUsing($configureImage);
        ImageEntry::configureUsing($configureImage);
        SpatieMediaLibraryImageEntry::configureUsing($configureImage);
    }

    /**
     * 配置切换开关组件的默认行为 (Table & Form)
     */
    private function configureToggleComponents(): void
    {
        // 表格切换列
        ToggleColumn::configureUsing(static function (ToggleColumn $column): void {
            $column->onColor('success')
                ->offColor('danger')
                ->onIcon('heroicon-m-check-circle')
                ->offIcon('heroicon-m-x-circle')
                ->afterStateUpdated(function ($record, $state): void {
                    Notification::make()
                        ->title($state ? __('admin.notifications.enabled') : __('admin.notifications.disabled'))
                        ->success()
                        ->send();
                });
        });

        // 表单切换开关
        Toggle::configureUsing(static function (Toggle $component): void {
            $component->onColor('success')
                ->offColor('danger')
                ->onIcon('heroicon-m-check-circle')
                ->offIcon('heroicon-m-x-circle');
        });

        // 图标列 (Boolean)
        IconColumn::configureUsing(static function (IconColumn $column): void {
            $column->trueIcon('heroicon-o-check-circle')
                ->falseIcon('heroicon-o-x-circle')
                ->trueColor('success')
                ->falseColor('danger');
        });
    }

    /**
     * 配置输入框组件的默认行为 (Forms)
     */
    private function configureInputComponents(): void
    {
        // 媒体库上传
        SpatieMediaLibraryFileUpload::configureUsing(static function (SpatieMediaLibraryFileUpload $component): void {
            $component->maxSize(2048)
                ->fetchFileInformation(false)
                ->previewable()
                ->pasteable(false)
                ->panelLayout('grid')
                ->multiple(false)
                ->maxParallelUploads(3)
                ->downloadable()
                ->openable()
                ->visibility('public')
                ->deletable(true)
                ->reorderable(false);
        });

        // 标签输入 (Spatie Tags)
        SpatieTagsInput::configureUsing(static function (SpatieTagsInput $component): void {
            $component->splitKeys(['Tab', ' ', ','])
                ->reorderable();
        });

        // 多行文本
        Textarea::configureUsing(static function (Textarea $component): void {
            $component->rows(3);
        });

        // 富文本编辑器
        RichEditor::configureUsing(static function (RichEditor $component): void {
            $component->extraInputAttributes([
                'style' => 'min-height: 300px; max-height: 800px; overflow-y: scroll;',
            ])
            ->fileAttachmentsDisk(config('filesystems.default'))
            ->fileAttachmentsDirectory('attachments')
            ->fileAttachmentsVisibility('public');
        });

        // Markdown 编辑器
        MarkdownEditor::configureUsing(static function (MarkdownEditor $component): void {
            $component->toolbarButtons([
                'bold', 'italic', 'strike', 'link', 'heading', 'blockquote',
                'codeBlock', 'bulletList', 'orderedList', 'table', 'attachFiles', 'undo', 'redo',
            ])
            ->fileAttachmentsDisk('oss')
            ->fileAttachmentsDirectory('attachments');
        });

        // 时间选择器
        DateTimePicker::configureUsing(static function (DateTimePicker $component): void {
            $component->timezone('Asia/Shanghai')
                ->locale(config('app.timezone'))
                ->displayFormat('Y-m-d H:i:s');
        });
        DatePicker::configureUsing(static function (
            DatePicker $component,
        ): void {
            $component
                ->native(false)
                ->locale('zh_CN')
                ->displayFormat('Y-m-d');
        });

        DateRangePicker::configureUsing(static function (
            DateRangePicker $component,
        ): void {
            $component
                ->timezone('Asia/Shanghai')
                ->displayFormat('YYYY-MM-DD HH:mm:ss')
                ->format('Y-m-d H:i:s')
                ->rangeSeparator(' - ')
                ->firstDayOfWeek(1)
                ->useRangeLabels()
                ->alwaysShowCalendar()
                ->timePicker24()
                ->autoApply();
        });

        DateRangeFilter::configureUsing(static function (
            DateRangeFilter $component,
        ): void {
            $component
                ->timezone('Asia/Shanghai')
                ->displayFormat('YYYY-MM-DD HH:mm:ss') // 用于浏览器显示的 JS 格式 (Moment/DayJS)
                ->format('Y-m-d H:i:s')                // 用于服务端解析的 PHP 格式 (Carbon)
                ->rangeSeparator(' - ')                 // 范围分割符号
                ->firstDayOfWeek(1)                    // 设置周一作为一周的第一天
                ->useRangeLabels()                     // 启用侧边快捷选择标签（今天、昨天、近7天等）
                ->alwaysShowCalendar()                 // 弹窗时始终直接显示日历界面
                ->timePicker24()                       // 使用 24 小时制时间选择器
                ->autoApply()                          // 选好范围后自动应用筛选，无需点击确定按钮
                ->withIndicator();                     // 在表格顶部显示当前激活的筛选状态
        });

        // 基础文件上传
        FileUpload::configureUsing(static function (FileUpload $component): void {
            $component->visibility('public')
                ->directory('uploads')
                ->openable()
                ->downloadable();
        });

        // 文本输入
        TextInput::configureUsing(static function (TextInput $component): void {
            $component->maxLength(255);
        });

        // 选择器
        Select::configureUsing(static function (Select $component): void {
            $component->searchable()
                ->preload()
                ->native(false);
        });

        /** 好像无效过 **/
        Table::configureUsing(static function (Table $table): void {
            $table->recordUrl(null);
            $table->recordAction(null);
        });
    }

    /**
     * 配置布局组件的默认行为
     */
    private function configureLayoutComponents(): void
    {
        // 区块
        Section::configureUsing(static function (Section $component): void {
            $component->collapsible();
        });

        // 网格
        Grid::configureUsing(static function (Grid $component): void {
            $component->columns([
                'default' => 1,
                'sm' => 2,
                'md' => 4,
                'lg' => 12,
            ]);
        });
    }

    /**
     * 配置表格全局默认行为
     */
    private function configureTableGlobalDefaults(): void
    {
        Table::configureUsing(static function (Table $table): void {
            $table->paginationPageOptions([10, 25, 50, 100])
                ->defaultPaginationPageOption(25)
                ->striped();
        });
    }

    /**
     * 配置第三方插件 Action
     */
    private function configureMediaActions(): void
    {
        MediaAction::configureUsing(static function (MediaAction $action): void {
            $action->label('查看媒体')
                ->preload(false)
                ->autoplay(true)
                ->disableDownload()
                ->disableRemotePlayback()
                ->modalHeading('查看媒体')
                ->modalFooterActionsAlignment(Alignment::Center);
        });
    }
}
