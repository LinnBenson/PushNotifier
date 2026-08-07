# 推送通知器

> 多渠道过滤式推送记录系统。

### 配置说明
- entrance: 插件入口地址
- token: 插件访问令牌
- default: 默认推送方式 telegram|bark|email
- telegram: Telegram 默认收件人
- bark: Bark 默认收件人
- email: E-mail 默认收件人

### 可调用方法
- 消息发送工具
  - `plugin('PushNotifier')->send( string $title, string $content )`
  - `->type( ?string $type )` 设置推送类型，默认为配置中的 default 值
  - `->source( ?string $source )` 设置消息来源，默认为 null
  - `->async( ?string $recipient )` 执行异步发送操作
  - `->request( ?string $recipient )` 执行发送操作
    - $recipient: string|null 收件人，默认为配置中的默认收件人
    - returns: bool 执行完成返回 true，否则返回 false