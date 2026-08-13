<?php

declare(strict_types=1);

use App\Helpers\AppHelper;
use Illuminate\Database\QueryException;

describe('AppHelper::json_decode', function () {
    it('throws JsonException on empty or blank string', function () {
        AppHelper::json_decode('   ');
    })->throws(JsonException::class);

    it('decodes valid json string', function () {
        expect(AppHelper::json_decode('{"key": "value", "num": 123}'))->toBe(['key' => 'value', 'num' => 123]);
    });
});

describe('AppHelper::formatFileSize and readableBytes', function () {
    it('formats bytes to human readable sizes', function () {
        expect(AppHelper::formatFileSize(1024))
            ->toBe('1 KB')
            ->and(AppHelper::readableBytes(1_048_576))
            ->toBe('1 MB')
            ->and(AppHelper::readableBytes(0))
            ->toBe('0 B');
    });
});

describe('AppHelper::getNumber', function () {
    it('formats numbers with commas', function () {
        expect(AppHelper::getNumber(1000))->toBe('1,000')->and(AppHelper::getNumber(1_234_567))->toBe('1,234,567');
    });
});

describe('AppHelper::getUserFriendlyMessage', function () {
    it('returns mapped friendly message for known SQL error code', function () {
        $exception = new QueryException(
            'default',
            'SELECT * FROM users',
            [],
            new Exception('Duplicate entry for key', 1062),
        );

        $msg = AppHelper::getUserFriendlyMessage($exception);
        expect($msg)->toBe('数据已存在，无法重复添加');
    });

    it('returns matched pattern message for foreign key violation', function () {
        $exception = new QueryException(
            'default',
            'INSERT INTO orders',
            [],
            new Exception('a foreign key constraint fails (`test`.`orders`)', 9999),
        );

        $msg = AppHelper::getUserFriendlyMessage($exception);
        expect($msg)->toBe('该数据与其他信息相关联，无法执行此操作');
    });
});
