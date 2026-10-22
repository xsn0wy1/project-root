<?php
declare(strict_types=1);
namespace Hospital\Http;

final class Response
{
    public static function finish($data, int $status): void
    {
        ignore_user_abort(true);
        ini_set('zlib.output_compression', '0');
        ob_start();
        self::json($data, $status);
        $body = ob_get_clean();
        header('Content-Length: ' . strlen($body));
        header('Connection: close');
        echo $body;
        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
        else { while (ob_get_level() > 0) ob_end_flush(); flush(); }
    }

    public static function json($data, int $status = 200, array $meta = []): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Language: ' . \Hospital\I18n::locale());
        echo json_encode(['success' => true, 'data' => $data, 'meta' => ['request_id' => $GLOBALS['request_id']] + $meta], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public static function error(ApiException $error): void
    {
        http_response_code($error->status);
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Language: ' . \Hospital\I18n::locale());
        $payload = ['code' => $error->errorCode, 'message' => $error->getMessage()];
        if ($error->fields) $payload['fields'] = $error->fields;
        echo json_encode(['success' => false, 'error' => $payload, 'meta' => ['request_id' => $GLOBALS['request_id']]], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
