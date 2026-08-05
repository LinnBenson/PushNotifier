<?php

namespace App\Plugins\PushNotifier\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NotifierRecord
 * 通知内容记录表
 * @package App\Plugins\PushNotifier\Models
 */
class NotifierRecord extends Model {
    protected $table = 'notifier_records';

    /**
     * 配置类别。
     * @var array<string, string>
     */
    public const TYPES = [
        'telegram' => 'Telegram',
        'bark' => 'Bark',
        'email' => 'E-mail',
    ];

    protected $casts = [
        'result_status' => 'boolean',
    ];

    /**
     * 可批量赋值字段。
     * @var array<int, string>
     */
    protected $fillable = [
        'uid',
        'type',
        'recipient',
        'source',
        'title',
        'content',
        'result_status',
        'request_data',
        'result_data',
    ];

    /**
     * 获取类型名称。
     * 根据类型值返回对应中文名称。
     * @return string 类型名称
     */
    public function getTypeLabelAttribute(): string {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * 安装表
     * @return void
     */
    public static function up(): void {
        if ( Schema::hasTable( 'notifier_records' ) ) { return; }
        Schema::create( 'notifier_records', function ( Blueprint $table ): void {
            $table->id()->comment( '通知记录ID' );
            $table->unsignedBigInteger( 'uid' )->default( 0 )->comment( '用户UID，0表示系统通知' );
            $table->string( 'type' )->comment( '通知类型' );
            $table->string( 'recipient' )->nullable()->comment( '接收人' );
            $table->string( 'source' )->nullable()->comment( '通知来源' );
            $table->string( 'title' )->nullable()->comment( '通知标题' );
            $table->text( 'content' )->nullable()->comment( '通知内容' );
            $table->boolean( 'result_status' )->default( false )->comment( '通知结果状态：0失败，1成功' );
            $table->text( 'request_data' )->nullable()->comment( '原始请求数据' );
            $table->text( 'result_data' )->nullable()->comment( '通知结果数据' );
            $table->timestamp( 'created_at' )->nullable()->comment( '创建时间' );
            $table->timestamp( 'updated_at' )->nullable()->comment( '更新时间' );
        } );
    }

    /**
     * 卸载表
     * @return void
     */
    public static function down(): void {
        if ( !Schema::hasTable( 'notifier_records' ) ) { return; }
        Schema::drop( 'notifier_records' );
    }
}
