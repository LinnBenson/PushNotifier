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
<article class="push-notifier-details">
    {{-- 标题与发送状态 --}}
    <header class="push-notifier-details-header">
        <div class="push-notifier-details-tags">
            <span class="push-notifier-details-status {{ $record->result_status ? 'is-success' : 'is-failure' }}">
                <span></span>{{ $record->result_status ? '发送成功' : '发送失败' }}
            </span>
            <span class="push-notifier-details-tag">{{ $record->type_label }}</span>
            @if ( $record->source )
                <span class="push-notifier-details-tag">{{ $record->source }}</span>
            @endif
            <button
                type="button"
                class="push-notifier-details-resend"
                wire:click="resendNotification({{ $record->getKey() }})"
                wire:loading.attr="disabled"
                wire:target="resendNotification({{ $record->getKey() }})"
            >
                <x-filament::icon
                    class="push-notifier-details-resend-icon"
                    icon="heroicon-o-arrow-path"
                    wire:loading.class="is-spinning"
                    wire:target="resendNotification({{ $record->getKey() }})"
                />
                <span wire:loading.remove wire:target="resendNotification({{ $record->getKey() }})">重新推送</span>
                <span wire:loading wire:target="resendNotification({{ $record->getKey() }})">推送中</span>
            </button>
        </div>
        <h2>{{ $record->title ?: '无标题通知' }}</h2>
    </header>

    {{-- 通知正文 --}}
    <section class="push-notifier-details-message">
        <pre>{{ $record->content ?: '--' }}</pre>
    </section>

    {{-- 次要发送信息 --}}
    <dl class="push-notifier-details-meta">
        <div>
            <dt>接收人</dt>
            <dd>{{ $record->recipient ?: '--' }}</dd>
        </div>
        <div>
            <dt>UID</dt>
            <dd>{{ $record->uid }}</dd>
        </div>
        <div>
            <dt>发送时间</dt>
            <dd>{{ $record->created_at?->format( 'Y.m.d H:i:s' ) ?? '--' }}</dd>
        </div>
    </dl>

    {{-- 可折叠的技术数据 --}}
    <div class="push-notifier-details-data">
        <details>
            <summary>
                <span>请求数据</span>
                <x-filament::icon icon="heroicon-o-chevron-down" />
            </summary>
            <pre>{{ $formatData( $record->request_data ) }}</pre>
        </details>
        <details>
            <summary>
                <span>结果数据</span>
                <x-filament::icon icon="heroicon-o-chevron-down" />
            </summary>
            <pre>{{ $formatData( $record->result_data ) }}</pre>
        </details>
    </div>
