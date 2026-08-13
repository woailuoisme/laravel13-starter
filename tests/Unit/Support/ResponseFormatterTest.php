<?php

declare(strict_types=1);

use App\Enums\Gender;
use App\Support\ResponseFormatter;
use Illuminate\Pagination\LengthAwarePaginator;

describe('ResponseFormatter::response', function () {
    it('returns a JSON response with the unified structure', function () {
        $response = ResponseFormatter::response(data: ['id' => 1], message: 'ok', code: 200);

        expect($response->status())
            ->toBe(200)
            ->and($response->getData(true))
            ->toBe([
                'success' => true,
                'code' => 200,
                'message' => 'ok',
                'data' => ['id' => 1],
            ]);
    });

    it('uses the provided status flag', function () {
        $response = ResponseFormatter::response(data: null, message: 'fail', code: 422, status: false);

        expect($response->status())
            ->toBe(422)
            ->and($response->getData(true))
            ->toMatchArray([
                'success' => false,
            ]);
    });

    it('clamps out-of-range status codes to 500', function () {
        $response = ResponseFormatter::response(data: null, message: 'bad', code: 99);

        expect($response->status())
            ->toBe(500)
            ->and($response->getData(true))
            ->toMatchArray([
                'code' => 500,
            ]);
    });
});

describe('ResponseFormatter::sendSuccess', function () {
    it('returns a success response with the canonical shape', function () {
        $response = ResponseFormatter::sendSuccess('ok', ['id' => 1]);

        expect($response->status())
            ->toBe(200)
            ->and($response->getData(true))
            ->toBe([
                'success' => true,
                'message' => 'ok',
                'code' => 200,
                'data' => ['id' => 1],
            ]);
    });

    it('omits data when not provided and honours customCode', function () {
        $response = ResponseFormatter::sendSuccess('ok', null, 201, 1001);

        expect($response->status())
            ->toBe(201)
            ->and($response->getData(true))
            ->toBe([
                'success' => true,
                'message' => 'ok',
                'code' => 1001,
            ]);
    });
});

describe('ResponseFormatter::sendError', function () {
    it('returns an error response with the canonical shape', function () {
        $response = ResponseFormatter::sendError('nope');

        expect($response->status())
            ->toBe(400)
            ->and($response->getData(true))
            ->toBe([
                'success' => false,
                'message' => 'nope',
                'code' => 400,
            ]);
    });

    it('includes errors and customCode when provided', function () {
        $response = ResponseFormatter::sendError('validation failed', 422, null, 2002, ['name' => 'required']);

        expect($response->getData(true))->toBe([
            'success' => false,
            'message' => 'validation failed',
            'code' => 2002,
            'errors' => ['name' => 'required'],
        ]);
    });
});

describe('ResponseFormatter::sendEnumError', function () {
    it('uses the enum value as code and httpStatus for the status', function () {
        $response = ResponseFormatter::sendEnumError(Gender::Female, 422);

        expect($response->status())
            ->toBe(422)
            ->and($response->getData(true))
            ->toMatchArray([
                'code' => 400,
            ]);
    });
});

describe('ResponseFormatter::paginatorData', function () {
    it('builds the paginated payload from a LengthAwarePaginator', function () {
        $paginator = new LengthAwarePaginator(
            collect([['id' => 1], ['id' => 2]]),
            10,
            2,
            3,
        );

        $data = ResponseFormatter::paginatorData($paginator);

        expect($data['meta'])
            ->toBe([
                'total' => 10,
                'per_page' => 2,
                'last_page' => 5,
                'current_page' => 3,
                'from' => 5,
                'to' => 6,
            ])
            ->and($data['items'])
            ->toBeArray()
            ->and($data['items'])
            ->toHaveCount(2);
    });
});

describe('ResponseFormatter::makeResponse and makeError', function () {
    it('builds a success array', function () {
        expect(ResponseFormatter::makeResponse('ok', ['id' => 1]))->toBe([
            'success' => true,
            'message' => 'ok',
            'data' => ['id' => 1],
        ]);
    });

    it('builds an error array and omits empty data', function () {
        expect(ResponseFormatter::makeError('fail'))
            ->toBe([
                'success' => false,
                'message' => 'fail',
            ])
            ->and(ResponseFormatter::makeError('fail', ['field' => 'x']))
            ->toBe([
                'success' => false,
                'message' => 'fail',
                'data' => ['field' => 'x'],
            ]);
    });
});
