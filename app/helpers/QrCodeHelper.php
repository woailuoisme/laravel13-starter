<?php

declare(strict_types=1);

namespace App\Helpers;

use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Endroid\QrCode\Writer\WriterInterface;
use Exception;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * QR 码生成工具类
 *
 * 封装 endroid/qr-code 库，提供 PNG、SVG、Base64 及 Data URL 等格式的二维码生成，
 * 支持 Logo、标签、自定义颜色等高级功能。
 */
class QrCodeHelper
{
    /** 默认二维码边距（像素） */
    private const int DEFAULT_MARGIN = 10;

    /** 默认二维码尺寸（像素） */
    private const int DEFAULT_SIZE = 300;

    /**
     * 生成 PNG 格式二维码（原始二进制数据）
     *
     * @param string $text 要编码的文本
     * @param int $size 尺寸（像素）
     */
    public static function generatePng(string $text, int $size = self::DEFAULT_SIZE): string
    {
        if (empty($text)) {
            return '';
        }

        try {
            return self::makeWriter('png')
                ->write(self::buildQrCode($text, $size))
                ->getString();
        } catch (Exception $e) {
            Log::error('QR Code: Failed to generate PNG', [
                'text' => $text,
                'size' => $size,
                'exception' => $e->getMessage(),
            ]);

            return '';
        }
    }

    /**
     * 生成 PNG Data URL 格式二维码（可直接用于 HTML img src 属性）
     *
     * @param string $text 要编码的文本
     * @param int $size 尺寸（像素）
     */
    public static function generateDataUrl(string $text, int $size = self::DEFAULT_SIZE): string
    {
        $png = self::generatePng($text, $size);

        return $png !== '' ? 'data:image/png;base64,'.base64_encode($png) : '';
    }

    /**
     * 生成 SVG Data URL 格式二维码
     *
     * @param string $text 要编码的文本
     * @param int $size 尺寸（像素）
     * @param bool $useBase64 true 使用 base64 编码，false 使用 URL 编码（体积更小）
     */
    public static function generateSvgDataUrl(string $text, int $size = self::DEFAULT_SIZE, bool $useBase64 = false): string
    {
        $svg = self::generateSvg($text, $size);

        if ($svg === '') {
            return '';
        }

        return $useBase64
            ? 'data:image/svg+xml;base64,'.base64_encode($svg)
            : 'data:image/svg+xml;charset=utf-8,'.rawurlencode($svg);
    }

    /**
     * 生成 Base64 编码的 PNG 二维码
     *
     * @param string $text 要编码的文本
     * @param int $size 尺寸（像素）
     */
    public static function generateBase64(string $text, int $size = self::DEFAULT_SIZE): string
    {
        $png = self::generatePng($text, $size);

        return $png !== '' ? base64_encode($png) : '';
    }

    /**
     * 生成 SVG 格式二维码（矢量图，可无损缩放）
     *
     * @param string $text 要编码的文本
     * @param int $size 尺寸（像素）
     */
    public static function generateSvg(string $text, int $size = self::DEFAULT_SIZE): string
    {
        if (empty($text)) {
            return '';
        }

        try {
            return self::makeWriter('svg')
                ->write(self::buildQrCode($text, $size))
                ->getString();
        } catch (Exception $e) {
            Log::error('QR Code: Failed to generate SVG', [
                'text' => $text,
                'size' => $size,
                'exception' => $e->getMessage(),
            ]);

            return '';
        }
    }

    /**
     * @deprecated EPS 格式在 endroid/qr-code v6 起不再支持，降级为 PNG
     */
    public static function generateEps(string $text, int $size = self::DEFAULT_SIZE): string
    {
        Log::warning('QR Code: EPS format is no longer supported, falling back to PNG', ['text' => $text]);

        return self::generatePng($text, $size);
    }

    /**
     * 生成自定义颜色与错误纠正级别的二维码
     *
     * @param string $text 要编码的文本
     * @param string $format 输出格式：png | svg
     * @param int $size 尺寸（像素）
     * @param string $style 已废弃参数，保留以向后兼容
     * @param string $eyeStyle 已废弃参数，保留以向后兼容
     * @param array<int, int> $foregroundColor 前景色 [r, g, b]
     * @param array<int, int> $backgroundColor 背景色 [r, g, b]
     * @param int $margin 边距（像素）
     * @param string $errorCorrection 错误纠正级别：L | M | Q | H
     */
    public static function generateCustom(
        string $text,
        string $format = 'png',
        int $size = self::DEFAULT_SIZE,
        string $style = 'square',
        string $eyeStyle = 'square',
        array $foregroundColor = [0, 0, 0],
        array $backgroundColor = [255, 255, 255],
        int $margin = 2,
        string $errorCorrection = 'M',
    ): string {
        if (empty($text)) {
            return '';
        }

        if ($style !== 'square' || $eyeStyle !== 'square') {
            Log::warning('QR Code: Style and eyeStyle parameters are no longer supported', [
                'style' => $style,
                'eyeStyle' => $eyeStyle,
            ]);
        }

        try {
            $qrCode = new QrCode(
                data: $text,
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: self::resolveErrorCorrectionLevel($errorCorrection),
                size: $size,
                margin: $margin,
                foregroundColor: self::colorFromArray($foregroundColor),
                backgroundColor: self::colorFromArray($backgroundColor),
            );

            return self::makeWriter($format)->write($qrCode)->getString();
        } catch (\Exception $e) {
            Log::error('QR Code: Failed to generate custom QR code', [
                'text' => $text,
                'format' => $format,
                'exception' => $e->getMessage(),
            ]);

            return '';
        }
    }

