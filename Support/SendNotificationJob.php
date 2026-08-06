<?php

namespace App\Plugins\PushNotifier\Support;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendNotificationJob implements ShouldQueue {
    use Queueable;

    public int $tries = 1;
    public int $timeout = 60;

    public function __construct(
        private string $title,
        private string $content,
        private ?string $type = null,
        private ?string $source = null,
        private ?string $recipient = null,
    ) {}

    /**
     * 执行异步通知发送
     * @return void
     */
    public function handle(): void {
        $request = plugin( 'PushNotifier' )->send( $this->title, $this->content );
        if ( $this->type !== null ) {
            $request->type( $this->type );
        }
        if ( $this->source !== null ) {
            $request->source( $this->source );
        }
        $result = $request->request( $this->recipient );
        if ( !$result ) {
            throw new \RuntimeException( 'Notification sending failed.' );
        }
    }
}