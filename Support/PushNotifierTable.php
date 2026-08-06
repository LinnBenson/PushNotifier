<?php

namespace App\Plugins\PushNotifier\Support;

use App\Filament\Concerns\HasNavigationLevel;
use App\Plugins\PushNotifier\Models\NotifierRecord;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use UnitEnum;
use Illuminate\Support\Str;
use Throwable;

/**
 * PushNotifierTable
 * 推送通知记录管理页面。
 * @package App\Plugins\PushNotifier\Support
 */
class PushNotifierTable extends Page implements HasTable {
    use HasNavigationLevel {
        canAccess as protected canAccessByNavigationLevel;
    }
    use InteractsWithTable;

    protected static string $navigationPermission = 'push_notifier_records';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static ?string $slug = 'push-notifier-records';

    protected static ?int $navigationSort = 10;

    protected string $view = 'PushNotifier::push-notifier-table';

    /**
     * 获取头部操作。
     * 提供新建推送和重新加载通知记录操作。
     * @return array<int, Action> 头部操作按钮
     */
    protected function getHeaderActions(): array {
        return [
            Action::make( 'createNotification' )
                ->label( '新建推送' )
                ->icon( Heroicon::OutlinedPaperAirplane )
                ->modalIcon( Heroicon::OutlinedPaperAirplane )
                ->modalHeading( '新建推送' )
                ->modalDescription( '填写通知内容并选择推送渠道。接收人留空时使用该渠道的默认配置。' )
                ->schema( [
                    Select::make( 'type' )
                        ->label( '推送类型' )
                        ->options( NotifierRecord::TYPES )
                        ->default( fn (): ?string => plugin( 'PushNotifier' )->config( 'default' ) )
                        ->native( false )
                        ->required(),
                    TextInput::make( 'recipient' )
                        ->label( '接收人' )
                        ->placeholder( '留空则使用该渠道的默认接收人' )
                        ->maxLength( 255 ),
                    TextInput::make( 'title' )
                        ->label( '标题' )
                        ->maxLength( 255 )
                        ->required(),
                    Textarea::make( 'content' )
                        ->label( '内容' )
                        ->rows( 8 )
                        ->maxLength( 10000 )
                        ->required()
                        ->columnSpanFull(),
                    TextInput::make( 'source' )
                        ->label( '来源' )
                        ->placeholder( '可留空，例如：system' )
                        ->maxLength( 255 ),
                ] )
                ->modalWidth( '2xl' )
                ->modalSubmitActionLabel( '确认推送' )
                ->modalCancelActionLabel( '取消' )
                ->action( fn ( array $data ): mixed => $this->sendNewNotification( $data ) ),
            Action::make( 'refresh' )
                ->label( '刷新' )
                ->icon( Heroicon::OutlinedArrowPath )
                ->action( fn (): null => null ),
        ];
    }

    /**
     * 发送新通知
     * 根据表单内容发送通知，队列可用时优先异步推送。
     * @param array<string, mixed> $data 通知表单数据
     * @return void
     */
    public function sendNewNotification( array $data ): void {
        $plugin = plugin( 'PushNotifier' );
        if ( !$plugin ) {
            Notification::make()->title( '推送通知器不可用' )->danger()->send();
            return;
        }
        $type = (string) ( $data['type'] ?? '' );
        $recipient = trim( (string) ( $data['recipient'] ?? '' ) );
        if ( $recipient === '' ) {
            $recipient = trim( (string) $plugin->config( $type ) );
        }
        if ( $recipient === '' ) {
            Notification::make()->title( '接收人未填写，且该渠道没有默认接收人' )->warning()->send();
            return;
        }
        try {
            $source = trim( (string) ( $data['source'] ?? '' ) );
            $result = $plugin
                ->send( (string) ( $data['title'] ?? '' ), (string) ( $data['content'] ?? '' ) )
                ->type( $type )
                ->source( $source === '' ? null : $source )
                ->request( $recipient );
            if ( !$result ) {
                Notification::make()->title( '通知推送失败' )->danger()->send();
                return;
            }
            Notification::make()
                ->title( '通知推送成功' )
                ->success()
                ->send();
        }catch ( Throwable $throwable ) {
            report( $throwable );
            Notification::make()->title( '通知推送失败' )->danger()->send();
        }
    }

    /**
     * 判断是否允许访问通知记录页面。
     * 数据表不存在时隐藏菜单并阻止页面查询。
     * @return bool 是否允许访问
     */
    public static function canAccess(): bool {
        return Schema::hasTable( 'notifier_records' ) && static::canAccessByNavigationLevel();
    }

