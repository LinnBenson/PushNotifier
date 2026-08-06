<?php

namespace App\Plugins\PushNotifier\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Plugins\PushNotifier\Models\NotifierRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Mail\Message;
use App\Plugins\PushNotifier\Support\BuildMessageService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * AdminController
 * 推送通知服务入口
 * @package App\Plugins\PushNotifier\Controllers
 */
class PushController extends Controller {
    // 默认使用 HTTP 模式
    public string $model = 'http';
    public function setCli() { $this->model = 'cli'; }
    /**
     * 主入口
     * @return JsonResponse JSON 响应
     */
    public function index( Request $request ): JsonResponse {
        // 验证 Token
        $token = $request->input( 'token', null );
        if ( !$token || $token !== plugin( 'PushNotifier' )->config( 'token' ) ) {
            return echoJson( 3, ['base.error.403'] );
        }
        $uid = 0;
        // 验证并读取传入参数
        $input = $request->all();
        if ( !array_key_exists( 'type', $input ) ) {
            $input['type'] = plugin( 'PushNotifier' )->config( 'default' );
        }
        $validator = Validator::make( $input, [
            'type' => ['required', 'string', Rule::in( array_keys( NotifierRecord::TYPES ) )],
            'source' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:10000'],
        ] );
        if ( $validator->fails() ) {
            return echoJson( 2, ['base.error.input'] );
        }
        $validated = $validator->validated();
        $type = $validated['type'];
        $data = BuildMessageService::build( $validated['source'] ?? null, $validated['title'], $validated['content'] );
        // 获取接收人
        $recipient = $this->getRecipient( $uid, $type );
        if ( !$recipient ) { return echoJson( 2, ['base.error.prohibit'] ); }
        // 查询近 15 秒内是否有相同的请求记录，防止重复发送
        $duplicateKey = 'push_notifier:duplicate:'.hash(
            'sha256',
            json_encode( [
                'type' => $type,
                'recipient' => $recipient,
                'source' => $data['source'],
                'title' => $data['title'],
                'content' => $data['content'],
            ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES )
        );
        if ( !Cache::add( $duplicateKey, true, now()->addSeconds( 15 ) ) ) {
            return echoJson( 2, ['base.error.limit'] );
        }
        if ( getQueueWorkerStatus() ) {
            // 队列工作进程正在运行，使用异步发送
            $async = plugin( 'PushNotifier' )->send( $data['title'], $data['content'] )
                    ->type( $type )
                    ->source( $data['source'] )
                    ->async( $recipient );
            if ( $async ) {
                return echoJson( true, ['base.send'] );
            } else {
                // 没有成功发送，释放占位，允许再次尝试
                Cache::forget( $duplicateKey );
                return echoJson( false, ['base.send'] );
            }
        }
        switch ( $type ) {
            case 'telegram':
                return $this->telegram( $uid, $recipient, $data['source'] ?? null, $data['title'], $data['content'] );
            case 'bark':
                return $this->bark( $uid, $recipient, $data['source'] ?? null, $data['title'], $data['content'] );
            case 'email':
                return $this->email( $uid, $recipient, $data['source'] ?? null, $data['title'], $data['content'] );
            default:
                return echoJson( 2, ['base.error.input'] );
        }
    }

    /**
     * 发送 Telegram 通知
     * @param int $uid 用户 UID
     * @param string $user 接收人
     * @param string|null $source 通知来源
     * @param string|null $title 通知标题
     * @param string|null $content 通知内容
     * @return JsonResponse|bool JSON 响应或布尔值
     */
    public function telegram( int $uid, string $user, ?string $source, ?string $title, ?string $content ): JsonResponse|bool {
        $api = setting( 'system.telegram.api' );
        if ( !$api ) {
            return $this->saveRecord( $uid, 'telegram', $user, $source, $title, $content, false, 'Telegram API or UID is not configured.' );
        }
        $escapedDivider = $this->escapeTelegramMarkdownV2( '-----' );
        $escapedTitle = $title ? $this->escapeTelegramMarkdownV2( $title ) : null;
        $escapedContent = $content ? $this->escapeTelegramMarkdownV2( $content ) : null;
        $escapedSource = $source ? $this->escapeTelegramMarkdownV2( $source ) : null;
        $messageParts = array_filter( [
            $escapedTitle ? "*{$escapedTitle}*\n{$escapedDivider}" : null,
            $escapedContent,
            $escapedSource ? "{$escapedDivider}\n*Source:* {$escapedSource}" : null,
        ], static fn ( mixed $value ): bool => $value !== null && $value !== '' );
        $message = implode( "\n", $messageParts );
        if ( Str::length( $message ) > 4096 ) {
            return $this->saveRecord( $uid, 'telegram', $user, $source, $title, $content, false, 'Telegram message exceeds the 4096-character limit.' );
        }
        try {
            $response = Http::acceptJson()
                ->asJson()
                ->connectTimeout( 5 )
                ->timeout( 15 )
                ->post( "https://api.telegram.org/bot{$api}/sendMessage", [
                    'chat_id' => $user,
                    'text' => $message,
                    'parse_mode' => 'MarkdownV2',
                    'link_preview_options' => ['is_disabled' => true],
                ] );
            $responseData = $response->json();
            $resultData = [
                'http_status' => $response->status(),
                'response' => is_array( $responseData ) ? $responseData : $response->body(),
            ];
            $resultStatus = $response->successful() && data_get( $responseData, 'ok' ) === true;
            return $this->saveRecord( $uid, 'telegram', $user, $source, $title, $content, $resultStatus, $resultData );
        }catch ( Throwable $exception ) {
            return $this->saveRecord( $uid, 'telegram', $user, $source, $title, $content, false, [
                'error' => 'Telegram request failed.',
                'exception' => $exception->getMessage(),
            ] );
        }
    }

    /**
     * 转义 Telegram MarkdownV2 文本。
     * @param string $value 原始文本
     * @return string 转义后的文本
     */
    public function escapeTelegramMarkdownV2( string $value ): string {
        return str_replace(
            ['\\', '_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'],
            ['\\\\', '\\_', '\\*', '\\[', '\\]', '\\(', '\\)', '\\~', '\\`', '\\>', '\\#', '\\+', '\\-', '\\=', '\\|', '\\{', '\\}', '\\.', '\\!'],
            $value,
        );
    }

    /**
     * 发送 Bark 通知
     * @param int $uid 用户 UID
     * @param string $device 接收设备码
     * @param string|null $source 通知来源
     * @param string|null $title 通知标题
     * @param string|null $content 通知内容
     * @return JsonResponse|bool JSON 响应或布尔值
     */
    public function bark( int $uid, string $device, ?string $source, ?string $title, ?string $content ): JsonResponse|bool {
        $host = setting( 'system.bark.host' );
        if ( !$host ) {
            return $this->saveRecord( $uid, 'bark', $device, $source, $title, $content, false, 'Bark host or device is not configured.' );
        }
        $postData = [
            'device_key' => $device,
            'title' => $title,
            'body' => $content
        ];
        if ( $source ) {
            $postData = array_merge( $postData, [
                'icon' => config( 'app.url' )."/assets/icons/source/{$source}.png?version=".plugin( 'PushNotifier' )->version,
                'group' => $source,
            ]);
        }
        $payload = array_filter( $postData, static fn ( mixed $value ): bool => $value !== null && $value !== '' );
        try {
            $response = Http::acceptJson()
                ->asJson()
                ->connectTimeout( 5 )
                ->timeout( 15 )
                ->post( rtrim( $host, '/' ).'/push', $payload );
            $responseData = $response->json();
            $resultData = [
                'http_status' => $response->status(),
                'response' => is_array( $responseData ) ? $responseData : $response->body(),
            ];
            $resultStatus = $response->successful() && (int) data_get( $responseData, 'code' ) === 200;
            return $this->saveRecord( $uid, 'bark', $device, $source, $title, $content, $resultStatus, $resultData );
        }catch ( Throwable $throwable ) {
            return $this->saveRecord( $uid, 'bark', $device, $source, $title, $content, false, [
                'error' => 'Bark request failed.',
                'exception' => $throwable->getMessage(),
            ] );
        }
    }

    /**
     * 发送 Email 通知
     * @param int $uid 用户 UID
     * @param string $recipient 接收人
     * @param string|null $source 通知来源
     * @param string|null $title 通知标题
     * @param string|null $content 通知内容
     * @return JsonResponse|bool JSON 响应或布尔值
     */
    public function email( int $uid, string $recipient, ?string $source, ?string $title, ?string $content ): JsonResponse|bool {
        $host = setting( 'system.mail.host' ); // SMTP 主机
        $port = setting( 'system.mail.port' ); // SMTP 端口
        $username = setting( 'system.mail.username' ); // SMTP 用户名
        $password = setting( 'system.mail.password' ); // SMTP 密码或授权码
        $scheme = setting( 'system.mail.scheme', null ); // SMTP 协议
        $scheme = $scheme === 'null' || empty( $scheme ) ? null : $scheme;
        if ( !$host || !$port || !$username || !$password ) {
            return $this->saveRecord( $uid, 'email', $recipient, $source, $title, $content, false, 'The mail server configuration is incomplete.' );
        }
        $recipient = (string) $recipient;
        $title = (string) $title;
        $senderName = (string) setting( 'app.title' );
        $mailContent = $content;
        try {
            config()->set( [
                'mail.mailers.smtp.scheme' => $scheme,
                'mail.mailers.smtp.host' => (string) $host,
                'mail.mailers.smtp.port' => (int) $port,
                'mail.mailers.smtp.username' => (string) $username,
                'mail.mailers.smtp.password' => (string) $password,
                'mail.mailers.smtp.timeout' => 15,
                'mail.mailers.smtp.auto_tls' => true,
                'mail.mailers.smtp.verify_peer' => false,
                'mail.from.address' => (string) $username,
                'mail.from.name' => $senderName,
            ] );
            Mail::purge( 'smtp' );
            Mail::mailer( 'smtp' )->raw( $mailContent, function ( Message $message ) use ( $username, $recipient, $senderName, $title ): void {
                $message
                    ->from( $username, $senderName )
                    ->to( $recipient )
                    ->subject( $title );
            } );
            return $this->saveRecord( $uid, 'email', $recipient, $source, $title, $content, true, 'Email sent successfully.' );
        }catch ( Throwable $exception ) {
            return $this->saveRecord( $uid, 'email', $recipient, $source, $title, $content, false, [
                'error' => 'Email request failed.',
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * 保存通知记录
     * @param int $uid 用户 UID
     * @param string $type 通知类型
     * @param string|null $recipient 接收人
     * @param string|null $source 通知来源
     * @param string|null $title 通知标题
     * @param string|null $content 通知内容
     * @param bool $resultStatus 通知结果状态
     * @param mixed $resultData 通知结果数据
     * @return JsonResponse|bool JSON 响应或布尔值
     */
    private function saveRecord( int $uid, string $type, ?string $recipient, ?string $source, ?string $title, ?string $content, bool $resultStatus, array|string|null $resultData ): JsonResponse|bool {
        if ( $resultStatus === true ) { $resultData = 'Push complete.'; }
        NotifierRecord::create( [
            'uid' => $uid,
            'type' => $type,
            'recipient' => $recipient,
            'source' => $source,
            'title' => $title,
            'content' => $content,
            'result_status' => $resultStatus,
            'request_data' => json_encode([
                'type' => $type,
                'source' => $source,
                'title' => $title,
                'content' => $content,
            ]),
            'result_data' => is_array( $resultData ) ? json_encode( $resultData ) : $resultData,
        ] );
        return $this->model === 'http' ? echoJson( $resultStatus, ['base.send'] ) : $resultStatus;
    }

    /**
     * 获取接收人
     * @param int $uid 用户 UID
     * @param string|null $type 通知类型
     * @return string|null 接收人
     */
    public function getRecipient( int $uid, ?string $type ): ?string {
        if ( $uid === 0 ) {
            switch ( $type ) {
                case 'telegram':
                    return plugin( 'PushNotifier' )->config( 'telegram' );
                case 'bark':
                    return plugin( 'PushNotifier' )->config( 'bark' );
                case 'email':
                    return plugin( 'PushNotifier' )->config( 'email' );
                default:
                    return null;
            }
        }
        return null;
    }
}
