<?php

namespace App\Plugins\PushNotifier\Controllers;

use App\Http\Controllers\Controller;
use App\Plugins\PushNotifier\Models\NotifierRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * AdminController
 * 推送通知器数据表管理控制器。
 * @package App\Plugins\PushNotifier\Controllers
 */
class AdminController extends Controller {
    /**
     * 安装通知记录表。
     * @return JsonResponse JSON 响应
     */
    public function install(): JsonResponse {
        try {
            if ( Schema::hasTable( 'notifier_records' ) ) {
                return echoJson( true, ['message' => '通知记录表已安装。'] );
            }
            NotifierRecord::up();
            return echoJson( true, ['message' => '通知记录表安装成功。'] );
        }catch ( Throwable $throwable ) {
            report( $throwable );
            return echoJson( false, ['message' => '通知记录表安装失败，请查看系统日志。'], 500 );
        }
    }

    /**
     * 卸载通知记录表。
     * @return JsonResponse JSON 响应
     */
    public function uninstall(): JsonResponse {
        try {
            if ( !Schema::hasTable( 'notifier_records' ) ) {
                return echoJson( true, ['message' => '通知记录表尚未安装。'] );
            }
            NotifierRecord::down();
            return echoJson( true, ['message' => '通知记录表卸载成功。'] );
        }catch ( Throwable $throwable ) {
            report( $throwable );
            return echoJson( false, ['message' => '通知记录表卸载失败，请查看系统日志。'], 500 );
        }
    }
}