    /**
     * @deprecated 渐变效果在 endroid/qr-code v6 起不再支持，降级为普通 SVG
     *
     * @param array<int, int> $startColor
     * @param array<int, int> $endColor
     * @param array<int, int> $backgroundColor
     */
    public static function generateGradient(
        string $text,
        int $size = self::DEFAULT_SIZE,
        array $startColor = [0, 100, 200],
        array $endColor = [200, 0, 100],
        string $gradientType = 'DIAGONAL',
        array $backgroundColor = [255, 255, 255],
        int $margin = 2,
    ): string {
        Log::warning('QR Code: Gradient effects are no longer supported, falling back to plain SVG', [
            'text' => $text,
            'gradientType' => $gradientType,
        ]);

        return self::generateSvg($text, $size);
    }

    /**
     * 生成嵌入 Logo 的二维码（PNG 格式，内部自动使用 High 错误纠正）
     *
     * @param string $text 要编码的文本
     * @param string $logoPath Logo 图片的绝对路径
     * @param int $size 尺寸（像素）
     * @param float $logoPercentage Logo 宽度占二维码的比例（建议 0.1–0.3）
     * @param array<int, int> $foregroundColor 前景色 [r, g, b]
     * @param array<int, int> $backgroundColor 背景色 [r, g, b]
     */
    public static function generateWithLogo(
        string $text,
        string $logoPath,
        int $size = self::DEFAULT_SIZE,
        float $logoPercentage = 0.2,
        array $foregroundColor = [0, 0, 0],
        array $backgroundColor = [255, 255, 255],
    ): string {
        if (empty($text)) {
            return '';
        }

        if (! file_exists($logoPath)) {
            Log::warning('QR Code: Logo file not found, falling back to plain QR code', ['logoPath' => $logoPath]);

            return self::generatePng($text, $size);
        }

        try {
            $qrCode = new QrCode(
                data: $text,
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::High,
                size: $size,
                margin: self::DEFAULT_MARGIN,
                foregroundColor: self::colorFromArray($foregroundColor),
                backgroundColor: self::colorFromArray($backgroundColor),
            );

            $logo = new Logo(path: $logoPath, resizeToWidth: (int) ($size * $logoPercentage));

            return (new PngWriter())->write($qrCode, $logo)->getString();
        } catch (\Exception $e) {
            Log::error('QR Code: Failed to generate QR code with logo', [
                'text' => $text,
                'logoPath' => $logoPath,
                'exception' => $e->getMessage(),
            ]);

            return self::generatePng($text, $size);
        }
    }

    /**
     * 批量生成多种格式的二维码
     *
     * @param string $text 要编码的文本
     * @param array<int, string> $formats 格式列表，支持：png | svg | base64 | data_url
     * @param int $size 尺寸（像素）
     * @return array<string, string> 格式 => 二维码数据
     */
    public static function generateMultipleFormats(
        string $text,
        array $formats = ['png', 'svg'],
        int $size = self::DEFAULT_SIZE,
    ): array {
        if (empty($text)) {
            return [];
        }

        $results = [];

        foreach ($formats as $format) {
            $results[$format] = match ($format) {
                'png' => self::generatePng($text, $size),
                'svg' => self::generateSvg($text, $size),
                'base64' => self::generateBase64($text, $size),
                'data_url' => self::generateDataUrl($text, $size),
                'eps' => (static function () use ($text): string {
                    Log::warning('QR Code: EPS format is no longer supported, skipping', ['text' => $text]);

                    return '';
                })(),
                default => '',
            };

            if ($results[$format] === '') {
                unset($results[$format]);
            }
        }

        return $results;
    }

