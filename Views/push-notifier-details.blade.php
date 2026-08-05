@php
    $formatData = static function ( mixed $value ): string {
        if ( $value === null || $value === '' ) { return '--'; }
        if ( is_string( $value ) ) {
            $decoded = json_decode( $value, true );
            if ( json_last_error() === JSON_ERROR_NONE ) { $value = $decoded; }
        }
        if ( !is_array( $value ) ) { return (string) $value; }
        $json = json_encode(
            $value,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE,
        );
        return is_string( $json ) ? $json : '--';
    };
@endphp

{{-- 推送通知记录详情 --}}
<div class="push-notifier-details">
    <dl class="push-notifier-details-summary">
        <div><dt>UID</dt><dd>{{ $record->uid }}</dd></div>
        <div><dt>通知类型</dt><dd>{{ $record->type_label }}</dd></div>
        <div><dt>通知来源</dt><dd>{{ $record->source ?: '--' }}</dd></div>
        <div>
            <dt>通知结果</dt>
            <dd class="{{ $record->result_status ? 'is-success' : 'is-failure' }}">
                {{ $record->result_status ? '发送成功' : '发送失败' }}
            </dd>
        </div>
    </dl>
    <section>
        <h3>接收人</h3>
        <p>{{ $record->recipient ?: '--' }}</p>
    </section>
    <section>
        <h3>通知标题</h3>
        <p>{{ $record->title ?: '--' }}</p>
    </section>
    <section>
        <h3>通知内容</h3>
        <pre>{{ $record->content ?: '--' }}</pre>
    </section>
    <div class="push-notifier-details-data">
        <section>
            <h3>请求数据</h3>
            <pre>{{ $formatData( $record->request_data ) }}</pre>
        </section>
        <section>
            <h3>结果数据</h3>
            <pre>{{ $formatData( $record->result_data ) }}</pre>
        </section>
    </div>
    <p class="push-notifier-details-time">
        创建于 {{ $record->created_at?->format( 'Y.m.d H:i:s' ) ?? '--' }}
        · 更新于 {{ $record->updated_at?->format( 'Y.m.d H:i:s' ) ?? '--' }}
    </p>
</div>

<style>
    /* 推送通知记录详情弹窗 */
    .push-notifier-details-modal {
        max-height: calc(100dvh - 2rem);
        overflow: hidden;
    }
    .push-notifier-details-modal .fi-modal-content {
        flex: 1 1 auto;
        min-width: 0;
        min-height: 0;
        overflow-x: hidden;
        overflow-y: auto;
        overscroll-behavior: contain;
        -webkit-overflow-scrolling: touch;
    }
    .push-notifier-details { display: flex; min-width: 0; flex-direction: column; gap: 1rem; }
    .push-notifier-details-summary { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0.75rem; }
    .push-notifier-details-summary > div,
    .push-notifier-details section {
        min-width: 0;
        padding: 1rem;
        border: 1px solid var(--gray-200);
        border-radius: 0.75rem;
        background: var(--gray-50);
    }
    .dark .push-notifier-details-summary > div,
    .dark .push-notifier-details section { border-color: var(--gray-700); background: var(--gray-900); }
    .push-notifier-details dt,
    .push-notifier-details h3 { color: var(--gray-500); font-size: 0.75rem; font-weight: 600; }
    .push-notifier-details dd,
    .push-notifier-details p { margin-top: 0.35rem; color: var(--gray-950); overflow-wrap: anywhere; }
    .dark .push-notifier-details dd,
    .dark .push-notifier-details p { color: white; }
    .push-notifier-details dd.is-success { color: var(--success-600); }
    .push-notifier-details dd.is-failure { color: var(--danger-600); }
    .push-notifier-details pre {
        margin-top: 0.6rem;
        color: var(--gray-800);
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        font-size: 0.78rem;
        line-height: 1.6;
        white-space: pre-wrap;
        overflow-wrap: anywhere;
    }
    .dark .push-notifier-details pre { color: var(--gray-200); }
    .push-notifier-details-data { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
    .push-notifier-details-time { margin: 0; color: var(--gray-500) !important; font-size: 0.75rem; }
    @media (max-width: 640px) {
        .push-notifier-details-modal { max-height: calc(100dvh - 1rem); }
        .push-notifier-details-modal .fi-modal-header { padding-inline: 1rem; padding-top: 1rem; }
        .push-notifier-details-modal .fi-modal-content,
        .push-notifier-details-modal .fi-modal-footer { padding-inline: 1rem; }
        .push-notifier-details-summary,
        .push-notifier-details-data { grid-template-columns: 1fr; }
    }
</style>
