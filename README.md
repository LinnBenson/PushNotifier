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
- `plugin('PushNotifier')->send( $title, $content, $source = null, $type = null )`
  - 用于通过代码发送通知，参数说明：
    - $title: string 消息标题
    - $content: string 消息内容
    - $source: string|null 消息来源（可选）
    - $type: string|null 通知类型（可选，默认使用配置中的 default 值）