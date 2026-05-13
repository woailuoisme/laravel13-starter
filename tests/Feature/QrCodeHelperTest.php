<?php

use App\Helpers\QrCodeHelper;
use Illuminate\Support\Facades\Log;

it('generates png svg and data url output', function () {
    $png = QrCodeHelper::generatePng('https://example.com', 120);
    $svg = QrCodeHelper::generateSvg('https://example.com', 120);
    $dataUrl = QrCodeHelper::generateDataUrl('https://example.com', 120);

    expect($png)
        ->not->toBe('')
        ->toStartWith("\x89PNG\r\n\x1A\n")
        ->and($svg)
        ->not->toBe('')
        ->toContain('<svg')
        ->and($dataUrl)
        ->toStartWith('data:image/png;base64,');
});

it('generates base64 png output', function () {
    $decoded = base64_decode(QrCodeHelper::generateBase64('https://example.com', 120), true);

    expect($decoded)
        ->not->toBeFalse()
        ->toStartWith("\x89PNG\r\n\x1A\n");
});

it('generates svg data urls with url encoded and base64 encoding', function () {
    $encodedPrefix = 'data:image/svg+xml;charset=utf-8,';
    $base64Prefix = 'data:image/svg+xml;base64,';

    $encoded = QrCodeHelper::generateSvgDataUrl('https://example.com', 120);
    $base64 = QrCodeHelper::generateSvgDataUrl('https://example.com', 120, true);

    expect($encoded)
        ->toStartWith($encodedPrefix)
        ->and(rawurldecode(mb_substr($encoded, mb_strlen($encodedPrefix))))
        ->toContain('<svg')
        ->and($base64)
        ->toStartWith($base64Prefix)
        ->and(base64_decode(mb_substr($base64, mb_strlen($base64Prefix)), true))
        ->toContain('<svg');
});

it('falls back from eps to png', function () {
    Log::shouldReceive('warning')
        ->once()
        ->with('QR Code: EPS format is no longer supported, falling back to PNG', ['text' => 'https://example.com']);

    /** @noinspection PhpDeprecationInspection */
    $eps = QrCodeHelper::generateEps('https://example.com', 120);

    expect($eps)
        ->toStartWith("\x89PNG\r\n\x1A\n");
});

it('generates custom qr codes in supported formats', function () {
    $png = QrCodeHelper::generateCustom(
        text: 'https://example.com',
        format: 'png',
        size: 120,
        foregroundColor: [10, 20, 30],
        backgroundColor: [240, 240, 240],
        errorCorrection: 'H',
    );
    $svg = QrCodeHelper::generateCustom(
        text: 'https://example.com',
        format: 'svg',
        size: 120,
        errorCorrection: 'Q',
    );

    expect($png)
        ->toStartWith("\x89PNG\r\n\x1A\n")
        ->and($svg)
        ->toContain('<svg');
});

it('warns about unsupported custom style parameters and keeps generating output', function () {
    Log::shouldReceive('warning')
        ->once()
        ->with('QR Code: Style and eyeStyle parameters are no longer supported', [
            'style' => 'round',
            'eyeStyle' => 'circle',
        ]);

    expect(QrCodeHelper::generateCustom('https://example.com', 'png', 120, 'round', 'circle'))
        ->toStartWith("\x89PNG\r\n\x1A\n");
});

it('falls back from gradient to plain svg', function () {
    Log::shouldReceive('warning')
        ->once()
        ->with('QR Code: Gradient effects are no longer supported, falling back to plain SVG', [
            'text' => 'https://example.com',
            'gradientType' => 'VERTICAL',
        ]);

    expect(QrCodeHelper::generateGradient('https://example.com', 120, gradientType: 'VERTICAL'))
        ->toContain('<svg');
});

it('falls back to plain png when logo file is missing', function () {
    $missingLogo = storage_path('framework/testing/qrcodes/missing-logo.png');

    Log::shouldReceive('warning')
        ->once()
        ->with('QR Code: Logo file not found, falling back to plain QR code', ['logoPath' => $missingLogo]);

    expect(QrCodeHelper::generateWithLogo('https://example.com', $missingLogo, 120))
        ->toStartWith("\x89PNG\r\n\x1A\n");
});

it('generates qr codes with labels in supported formats', function () {
    $png = QrCodeHelper::generateWithLabel('https://example.com', 'Example', 120, 'png');
    $svg = QrCodeHelper::generateWithLabel('https://example.com', 'Example', 120, 'svg');

    expect($png)
        ->toStartWith("\x89PNG\r\n\x1A\n")
        ->and($svg)
        ->toContain('<svg');
});

it('falls back to label only output when logo and label logo file is missing', function () {
    $missingLogo = storage_path('framework/testing/qrcodes/missing-logo-with-label.png');

    Log::shouldReceive('warning')
        ->once()
        ->with('QR Code: Logo file not found, generating with label only', ['logoPath' => $missingLogo]);

    expect(QrCodeHelper::generateWithLogoAndLabel('https://example.com', $missingLogo, 'Example', 120))
        ->toStartWith("\x89PNG\r\n\x1A\n");
});

it('omits unsupported or empty batch formats', function () {
    Log::shouldReceive('warning')
        ->once()
        ->with('QR Code: EPS format is no longer supported, skipping', ['text' => 'https://example.com']);

    $results = QrCodeHelper::generateMultipleFormats('https://example.com', [
        'png',
        'svg',
        'base64',
        'data_url',
        'eps',
        'unknown',
    ], 120);

    expect($results)
        ->toHaveKeys(['png', 'svg', 'base64', 'data_url'])
        ->not->toHaveKey('eps')
        ->not->toHaveKey('unknown');
});

it('saves generated qr code to a nested file path', function () {
    $path = storage_path('framework/testing/qrcodes/example.svg');

    @unlink($path);

    expect(QrCodeHelper::saveToFile('https://example.com', $path, 'svg', 120))->toBeTrue()
        ->and(file_get_contents($path))->toContain('<svg');

    @unlink($path);
});

it('preserves the previous empty text behavior', function () {
    expect(QrCodeHelper::generatePng(''))->toBe('')
        ->and(QrCodeHelper::generatePng('0'))->toBe('')
        ->and(QrCodeHelper::generateMultipleFormats(''))->toBe([]);
});
