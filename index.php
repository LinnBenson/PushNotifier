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
        $this->version = '1.0.2';
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
        config()->set( 'filament.navigation_levels.push_notifier_records', config( 'filament.navigation_levels.plugin_management', 99899 ) );
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
};
