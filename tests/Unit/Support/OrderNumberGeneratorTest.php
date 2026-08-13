<?php

declare(strict_types=1);

use App\Support\OrderNumberGenerator;

describe('OrderNumberGenerator::generateOrderNo', function () {
    it('generates an order number with the correct prefix', function () {
        expect(OrderNumberGenerator::generateOrderNo('ORD'))->toStartWith('ORD');
    });

    it('generates a unique order number each time', function () {
        $orderNo1 = OrderNumberGenerator::generateOrderNo();
        $orderNo2 = OrderNumberGenerator::generateOrderNo();
        expect($orderNo1)->not->toBe($orderNo2);
    });

    it('generates a shop order number with SO prefix', function () {
        expect(OrderNumberGenerator::generateShopOrderNo())->toStartWith('SO');
    });

    it('generates a product order number with PO prefix', function () {
        expect(OrderNumberGenerator::generateProductOrderNo())->toStartWith('PO');
    });

    it('generates an out trade number with OT prefix', function () {
        expect(OrderNumberGenerator::generateOutTradeNo())->toStartWith('OT');
    });

    it('generates a top up order number with TU prefix', function () {
        expect(OrderNumberGenerator::generateTopUpOrderNo())->toStartWith('TU');
    });

    it('generates a withdraw order number with WD prefix', function () {
        expect(OrderNumberGenerator::generateWithdrawOrderNo())->toStartWith('WD');
    });

    it('generates a refund order number with RF prefix', function () {
        expect(OrderNumberGenerator::generateRefundOrderNo())->toStartWith('RF');
    });

    it('generates a product refund number with PR prefix', function () {
        expect(OrderNumberGenerator::generateProductRefoundNo())->toStartWith('PR');
    });
});

describe('OrderNumberGenerator::orderNumber', function () {
    it('generates a unique order number', function () {
        expect(OrderNumberGenerator::orderNumber())->not->toBe(OrderNumberGenerator::orderNumber());
    });
});

describe('OrderNumberGenerator::orderCode', function () {
    it('generates a numeric order code', function () {
        expect(OrderNumberGenerator::orderCode())->toBeNumeric();
    });

    it('generates a unique order code', function () {
        expect(OrderNumberGenerator::orderCode())->not->toBe(OrderNumberGenerator::orderCode());
    });
});