    /**
     * 将二维码保存到指定文件路径
     *
     * @param string $text 要编码的文本
     * @param string $filePath 目标文件绝对路径
     * @param string $format 格式：png | svg
     * @param int $size 尺寸（像素）
     */
    public static function saveToFile(
        string $text,
        string $filePath,
        string $format = 'png',
        int $size = self::DEFAULT_SIZE,
    ): bool {
        if (empty($text)) {
            return false;
        }

        try {
            $data = match (mb_strtolower($format)) {
                'svg' => self::generateSvg($text, $size),
                'eps' => self::generateEps($text, $size),
                default => self::generatePng($text, $size),
            };

            if ($data === '') {
                return false;
            }

            $directory = dirname($filePath);
            if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $directory));
            }

            return file_put_contents($filePath, $data) !== false;
        } catch (\Exception $e) {
            Log::error('QR Code: Failed to save QR code to file', [
                'text' => $text,
                'filePath' => $filePath,
                'format' => $format,
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * 生成带底部文字标签的二维码
     *
     * @param string $text 要编码的文本
     * @param string $labelText 标签文字
     * @param int $size 尺寸（像素）
     * @param string $format 格式：png | svg
     * @param array<int, int> $labelColor 标签色 [r, g, b]
     * @param int $fontSize 字体大小（像素）
     */
    public static function generateWithLabel(
        string $text,
        string $labelText,
        int $size = self::DEFAULT_SIZE,
        string $format = 'png',
        array $labelColor = [0, 0, 0],
        int $fontSize = 16,
    ): string {
        if (empty($text)) {
            return '';
        }

        try {
            $label = new Label(text: $labelText, textColor: self::colorFromArray($labelColor));

            return self::makeWriter($format)
                ->write(self::buildQrCode($text, $size), null, $label)
                ->getString();
        } catch (\Exception $e) {
            Log::error('QR Code: Failed to generate QR code with label', [
                'text' => $text,
                'labelText' => $labelText,
                'exception' => $e->getMessage(),
            ]);

            return '';
        }
    }

    /**
     * 生成同时带 Logo 和标签的二维码（PNG 格式）
     *
     * @param string $text 要编码的文本
     * @param string $logoPath Logo 图片的绝对路径
     * @param string $labelText 标签文字
     * @param int $size 尺寸（像素）
     * @param float $logoPercentage Logo 占比（建议 0.1–0.3）
     * @param array<int, int> $labelColor 标签色 [r, g, b]
     */
    public static function generateWithLogoAndLabel(
        string $text,
        string $logoPath,
        string $labelText,
        int $size = self::DEFAULT_SIZE,
        float $logoPercentage = 0.2,
        array $labelColor = [0, 0, 0],
    ): string {
        if (empty($text)) {
            return '';
        }

        if (! file_exists($logoPath)) {
            Log::warning('QR Code: Logo file not found, generating with label only', ['logoPath' => $logoPath]);

            return self::generateWithLabel($text, $labelText, $size, 'png', $labelColor);
        }

        try {
            $qrCode = new QrCode(
                data: $text,
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::High,
                size: $size,
                margin: self::DEFAULT_MARGIN,
                foregroundColor: new Color(0, 0, 0),
                backgroundColor: new Color(255, 255, 255),
            );

            $logo = new Logo(path: $logoPath, resizeToWidth: (int) ($size * $logoPercentage));
            $label = new Label(text: $labelText, textColor: self::colorFromArray($labelColor));

            return (new PngWriter())->write($qrCode, $logo, $label)->getString();
        } catch (\Exception $e) {
            Log::error('QR Code: Failed to generate QR code with logo and label', [
                'text' => $text,
                'logoPath' => $logoPath,
                'labelText' => $labelText,
                'exception' => $e->getMessage(),
            ]);

            return self::generateWithLabel($text, $labelText, $size, 'png', $labelColor);
        }
    }

    // ==================== 私有辅助方法 ====================

    /**
     * 构建标准 QrCode 实例（黑白配色，Medium 纠错级别）
     */
    private static function buildQrCode(string $text, int $size): QrCode
    {
        return new QrCode(
            data: $text,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: $size,
            margin: self::DEFAULT_MARGIN,
            foregroundColor: new Color(0, 0, 0),
            backgroundColor: new Color(255, 255, 255),
        );
    }

    /**
     * 根据格式字符串返回对应的 Writer 实例
     */
    private static function makeWriter(string $format): WriterInterface
    {
        return match (mb_strtolower($format)) {
            'svg' => new SvgWriter(),
            default => new PngWriter(),
        };
    }

    /**
     * 将 [r, g, b] 数组转换为 Color 实例
     *
     * @param array<int, int> $rgb
     */
    private static function colorFromArray(array $rgb): Color
    {
        return new Color($rgb[0] ?? 0, $rgb[1] ?? 0, $rgb[2] ?? 0);
    }

    /**
     * 将错误纠正级别字符串解析为枚举值
     */
    private static function resolveErrorCorrectionLevel(string $level): ErrorCorrectionLevel
    {
        return match (mb_strtoupper($level)) {
            'L' => ErrorCorrectionLevel::Low,
            'Q' => ErrorCorrectionLevel::Quartile,
            'H' => ErrorCorrectionLevel::High,
            default => ErrorCorrectionLevel::Medium,
        };
    }
}
