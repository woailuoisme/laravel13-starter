<?php

declare(strict_types=1);

use App\Helpers\AppHelper;

describe('AppHelper::json_encode', function () {
    it('encodes an array to a JSON string', function () {
        $result = AppHelper::json_encode(['key' => 'value', 'emoji' => '你好']);
        expect($result)->toBe('{"key":"value","emoji":"你好"}');
    });

    it('does not escape unicode or slashes', function () {
        $result = AppHelper::json_encode(['url' => 'https://example.com/test', 'cn' => '中文']);
        expect($result)->toContain('https://example.com/test')->toContain('中文');
    });

    it('throws JsonException on un-encodable values', function () {
        // Resources cannot be JSON-encoded
        $resource = fopen('php://memory', 'r');
        AppHelper::json_encode(['res' => $resource]);
        fclose($resource);
    })->throws(JsonException::class);
});

describe('AppHelper::json_decode', function () {
    it('decodes a valid JSON string to an array', function () {
        $result = AppHelper::json_decode('{"key":"value"}');
        expect($result)->toBe(['key' => 'value']);
    });

    it('throws JsonException on empty string', function () {
        AppHelper::json_decode('');
    })->throws(JsonException::class);

    it('throws JsonException on whitespace-only string', function () {
        AppHelper::json_decode('   ');
    })->throws(JsonException::class);

    it('throws JsonException on invalid JSON', function () {
        AppHelper::json_decode('not-json');
    })->throws(JsonException::class);
});

describe('AppHelper::formatFileSize', function () {
    it('formats bytes correctly', function () {
        expect(AppHelper::formatFileSize(0))->toBe('0 B');
        expect(AppHelper::formatFileSize(512))->toBe('512 B');
    });

    it('formats kilobytes correctly', function () {
        expect(AppHelper::formatFileSize(1024))->toBe('1 KB');
        expect(AppHelper::formatFileSize(2048))->toBe('2 KB');
    });

    it('formats megabytes correctly', function () {
        expect(AppHelper::formatFileSize(1024 * 1024))->toBe('1 MB');
    });

    it('formats gigabytes correctly', function () {
        expect(AppHelper::formatFileSize(1024 * 1024 * 1024))->toBe('1 GB');
    });
});

describe('AppHelper::generateOrderNo', function () {
    it('generates an order number with the correct prefix', function () {
        expect(AppHelper::generateOrderNo('ORD'))->toStartWith('ORD');
    });

    it('generates a unique order number each time', function () {
        $orderNo1 = AppHelper::generateOrderNo();
        $orderNo2 = AppHelper::generateOrderNo();
        expect($orderNo1)->not->toBe($orderNo2);
    });

    it('generates a shop order number with SO prefix', function () {
        expect(AppHelper::generateShopOrderNo())->toStartWith('SO');
    });

    it('generates a product order number with PO prefix', function () {
        expect(AppHelper::generateProductOrderNo())->toStartWith('PO');
    });
});

describe('AppHelper::round', function () {
    it('rounds a float to 2 decimal places by default', function () {
        expect(AppHelper::round(3.14159))->toBe(3.14);
    });

    it('rounds down with PHP_ROUND_HALF_DOWN mode', function () {
        expect(AppHelper::round(2.345, 2, PHP_ROUND_HALF_DOWN))->toBe(2.34);
    });
});

describe('AppHelper::getIpInfo', function () {
    it('returns local info for loopback ip', function () {
        $result = AppHelper::getIpInfo('127.0.0.1');
        expect($result)->toHaveKeys(['ip', 'type'])->and($result['type'])->toBe('local');
    });

    it('throws RuntimeException for invalid ip', function () {
        AppHelper::getIpInfo('not-an-ip');
    })->throws(RuntimeException::class);
});
