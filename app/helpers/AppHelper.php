<?php

declare(strict_types=1);

namespace App\Helpers;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Number;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use JsonException;
use Random\RandomException;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class AppHelper
{
    /**
     * 构建微信 jscode2session 请求 URL
     */
    public static function getWxCodeUrl(string $appId, string $appSecret, string $jsCode): string
    {
        return "https://api.weixin.qq.com/sns/jscode2session?appid={$appId}&secret={$appSecret}&js_code={$jsCode}&grant_type=authorization_code";
    }

    /**
     * 安全的 JSON 编码
     *
     * @throws JsonException 当编码失败时抛出
     */
    public static function json_encode(array $arr): string
    {
        try {
            return json_encode(
                $arr,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
        } catch (JsonException $e) {
            Log::error('JSON encode failed', ['data' => $arr, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * 安全的 JSON 编码（格式化输出）
     *
     * @throws JsonException 当编码失败时抛出
     */
    public static function json_encode_pretty(array $arr): string
    {
        try {
            return json_encode(
                $arr,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
            );
        } catch (JsonException $e) {
            Log::error('JSON pretty encode failed', ['data' => $arr, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * 带舍入模式的浮点数取整
     */
    public static function round(float $num, int $precision = 2, int $mode = PHP_ROUND_HALF_DOWN): float
    {
        return round($num, $precision, $mode);
    }

    /**
     * 安全的 JSON 解码
     *
     * @throws JsonException 当字符串为空或解码失败时抛出
     */
    public static function json_decode(string $str): mixed
    {
        if (empty(mb_trim($str))) {
            throw new JsonException('Empty JSON string provided');
        }

        try {
            return json_decode($str, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            Log::error('JSON decode failed', ['json' => $str, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * 格式化文件大小为人类可读字符串
     *
     * 委托给 Laravel 原生 Number::fileSize()，保持 API 兼容。
     */
    public static function formatFileSize(float|int $bytes): string
    {
        return Number::fileSize((int) max($bytes, 0));
    }

    /**
     * 安全执行 Shell 命令并返回输出
     *
     * @throws Exception | ProcessFailedException
     */
    public static function exec(string $cmd, ?int $timeout = 60): string
    {
        Log::info('Executing shell command', ['command' => $cmd]);

        try {
            $process = Process::fromShellCommandline($cmd);
            $processOutput = '';

            $process->setTimeout($timeout)->run(function ($type, $line) use (&$processOutput): void {
                $processOutput .= $line;
            });

            if ($process->getExitCode() !== 0) {
                $exception = new ProcessFailedException($process);
                Log::error('Shell command failed', [
                    'command' => $cmd,
                    'exit_code' => $process->getExitCode(),
                    'output' => $processOutput,
                    'error_output' => $process->getErrorOutput(),
                ]);
                report($exception);
                throw $exception;
            }

            Log::debug('Shell command executed successfully', [
                'command' => $cmd,
                'output_length' => mb_strlen($processOutput),
            ]);

            return $processOutput;
        } catch (Exception $e) {
            Log::error('Shell command execution error', ['command' => $cmd, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * 获取客户端真实 IP 地址
     *
     * @param bool $allowPrivate 是否允许私有/保留 IP（通常用于本地开发）
     */
    public static function getIp(bool $allowPrivate = false): ?string
    {
        $ip = Request::ip();

        if ($ip && self::isValidIp($ip, $allowPrivate)) {
            return $ip;
        }

        $proxyHeaders = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        ];

        foreach ($proxyHeaders as $header) {
            $value = $_SERVER[$header] ?? null;

            if (empty($value)) {
                continue;
            }

            foreach (array_map('trim', explode(',', $value)) as $candidate) {
                if (self::isValidIp($candidate, $allowPrivate)) {
                    return $candidate;
                }
            }
        }

        return app()->isLocal() ? '127.0.0.1' : null;
    }

    /**
     * 生成带前缀的唯一订单编号
     *
     * @throws RandomException
     */
    public static function generateOrderNo(string $prefix = 'ORD'): string
    {
        return $prefix
            .date('YmdHis')
            .mb_str_pad((string) ((microtime(true) * 10000) % 10000), 4, '0', STR_PAD_LEFT)
            .random_int(100000, 999999);
    }

    /** 生成商城订单编号（前缀 SO）
     * @throws RandomException
     */
    public static function generateShopOrderNo(): string
    {
        return self::generateOrderNo('SO');
    }

    /** 生成产品订单编号（前缀 PO）
     * @throws RandomException
     */
    public static function generateProductOrderNo(): string
    {
        return self::generateOrderNo('PO');
    }

    /** 生成外部交易流水编号（前缀 OT）
     * @throws RandomException
     */
    public static function generateOutTradeNo(): string
    {
        return self::generateOrderNo('OT');
    }

    /** 生成充值订单编号（前缀 TU）
     * @throws RandomException
     */
    public static function generateTopUpOrderNo(): string
    {
        return self::generateOrderNo('TU');
    }

    /** 生成提现订单编号（前缀 WD） */
    public static function generateWithdrawOrderNo(): string
    {
        return self::generateOrderNo('WD');
    }

    /** 生成退款订单编号（前缀 RF）
     * @throws RandomException
     */
    public static function generateRefundOrderNo(): string
    {
        return self::generateOrderNo('RF');
    }

    /** 生成产品退款单号（前缀 PR）
     * @throws RandomException
     */
    public static function generateProductRefoundNo(): string
    {
        return self::generateOrderNo('PR');
    }

    /**
     * 查询 IP 地址的地理位置信息
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException 当 IP 格式无效时抛出
     */
    public static function getIpInfo(string $ip): array
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new RuntimeException('Invalid IP address format: '.$ip);
        }

        if (
            in_array($ip, ['::1', '127.0.0.1', '0.0.0.0'], true)
            || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
        ) {
            return ['ip' => $ip, 'type' => 'local', 'country' => 'Local', 'region' => 'Local', 'city' => 'Local'];
        }

        try {
            $client = new Client(['timeout' => 10, 'connect_timeout' => 5]);
            $token = config('services.ipinfo.token');
            $response = $client->get("https://ipinfo.io/{$ip}?token={$token}");
            $info = self::json_decode($response->getBody()->getContents());

            Log::debug('IP info retrieved successfully', ['ip' => $ip]);

            return $info;
        } catch (GuzzleException $e) {
            Log::error('Failed to get IP info from ipinfo.io', ['ip' => $ip, 'error' => $e->getMessage()]);

            return ['ip' => $ip, 'type' => 'unknown', 'error' => 'Unable to retrieve location information'];
        } catch (Exception $e) {
            Log::error('Unexpected error while getting IP info', ['ip' => $ip, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * 根据上传文件或文件名字符串生成 MD5 文件名
     *
     * 格式：{md5_hash}.{extension}（无扩展名时省略点）
     */
    public static function generateMd5FileName(UploadedFile|string $file): string
    {
        if ($file instanceof UploadedFile) {
            $md5Hash = md5_file($file->getRealPath());
            $extension = mb_strtolower($file->getClientOriginalExtension());
        } else {
            $pathInfo = pathinfo($file);
            $extension = $pathInfo['extension'] ?? '';
            $md5Hash = md5($pathInfo['filename'] ?? $file);
        }

        return $extension ? "{$md5Hash}.{$extension}" : $md5Hash;
    }

    /**
     * 根据模型类名生成存储路径前缀
     */
    public static function generateFilePath(object|string $model): string
    {
        $className = class_basename($model);

        return Str::plural(mb_strtolower($className)).'/';
    }

    /**
     * 计算多个数组的笛卡尔积
     *
     * @param array<string, array<mixed>> $input
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception 当输入元素不是数组时抛出
     */
    public static function cartesian(array $input): array
    {
        if (empty($input)) {
            return [];
        }

        foreach ($input as $key => $values) {
            if (! is_array($values)) {
                throw new RuntimeException("Input key '{$key}' must be an array");
            }
        }

        $result = [[]];

        foreach ($input as $key => $values) {
            $append = [];

            foreach ($result as $product) {
                foreach ($values as $item) {
                    $product[$key] = $item;
                    $append[] = $product;
                }
            }

            $result = $append;
        }

        return $result;
    }

    /**
     * 格式化任意消息值为字符串
     */
    protected static function formatMessage(mixed $message): string
    {
        if (is_null($message)) {
            return 'null';
        }

        if (is_bool($message)) {
            return $message ? 'true' : 'false';
        }

        if (is_scalar($message)) {
            return (string) $message;
        }

        if (is_array($message)) {
            return var_export($message, true);
        }

        if ($message instanceof Jsonable) {
            try {
                return $message->toJson();
            } catch (Exception $e) {
                Log::warning('Failed to convert Jsonable to JSON', ['error' => $e->getMessage()]);

                return 'Jsonable conversion failed';
            }
        }

        if ($message instanceof Arrayable) {
            try {
                return var_export($message->toArray(), true);
            } catch (Exception $e) {
                Log::warning('Failed to convert Arrayable to array', ['error' => $e->getMessage()]);

                return 'Arrayable conversion failed';
            }
        }

        return (string) $message;
    }

    /**
     * 生成基于微秒时间戳的唯一订单号
     */
    public static function orderNumber(): string
    {
        $today = now()->format('YmdHisu');
        $rand = mb_strtoupper(mb_substr(uniqid(sha1($today), true), 0, 4));

        return $today.$rand;
    }

    /**
     * 生成基于时间戳+毫秒+随机数的订单代码
     *
     * @throws RuntimeException 当随机数生成失败时抛出
     */
    public static function orderCode(): string
    {
        try {
            $now = now();
            $milliseconds = mb_str_pad((string) $now->milli, 3, '0', STR_PAD_LEFT);
            $randomNumber = mb_str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT);

            return $now->timestamp.$milliseconds.$randomNumber;
        } catch (Exception $e) {
            Log::error('Failed to generate order code', ['error' => $e->getMessage()]);
            throw new RuntimeException('Unable to generate order code: '.$e->getMessage());
        }
    }

    /**
     * 验证用户生日数据
     *
     * @param array{day: int|string, month: int|string, year: int|string} $data
     */
    public function validator(array $data): \Illuminate\Contracts\Validation\Validator
    {
        $data['date_of_birth'] = $data['day'].'-'.$data['month'].'-'.$data['year'];
        $currentYear = Carbon::now()->year;
        $minYear = Carbon::now()->subYears(150)->year;

        return Validator::make($data, [
            'year' => "required|integer|between:{$minYear},{$currentYear}",
            'date_of_birth' => 'required|date',
        ]);
    }

    /**
     * 将字节数转换为人类可读的格式（如 1.5 MB）
     */
    public static function readableBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $sizes = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        $i = (int) floor(log($bytes) / log(1024));

        return sprintf('%.2F', $bytes / (1024 ** $i)).' '.$sizes[$i];
    }

    /**
     * 格式化整数为千位分隔符数字字符串
     */
    public static function getNumber(int $input): string
    {
        return number_format($input);
    }

    /**
     * 将大数字转换为人类可读的简写（如 1K、1.5M、2.3B）
     */
    public static function getHumanNumber(int $input, bool $showDecimal = false, int $decimals = 0): string
    {
        $isNegative = $input < 0;
        $absoluteValue = abs($input);
        $decimals = $showDecimal && $decimals === 0 ? 1 : $decimals;

        [$value, $suffix] = match (true) {
            $absoluteValue < 1_000 => [$absoluteValue, ''],
            $absoluteValue < 1_000_000 => [$absoluteValue / 1_000, 'K'],
            $absoluteValue < 1_000_000_000 => [$absoluteValue / 1_000_000, 'M'],
            $absoluteValue < 1_000_000_000_000 => [$absoluteValue / 1_000_000_000, 'B'],
            default => [$absoluteValue / 1_000_000_000_000, 'T'],
        };

        $formattedValue = $showDecimal && $suffix !== ''
            ? number_format($value, $decimals)
            : number_format(floor($value),  decimals: 0);

        return ($isNegative ? '-' : '').$formattedValue.$suffix;
    }

    /**
     * 返回统一结构的 JSON API 响应
     */
    public static function response(
        mixed $data = null,
        ?string $message = null,
        int $code = 200,
        bool $status = true,
    ): JsonResponse {
        if ($code < 100 || $code > 599) {
            Log::warning('Invalid HTTP status code provided', ['code' => $code]);
            $code = 500;
        }

        return response()->json(
            ['status' => $status, 'code' => $code, 'message' => $message, 'data' => $data],
            $code,
        );
    }

    /**
     * 将 QueryException 转换为用户友好的中文错误消息
     */
    public static function getUserFriendlyMessage(QueryException $e): string
    {
        $errorCode = $e->getCode();
        $originalMessage = $e->getMessage();

        Log::debug('Database error occurred', [
            'code' => $errorCode,
            'message' => $originalMessage,
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        $errorCodes = self::getErrorCodeMappings();

        if (isset($errorCodes[$errorCode])) {
            return $errorCodes[$errorCode];
        }

        $friendlyMessage = self::matchErrorByPattern($originalMessage);

        if ($friendlyMessage) {
            return $friendlyMessage;
        }

        Log::warning('Unknown database error', ['code' => $errorCode, 'message' => $originalMessage]);

        return '数据操作失败，请稍后重试。如果问题持续，请联系系统管理员';
    }

    /**
     * 数据库错误码到友好消息的映射表
     *
     * 支持 MySQL、PostgreSQL、SQL Server
     *
     * @return array<int|string, string>
     */
    private static function getErrorCodeMappings(): array
    {
        $unique = '数据已存在，无法重复添加';
        $notNull = '存在必填信息未填写，请检查后重试';
        $foreignKey = '该数据已被关联使用，无法删除';
        $check = '数据格式不符合要求，请检查输入';
        $connFailed = '数据库服务暂时无法连接，请稍后再试';
        $permission = '您没有执行该操作的权限';
        $timeout = '数据库连接超时，请重试操作';
        $dataTooLong = '数据内容过长，请缩短后重试';
        $invalidDatetime = '日期时间格式不正确，请检查输入';
        $invalidFormat = '数据格式不符合要求，请检查输入';
        $tableNotFound = '系统数据结构异常，请联系管理员';
        $columnNotFound = '数据格式有误，请联系管理员处理';
        $syntaxError = '系统数据处理出错，请联系管理员';

        return [
            // MySQL
            1062 => $unique,
            1048 => $notNull,
            1364 => $notNull,
            1451 => $foreignKey,
            1217 => $foreignKey,
            1452 => '关联的数据不存在，请检查后重试',
            1216 => '关联数据不完整，请先完善关联信息',
            1045 => $permission,
            2002 => $connFailed,
            2005 => '数据库地址不正确，服务暂时无法使用',
            2013 => $timeout,
            1054 => $columnNotFound,
            1064 => $syntaxError,
            1146 => $tableNotFound,
            1396 => '操作失败，数据状态不符合要求',
            1406 => $dataTooLong,
            1292 => $invalidDatetime,
            1265 => $invalidFormat,
            1366 => '字符编码不正确，请检查输入内容',

            // PostgreSQL
            '23505' => $unique,
            '23503' => $foreignKey,
            '23502' => $notNull,
            '23514' => $check,
            '42501' => $permission,
            '42P01' => $tableNotFound,
            '42703' => $columnNotFound,
            '08006' => $connFailed,
            '57P03' => '数据库服务暂时不可用，请稍后再试',
            '22001' => $dataTooLong,
            '22008' => $invalidDatetime,
            '22P02' => $invalidFormat,

            // SQL Server
            2627 => $unique,
            515 => $notNull,
            547 => $foreignKey,
            102 => $syntaxError,
            4060 => $connFailed,
            18456 => $permission,
            8152 => $dataTooLong,
            241 => $invalidDatetime,
        ];
    }

    /**
     * 通过消息字符串的模式匹配获取友好错误消息
     */
    private static function matchErrorByPattern(string $originalMessage): ?string
    {
        $message = mb_strtolower($originalMessage);

        $patterns = [
            ['patterns' => ['unique constraint', 'duplicate entry', 'duplicate key'], 'message' => '数据已存在，无法重复添加'],
            ['patterns' => ['foreign key constraint', 'cannot delete or update a parent row'], 'message' => '该数据与其他信息相关联，无法执行此操作'],
            ['patterns' => ['not null constraint', 'column cannot be null'], 'message' => '存在必填信息未填写，请检查后重试'],
            ['patterns' => ['check constraint'], 'message' => '数据格式不符合要求，请检查输入'],
            ['patterns' => ['connection refused', 'could not connect', 'connection timed out'], 'message' => '数据库连接失败，请稍后再试'],
            ['patterns' => ['access denied', 'permission denied', 'authentication failed'], 'message' => '您没有执行该操作的权限'],
            ['patterns' => ['timeout', 'lock wait timeout'], 'message' => '操作超时，请重试'],
            ['patterns' => ['table', 'doesn\'t exist', 'not found'], 'message' => '系统数据结构异常，请联系管理员'],
            ['patterns' => ['unknown column', 'column not found'], 'message' => '数据格式有误，请联系管理员处理'],
            ['patterns' => ['data too long', 'string or binary data would be truncated'], 'message' => '数据内容过长，请缩短后重试'],
            ['patterns' => ['incorrect datetime value', 'invalid datetime format'], 'message' => '日期时间格式不正确，请检查输入'],
            ['patterns' => ['incorrect string value', 'invalid character'], 'message' => '字符编码不正确，请检查输入内容'],
        ];

        foreach ($patterns as $group) {
            foreach ($group['patterns'] as $pattern) {
                if (str_contains($message, $pattern)) {
                    return $group['message'];
                }
            }
        }

        return null;
    }

    /**
     * 验证 IP 地址格式，生产环境下可排除私有/保留地址
     */
    private static function isValidIp(string $ip, bool $allowPrivate): bool
    {
        $flags = FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6;

        if (! $allowPrivate && ! app()->isLocal()) {
            $flags |= FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
        }

        return (bool) filter_var($ip, FILTER_VALIDATE_IP, $flags);
    }
}