    /**
     * 配置通知记录表格。
     * @param Table $table 表格实例
     * @return Table 表格实例
     */
    public function table( Table $table ): Table {
        return $table
            ->query( NotifierRecord::query() )
            ->defaultSort( 'id', 'desc' )
            ->columns( [
                TextColumn::make( 'id' )
                    ->label( 'ID' )
                    ->sortable(),
                TextColumn::make( 'uid' )
                    ->label( 'UID' )
                    ->searchable()
                    ->sortable(),
                TextColumn::make( 'type' )
                    ->label( '通知类型' )
                    ->formatStateUsing( fn ( string $state ): string => NotifierRecord::TYPES[$state] ?? $state )
                    ->badge()
                    ->sortable(),
                TextColumn::make( 'recipient' )
                    ->label( '接收人' )
                    ->placeholder( '--' )
                    ->searchable()
                    ->toggleable( isToggledHiddenByDefault: true ),
                TextColumn::make( 'source' )
                    ->label( '通知来源' )
                    ->placeholder( '--' )
                    ->searchable()
                    ->sortable(),
                TextColumn::make( 'title' )
                    ->label( '通知标题' )
                    ->placeholder( '--' )
                    ->searchable()
                    ->limit( 20 )
                    ->tooltip( fn ( NotifierRecord $record ): ?string => $record->title )
                    ->action( $this->viewRecordAction( 'viewTitle' ) ),
                TextColumn::make( 'content' )
                    ->label( '通知内容' )
                    ->placeholder( '--' )
                    ->searchable()
                    ->limit( 30 )
                    ->wrap()
                    ->toggleable()
                    ->tooltip( function ( NotifierRecord $record ): ?string {
                        return Str::limit( (string) $record->content, 300 );
                    })
                    ->action( $this->viewRecordAction( 'viewContent' ) ),
                IconColumn::make( 'result_status' )
                    ->label( '通知结果' )
                    ->boolean()
                    ->sortable(),
                TextColumn::make( 'created_at' )
                    ->label( '创建时间' )
                    ->dateTime( 'Y.m.d H:i:s' )
                    ->sortable(),
                TextColumn::make( 'updated_at' )
                    ->label( '更新时间' )
                    ->dateTime( 'Y.m.d H:i:s' )
                    ->sortable()
                    ->toggleable( isToggledHiddenByDefault: true ),
            ] )
            ->filters( [
                SelectFilter::make( 'type' )
                    ->label( '通知类型' )
                    ->options( NotifierRecord::TYPES )
                    ->native( false ),
                TernaryFilter::make( 'result_status' )
                    ->label( '通知结果' )
                    ->trueLabel( '发送成功' )
                    ->falseLabel( '发送失败' )
                    ->placeholder( '全部结果' ),
                SelectFilter::make( 'source' )
                    ->label( '通知来源' )
                    ->options( fn (): array => NotifierRecord::query()
                        ->whereNotNull( 'source' )
                        ->where( 'source', '!=', '' )
                        ->distinct()
                        ->orderBy( 'source' )
                        ->pluck( 'source', 'source' )
                        ->all() )
                    ->searchable()
                    ->native( false ),
                Filter::make( 'created_at' )
                    ->label( '创建时间' )
                    ->schema( [
                        DatePicker::make( 'created_from' )
                            ->label( '开始日期' )
                            ->native( false ),
                        DatePicker::make( 'created_until' )
                            ->label( '结束日期' )
                            ->native( false ),
                    ] )
                    ->columns( 2 )
                    ->query( function ( Builder $query, array $data ): Builder {
                        return $query
                            ->when(
                                $data['created_from'] ?? null,
                                fn ( Builder $query, string $date ): Builder => $query->whereDate( 'created_at', '>=', $date ),
                            )
                            ->when(
                                $data['created_until'] ?? null,
                                fn ( Builder $query, string $date ): Builder => $query->whereDate( 'created_at', '<=', $date ),
                            );
                    } ),
            ] )
            ->recordActions( [
                $this->viewRecordAction( 'view' ),
                DeleteAction::make()
                    ->label( '删除' ),
            ] )
            ->recordActionsColumnLabel( '操作' )
            ->toolbarActions( [
                BulkActionGroup::make( [
                    DeleteBulkAction::make()
                        ->label( '删除所选' ),
                ] ),
            ] )
            ->defaultPaginationPageOption( 25 )
            ->paginationPageOptions( [25, 50, 100] )
            ->emptyStateHeading( '暂无通知记录' )
            ->emptyStateDescription( '通过推送通知器发送消息后，通知结果将显示在这里。' );
    }

    /**
     * 创建通知记录查看操作。
     * 供标题、内容列和操作栏复用同一个详情弹窗配置。
     * @param string $name 操作名称
     * @return Action 查看操作
     */
    private function viewRecordAction( string $name ): Action {
        return Action::make( $name )
            ->label( '查看' )
            ->icon( Heroicon::OutlinedEye )
            ->color( 'gray' )
            ->modalHeading( fn ( NotifierRecord $record ): string => $record->title ?? '查看通知详情' )
            ->modalContent( fn ( NotifierRecord $record ) => view( 'PushNotifier::push-notifier-details', [
                'record' => $record,
            ] ) )
            ->modalWidth( '4xl' )
            ->extraModalWindowAttributes( ['class' => 'push-notifier-details-modal'] )
            ->modalSubmitAction( false )
            ->modalCancelActionLabel( '关闭' );
    }

    /**
     * 重新推送通知
     * 使用原通知记录的渠道、接收人和消息内容再次发送通知。
     * @param int $recordId 通知记录 ID
     * @return void
     */
    public function resendNotification( int $recordId ): void {
        $record = NotifierRecord::query()->find( $recordId );
        if ( !$record ) {
            Notification::make()->title( '通知记录不存在' )->danger()->send();
            return;
        }
        try {
            $result = plugin( 'PushNotifier' )
                ->send( (string) $record->title, (string) $record->content )
                ->type( $record->type )
                ->source( $record->source )
                ->request( $record->recipient );
            if ( !$result ) {
                Notification::make()->title( '重新推送失败' )->danger()->send();
                return;
            }
            Notification::make()
                ->title( '通知重新推送成功' )
                ->success()
                ->send();
        }catch ( Throwable $throwable ) {
            report( $throwable );
            Notification::make()->title( '重新推送失败' )->danger()->send();
        }
    }

    /**
     * 页面信息
     */
    public function getBreadcrumbs(): array { return [__( 'filament.groups.other' ), '通知记录']; }
    public static function getNavigationLabel(): string { return '通知记录'; }
    public function getTitle(): string { return '推送通知记录'; }
    public static function getNavigationGroup(): string|UnitEnum|null { return __( 'filament.groups.other' ); }
}
