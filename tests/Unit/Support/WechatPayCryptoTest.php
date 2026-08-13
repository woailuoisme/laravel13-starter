<?php

declare(strict_types=1);

use App\Exceptions\WePayException;
use App\Services\Pay\WechatPayCrypto;
use WeChatPay\Crypto\AesGcm;

function wechat_pay_crypto(string $type = 'native', array $config = []): WechatPayCrypto
{
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    assert($key instanceof OpenSSLAsymmetricKey, 'Failed to generate OpenSSL private key');

    return new WechatPayCrypto(
        ['mch_id' => 'mch', 'key' => '0123456789abcdef0123456789abcdef', ...$config],
        $key,
        $type,
    );
}

describe('WechatPayCrypto::signPayData', function () {
    it('adds paySign for the JSAPI type', function () {
        $payload = wechat_pay_crypto('js')->signPayData([
            'appId' => 'app-id',
            'timeStamp' => '1700000000',
            'nonceStr' => 'nonce',
            'package' => 'prepay_id=wx123',
            'signType' => 'RSA',
        ]);

        expect($payload)
            ->toHaveKey('paySign')
            ->and($payload['paySign'])
            ->toBeString()
            ->and(mb_strlen($payload['paySign']))
            ->toBeGreaterThan(0);
    });

    it('adds sign for the App type', function () {
        $payload = wechat_pay_crypto('app')->signPayData([
            'appid' => 'app-id',
            'partnerid' => 'mch',
            'prepayid' => 'wx123',
            'package' => 'Sign=WXPay',
            'noncestr' => 'nonce',
            'timestamp' => '1700000000',
        ]);

        expect($payload)
            ->toHaveKey('sign')
            ->and($payload['sign'])
            ->toBeString()
            ->and(mb_strlen($payload['sign']))
            ->toBeGreaterThan(0);
    });

    it('leaves the payload untouched for non-signing types', function () {
        $payload = ['code_url' => 'weixin://wxpay/bizpayurl?pr=abc'];

        expect(wechat_pay_crypto('native')->signPayData($payload))->toBe($payload);
    });
});

describe('WechatPayCrypto::verifySignature', function () {
    it('returns false when required headers are missing', function () {
        $crypto = wechat_pay_crypto();

        expect($crypto->verifySignature([], '{}'))
            ->toBeFalse()
            ->and($crypto->verifySignature(['wechatpay-signature' => ['sig']], '{}'))
            ->toBeFalse();
    });
});

describe('WechatPayCrypto::decryptResource', function () {
    it('decrypts an AES-GCM encrypted resource', function () {
        $config = ['key' => '0123456789abcdef0123456789abcdef'];
        $crypto = wechat_pay_crypto('native', $config);

        $resource = [
            'ciphertext' => AesGcm::encrypt(
                '{"out_trade_no":"o123","trade_state":"SUCCESS"}',
                $config['key'],
                $nonce = 'abcdefghijklmnop',
                $aad = 'transaction',
            ),
            'nonce' => $nonce,
            'associated_data' => $aad,
        ];

        expect($crypto->decryptResource($resource))->toBe([
            'out_trade_no' => 'o123',
            'trade_state' => 'SUCCESS',
        ]);
    });
});

describe('WechatPayCrypto::resolveSerial', function () {
    it('returns the configured certificate_serial', function () {
        $crypto = wechat_pay_crypto('native', ['certificate_serial' => 'ABCDEF']);

        expect($crypto->resolveSerial())->toBe('ABCDEF');
    });

    it('throws a config error when neither serial nor cert path is resolvable', function () {
        $crypto = wechat_pay_crypto('native', ['certificate_serial' => '', 'cert_path' => '']);

        $crypto->resolveSerial();
    })->throws(WePayException::class);
});

describe('WechatPayCrypto::loadLocalCerts', function () {
    it('returns an empty array when no platform certs exist on disk', function () {
        expect(wechat_pay_crypto()->loadLocalCerts())->toBe([]);
    });
});
