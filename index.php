<?php

use App\Filament\Concerns\AdminLevel;
use App\Plugins\PushNotifier\Controllers\AdminController;
use App\Plugins\PushNotifier\Controllers\PushController;
use App\Plugins\PushNotifier\Support\PushNotifierTable;
use App\Providers\PluginProvider;
use Filament\Panel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use App\Plugins\PushNotifier\Models\NotifierRecord;
use App\Plugins\PushNotifier\Support\BuildMessageService;

/**
 * Push Notifier Plugin
 * 提供多渠道通知推送与结果记录能力。
 */
return new class extends PluginProvider {
    /**
     * 插件信息
     */
    public function __construct() {
        $this->name = '推送通知器';
        $this->description = '多渠道过滤式推送记录系统。';
        $this->version = '1.2.2';
        $this->author = 'System';
        $this->source = 'https://github.com/LinnBenson/PushNotifier/archive/refs/heads/main.zip';
        $this->setType( 1 );
    }

    /**
     * 注册插件 Hook。
     * @return void
     */
    public function boot(): void {
        $this->hook( 'APP_SERVICE_PROVIDER_BOOT', 'register' );
        $this->hook( 'ADMIN_PANEL_PROVIDER_PANEL', 'registerAdminPage' );
    }

    /**
     * 注册插件管理路由。
     * @return void
     */
    public function register(): void {
        app( 'view' )->addNamespace( 'PushNotifier', "{$this->path}Views" );
        Route::any( $this->config( 'entrance' ), [PushController::class, 'index'] )->name( 'index' );
        Route::middleware( AdminLevel::class )
            ->prefix( config( 'app.admin_path' ).'/plugins/push-notifier' )
            ->name( 'plugins.push-notifier.' )
            ->group( function (): void {
                Route::post( '/install-table', [AdminController::class, 'install'] )->name( 'install-table' );
                Route::post( '/uninstall-table', [AdminController::class, 'uninstall'] )->name( 'uninstall-table' );
            } );
    }

    /**
     * 注册后台管理页面。
     * @param Panel $panel Filament 面板
     * @return void
     */
    public function registerAdminPage( Panel $panel ): void {
        config()->set( 'filament.navigation_levels.push_notifier_records', config( 'filament.navigation_levels.plugin_management', 99990 ) );
        $panel->pages( [
            PushNotifierTable::class,
        ] );
    }

    /**
     * 安装插件。
     * @return bool 安装成功返回 true，否则返回 false
     */
    public function install(): bool {
        try {
            NotifierRecord::up();
            return true;
        } catch ( Throwable $throwable ) {
            Log::error( '安装通知记录表失败：'.$throwable->getMessage() );
            return false;
        }
    }

    /**
     * 卸载插件。
     * @return bool 卸载成功返回 true，否则返回 false
     */
    public function uninstall(): bool {
        try {
            NotifierRecord::down();
            return true;
        } catch ( Throwable $throwable ) {
            Log::error( '卸载通知记录表失败：'.$throwable->getMessage() );
            return false;
        }
    }

    /**
     * 发送通知工具
     * @param string $title 通知标题
     * @param string $content 通知内容
     * @return object 返回一个可链式调用的对象，支持设置通知类型、来源和接收人，并最终调用 request() 方法发送通知。
     */
    public function send( string $title, string $content ): object {
        $defaultType = $this->config( 'default' );
        return new class( $title, $content, $defaultType ) {
            private ?string $typeValue = null;
            private string $title;
            private string $content;
            private ?string $sourceValue = null;
            public function __construct( string $title, string $content, ?string $type = null ) {
                $this->title = $title;
                $this->content = $content;
                $this->typeValue = $type;
            }
            // 设置通知类型
            public function type( ?string $type = null ): self {
                $this->typeValue = $type;
                return $this;
            }
            // 设置通知来源
            public function source( ?string $source = null ): self {
                $this->sourceValue = $source;
                return $this;
            }
            // 发送通知
            public function request( ?string $recipient = null ): bool {
                $PushController = new PushController();
                $PushController->setCli();
                $uid = 0;
                $recipient = $recipient === null ? $PushController->getRecipient( $uid, $this->typeValue ) : $recipient;
                if ( empty( $recipient ) ) { return false; }
                $data = BuildMessageService::build( $this->sourceValue, $this->title, $this->content );
                switch ( $this->typeValue ) {
                    case 'telegram':
                        $result = $PushController->telegram( $uid, $recipient, $data['source'] ?? null, $data['title'], $data['content'] );
                        break;
                    case 'bark':
                        $result = $PushController->bark( $uid, $recipient, $data['source'] ?? null, $data['title'], $data['content'] );
                        break;
                    case 'email':
                        $result = $PushController->email( $uid, $recipient, $data['source'] ?? null, $data['title'], $data['content'] );
                        break;
                    default:
                        return false;
                }
                return $result === true;
            }
        };
    }
};
