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
        $title = trim( (string) $title );
        $content = trim( (string) $content );
        switch ( $source ) {
            case 'baota':
                return self::buildBaota( $source, $title, $content );
            default:
                return [
                    'source' => $source,
                    'title' => $title,
                    'content' => $content,
                ];
        }
    }

    /**
     * 构建宝塔面板消息内容。
     * @param string|null $source 消息来源
     * @param string|null $title 消息标题
     * @param string|null $content 消息内容
     * @return array<string, string|null> 构建后的消息数组
     */
    private static function buildBaota( ?string $source, ?string $title, ?string $content ): array {
        $originalTitle = trim( (string) $title );
        $content = strip_tags( (string) $content );
        $content = preg_replace( [
            '/^\s{0,3}#{1,6}\s*/m',
            '/^\s{0,3}>\s?/m',
            '/^\s{0,3}(?:[-*_]\s*){3,}$/m',
            '/!\[([^\]]*)\]\([^)]*\)/',
            '/\[([^\]]+)\]\([^)]*\)/',
            '/^\s{0,3}[-+*]\s+/m',
        ], ['', '', '', '$1', '$1', ''], $content );
        $content = preg_replace( '/^\s*'.preg_quote( $originalTitle, '/' ).'\s*(?:\R|$)/', '', $content, 1 );
        $content = trim( str_replace( ['**', '__', '~~', '`'], '', $content ) );
        return [
            'source' => $source,
            'title' => $title,
            'content' => $content,
        ];
    }
}