</article>

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
    .push-notifier-details {
        display: flex;
        min-width: 0;
        flex-direction: column;
        gap: 1.25rem;
    }
    /* 通知标题 */
    .push-notifier-details-header {
        display: flex;
        min-width: 0;
        flex-direction: column;
        gap: 0.75rem;
    }
    .push-notifier-details-header h2 {
        margin: 16px 0px 0px 0px;
        color: var(--gray-950);
        font-size: clamp(1.35rem, 2.5vw, 1.75rem);
        font-weight: 700;
        line-height: 1.3;
        overflow-wrap: anywhere;
    }
    .dark .push-notifier-details-header h2 { color: white; }
    .push-notifier-details-tags { display: flex; flex-wrap: wrap; align-items: center; gap: 0.4rem; }
    .push-notifier-details-status,
    .push-notifier-details-tag {
        display: inline-flex;
        min-height: 1.6rem;
        padding: 0.2rem 0.55rem;
        border-radius: 9999px;
        align-items: center;
        gap: 0.35rem;
        font-size: 0.7rem;
        font-weight: 600;
    }
    .push-notifier-details-tag { background: var(--gray-100); color: var(--gray-600); }
    .dark .push-notifier-details-tag { background: var(--gray-800); color: var(--gray-300); }
    .push-notifier-details-status span { width: 0.4rem; height: 0.4rem; border-radius: 9999px; background: currentColor; }
    .push-notifier-details-status.is-success {
        background: color-mix(in srgb, var(--success-500) 12%, transparent);
        color: var(--success-700);
    }
    .push-notifier-details-status.is-failure {
        background: color-mix(in srgb, var(--danger-500) 12%, transparent);
        color: var(--danger-700);
    }
    .push-notifier-details-resend {
        display: inline-flex;
        min-height: 2rem;
        margin-left: auto;
        padding: 0.35rem 0.75rem;
        border-radius: 0.5rem;
        background: var(--primary-600);
        box-shadow: 0 1px 2px color-mix(in srgb, var(--primary-950) 18%, transparent);
        color: var(--primary-50);
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
        cursor: pointer;
        font-size: 0.75rem;
        font-weight: 600;
        transition: background-color 150ms ease, opacity 150ms ease, transform 150ms ease;
    }
    .push-notifier-details-resend:hover { background: var(--primary-500); }
    .push-notifier-details-resend:active { transform: translateY(1px); }
    .push-notifier-details-resend:disabled { opacity: 0.65; cursor: wait; }
    .push-notifier-details-resend-icon { width: 1rem; height: 1rem; }
    .push-notifier-details-resend-icon.is-spinning { animation: push-notifier-details-spin 700ms linear infinite; }
    @keyframes push-notifier-details-spin { to { transform: rotate(360deg); } }
    /* 通知正文 */
    .push-notifier-details-message {
        min-width: 0;
        padding: 1.25rem;
        border: 1px solid var(--gray-200);
        border-radius: 0.875rem;
        background: var(--gray-50);
        box-shadow: inset 0 1px 0 color-mix(in srgb, white 70%, transparent);
    }
    .dark .push-notifier-details-message {
        border-color: var(--gray-700);
        background: var(--gray-900);
        box-shadow: none;
    }
    .push-notifier-details-message pre {
        margin: 0;
        color: var(--gray-900);
        font-family: inherit;
        font-size: 0.95rem;
        line-height: 1.75;
        white-space: pre-wrap;
        overflow-wrap: anywhere;
    }
    .dark .push-notifier-details-message pre { color: var(--gray-100); }
    /* 次要发送信息 */
    .push-notifier-details-meta {
        display: grid;
        padding: 0.875rem 1rem;
        border: 1px solid var(--gray-200);
        border-radius: 0.75rem;
        background: white;
        grid-template-columns: minmax(0, 2fr) minmax(5rem, 0.5fr) minmax(10rem, 1fr);
        gap: 1rem;
    }
    .dark .push-notifier-details-meta { border-color: var(--gray-700); background: var(--gray-900); }
    .push-notifier-details-meta > div { min-width: 0; }
    .push-notifier-details-meta dt { color: var(--gray-400); font-size: 0.68rem; font-weight: 600; }
    .push-notifier-details-meta dd {
        margin-top: 0.25rem;
        color: var(--gray-600);
        font-size: 0.78rem;
        line-height: 1.45;
        overflow-wrap: anywhere;
    }
    .dark .push-notifier-details-meta dd { color: var(--gray-300); }
    /* 可折叠的请求及结果数据 */
    .push-notifier-details-data {
        display: grid;
        overflow: hidden;
        border: 1px solid var(--gray-200);
        border-radius: 0.75rem;
        background: white;
    }
    .dark .push-notifier-details-data { border-color: var(--gray-700); background: var(--gray-900); }
    .push-notifier-details-data details + details { border-top: 1px solid var(--gray-200); }
    .dark .push-notifier-details-data details { border-color: var(--gray-700); }
    .push-notifier-details-data summary {
        display: flex;
        min-height: 2.75rem;
        padding: 0 1rem;
        color: var(--gray-500);
        align-items: center;
        justify-content: space-between;
        cursor: pointer;
        font-size: 0.75rem;
        font-weight: 600;
        list-style: none;
        user-select: none;
    }
    .push-notifier-details-data summary::-webkit-details-marker { display: none; }
    .push-notifier-details-data summary:hover { color: var(--primary-600); }
    .push-notifier-details-data summary svg {
        width: 0.95rem;
        height: 0.95rem;
        transition: transform 160ms ease;
    }
    .push-notifier-details-data details[open] summary svg { transform: rotate(180deg); }
    .push-notifier-details-data details > pre {
        max-height: 18rem;
        margin: 0 1rem 1rem;
        padding: 0.875rem;
        overflow: auto;
        border-radius: 0.625rem;
        background: var(--gray-950);
        color: var(--gray-100);
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 0.75rem;
        line-height: 1.6;
        white-space: pre-wrap;
        overflow-wrap: anywhere;
    }
    @media (max-width: 640px) {
        .push-notifier-details-modal { max-height: calc(100dvh - 1rem); }
        .push-notifier-details-modal .fi-modal-header { padding-inline: 1rem; padding-top: 1rem; }
        .push-notifier-details-modal .fi-modal-content,
        .push-notifier-details-modal .fi-modal-footer { padding-inline: 1rem; }
        .push-notifier-details { gap: 1rem; }
        .push-notifier-details-resend { width: 100%; margin-left: 0; }
        .push-notifier-details-message { padding: 1rem; }
        .push-notifier-details-meta { grid-template-columns: 1fr 1fr; }
        .push-notifier-details-meta > div:first-child { grid-column: 1 / -1; }
    }
</style>
