@php
    $tableInstalled = \Illuminate\Support\Facades\Schema::hasTable( 'notifier_records' );
    $pushUrl = config( 'app.url' ).$plugin->config( 'entrance' );
@endphp

{{-- 通知记录表管理模块 --}}
<div
    class="push-notifier-table"
    x-data="{
        installed: @js( $tableInstalled ),
        running: false,
        message: '',
        messageStatus: '',
        copied: false,
        copyFallback( value ) {
            const input = document.createElement( 'textarea' );
            input.value = value;
            input.readOnly = true;
            input.style.position = 'fixed';
            input.style.opacity = '0';
            document.body.appendChild( input );
            input.select();
            const copied = document.execCommand( 'copy' );
            input.remove();
            return copied;
        },
        async copyLink() {
            const value = this.$refs.pushLink.value;
            let copied = false;
            if ( window.isSecureContext && window.navigator.clipboard ) {
                try {
                    await window.navigator.clipboard.writeText( value );
                    copied = true;
                }catch ( error ) {}
            }
            if ( !copied ) { copied = this.copyFallback( value ); }
            this.copied = copied;
            window.setTimeout( () => this.copied = false, 1800 );
        },
        async execute() {
            if ( this.running ) { return; }
            const operation = this.installed ? 'uninstall-table' : 'install-table';
            if ( this.installed && !confirm( '确定卸载通知记录表吗？\n\n表内已有通知记录将被永久删除，此操作无法撤销。' ) ) { return; }
            this.running = true;
            this.message = '';
            this.messageStatus = '';
            try {
                const response = await fetch( `{{ url( config( 'app.admin_path' ).'/plugins/push-notifier' ) }}/${operation}`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.$refs.token.value,
                    },
                } );
                const data = await response.json();
                const succeeded = response.ok && data.status;
                this.messageStatus = succeeded ? 'success' : 'error';
                this.message = data.data?.message || data.message || '操作已完成。';
                if ( succeeded ) { this.installed = !this.installed; }
            }catch ( error ) {
                this.messageStatus = 'error';
                this.message = '请求失败，请稍后重试。';
            }finally {
                this.running = false;
            }
        },
    }"
>
    <input x-ref="token" type="hidden" value="{{ csrf_token() }}">

    {{-- 推送入口链接 --}}
    <section class="push-notifier-link-card">
        <div class="push-notifier-link-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13.5 10.5 21 3m0 0h-6m6 0v6M10.5 6H6.75A3.75 3.75 0 0 0 3 9.75v7.5A3.75 3.75 0 0 0 6.75 21h7.5A3.75 3.75 0 0 0 18 17.25V13.5" />
            </svg>
        </div>
        <div class="push-notifier-link-content">
            <span>接口入口</span>
            <h2>推送链接</h2>
            <p>调用此地址并传入 Token、通知类型、标题和内容即可发起推送。</p>
            <div class="push-notifier-link-value">
                <input x-ref="pushLink" type="text" value="{{ $pushUrl }}" readonly aria-label="推送链接">
                <button class="is-primary" type="button" x-on:click="copyLink()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15.75 17.25v2.625A1.125 1.125 0 0 1 14.625 21h-10.5A1.125 1.125 0 0 1 3 19.875v-10.5A1.125 1.125 0 0 1 4.125 8.25H6.75m9-5.25h4.125A1.125 1.125 0 0 1 21 4.125v10.5a1.125 1.125 0 0 1-1.125 1.125h-10.5a1.125 1.125 0 0 1-1.125-1.125v-10.5A1.125 1.125 0 0 1 9.375 3h6.375Z" />
                    </svg>
                    <span x-text="copied ? '已复制' : '复制链接'">复制链接</span>
                </button>
            </div>
        </div>
    </section>

    <section class="push-notifier-table-card">
        <div class="push-notifier-table-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4.5 6.75C4.5 5.507 7.858 4.5 12 4.5s7.5 1.007 7.5 2.25S16.142 9 12 9 4.5 7.993 4.5 6.75Zm0 0v5.625c0 1.243 3.358 2.25 7.5 2.25s7.5-1.007 7.5-2.25V6.75m-15 5.625V18c0 1.243 3.358 2.25 7.5 2.25s7.5-1.007 7.5-2.25v-5.625" />
            </svg>
        </div>
        <div class="push-notifier-table-content">
            <div class="push-notifier-table-heading">
                <div>
                    <span>数据存储</span>
                    <h2>通知记录表</h2>
                </div>
                <span class="push-notifier-table-status" x-bind:data-installed="installed">
                    <i></i>
                    <span x-text="installed ? '已安装' : '未安装'">{{ $tableInstalled ? '已安装' : '未安装' }}</span>
                </span>
            </div>
            <p>用于保存通知类型、来源、内容以及推送结果等记录。</p>
            <button
                type="button"
                x-on:click="execute()"
                x-bind:class="installed ? 'is-danger' : 'is-primary'"
                x-bind:disabled="running"
            >
                <span x-show="running" class="push-notifier-table-spinner"></span>
                <span x-text="running ? '处理中…' : ( installed ? '卸载数据表' : '安装数据表' )">{{ $tableInstalled ? '卸载数据表' : '安装数据表' }}</span>
            </button>
        </div>
    </section>
    <div
        class="push-notifier-table-message"
        x-cloak
        x-show="message !== ''"
        x-bind:data-status="messageStatus"
        x-text="message"
    ></div>
