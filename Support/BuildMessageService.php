<?php

namespace App\Plugins\PushNotifier\Support;

class BuildMessageService {
    /**
     * 构建消息内容。
     * @param string|null $source 消息来源
     * @param string|null $title 消息标题
     * @param string|null $content 消息内容
     * @return array<string, string|null> 构建后的消息数组
     */
    public static function build( ?string $source, ?string $title, ?string $content ): array {
        return [
            'source' => $source,
            'title' => $title,
            'content' => $content,
        ];
    }
}