</div>

<style>
    /* 推送通知器数据表管理模块 */
    .push-notifier-table { display: flex; flex-direction: column; gap: 1rem; color: var(--gray-700); }
    .dark .push-notifier-table { color: var(--gray-300); }
    .push-notifier-link-card,
    .push-notifier-table-card {
        display: flex;
        padding: 1.25rem;
        border: 1px solid var(--gray-200);
        border-radius: 1rem;
        background: white;
        gap: 1rem;
    }
    .dark .push-notifier-link-card,
    .dark .push-notifier-table-card { border-color: var(--gray-700); background: var(--gray-900); }
    .push-notifier-link-icon,
    .push-notifier-table-icon {
        display: flex;
        width: 3rem;
        height: 3rem;
        flex: 0 0 auto;
        border-radius: 0.8rem;
        background: color-mix(in srgb, var(--primary-500) 14%, transparent);
        color: var(--primary-600);
        align-items: center;
        justify-content: center;
    }
    .push-notifier-link-icon { background: color-mix(in srgb, var(--success-500) 14%, transparent); color: var(--success-600); }
    .push-notifier-link-icon svg,
    .push-notifier-table-icon svg { width: 1.6rem; height: 1.6rem; }
    .push-notifier-link-content,
    .push-notifier-table-content { min-width: 0; flex: 1; }
    .push-notifier-table-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
    .push-notifier-table-heading > div > span {
        color: var(--primary-600);
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.08em;
    }
    .push-notifier-link-content > span {
        color: var(--success-600);
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.08em;
    }
    .push-notifier-table h2 { margin-top: 0.15rem; color: var(--gray-950); font-size: 1.15rem; font-weight: 700; }
    .dark .push-notifier-table h2 { color: white; }
    .push-notifier-link-content > p,
    .push-notifier-table-content > p { margin: 0.55rem 0 1rem; color: var(--gray-600); font-size: 0.85rem; }
    .dark .push-notifier-link-content > p,
    .dark .push-notifier-table-content > p { color: var(--gray-400); }
    .push-notifier-link-value { display: flex; min-width: 0; align-items: stretch; gap: 0.65rem; }
    .push-notifier-link-value input {
        min-width: 0;
        min-height: 2.35rem;
        flex: 1;
        padding: 0.55rem 0.75rem;
        border: 1px solid var(--gray-300);
        border-radius: 0.6rem;
        background: var(--gray-50);
        color: var(--gray-800);
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        font-size: 0.78rem;
    }
    .dark .push-notifier-link-value input { border-color: var(--gray-700); background: var(--gray-950); color: var(--gray-200); }
    .push-notifier-table-status {
        display: inline-flex;
        padding: 0.3rem 0.6rem;
        border-radius: 9999px;
        background: var(--gray-100);
        color: var(--gray-600);
        align-items: center;
        gap: 0.4rem;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .dark .push-notifier-table-status { background: var(--gray-800); color: var(--gray-300); }
    .push-notifier-table-status i { width: 0.45rem; height: 0.45rem; border-radius: 50%; background: var(--gray-400); }
    .push-notifier-table-status[data-installed="true"] { background: color-mix(in srgb, var(--success-500) 12%, transparent); color: var(--success-700); }
    .push-notifier-table-status[data-installed="true"] i { background: var(--success-500); }
    .push-notifier-table button {
        display: inline-flex;
        min-height: 2.35rem;
        padding: 0.55rem 0.9rem;
        border: 0;
        border-radius: 0.6rem;
        color: white;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        font-size: 0.82rem;
        font-weight: 650;
        cursor: pointer;
    }
    .push-notifier-table button.is-primary { background: var(--primary-600); }
    .push-notifier-table button.is-danger { background: var(--danger-600); }
    .push-notifier-link-value button svg { width: 1rem; height: 1rem; }
    .push-notifier-table button:disabled { cursor: wait; opacity: 0.65; }
    .push-notifier-table-spinner { width: 0.9rem; height: 0.9rem; border: 2px solid rgba(255, 255, 255, 0.4); border-top-color: white; border-radius: 50%; animation: push-notifier-spin 0.7s linear infinite; }
    .push-notifier-table-message { margin-top: 0.75rem; padding: 0.7rem 0.85rem; border-radius: 0.6rem; font-size: 0.82rem; }
    .push-notifier-table-message[data-status="success"] { background: color-mix(in srgb, var(--success-500) 12%, transparent); color: var(--success-700); }
    .push-notifier-table-message[data-status="error"] { background: color-mix(in srgb, var(--danger-500) 12%, transparent); color: var(--danger-700); }
    @keyframes push-notifier-spin { to { transform: rotate(360deg); } }
    @media (max-width: 640px) {
        .push-notifier-link-card,
        .push-notifier-table-card { padding: 1rem; }
        .push-notifier-link-icon,
        .push-notifier-table-icon { display: none; }
        .push-notifier-link-value { flex-direction: column; }
        .push-notifier-link-value button,
        .push-notifier-table button { width: 100%; }
    }
</style>
