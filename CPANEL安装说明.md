# cPanel 安装说明（AI + AR Object Recognition）

## 上传什么

把项目做成 zip 后上传到 cPanel，或用文件管理器上传整个文件夹。

建议线上目录名：

```text
public_html/ai-ar/
```

访问地址示例：

```text
https://你的域名.com/ai-ar/
https://你的域名.com/ai-ar/settings.php
```

也可放到子域名根目录：

```text
public_html/   （内容直接是本项目文件）
→ https://ar.你的域名.com/
```

## 不要上传这些（本机专用）

- `tools/`（cloudflared、日志）
- `开启跨网络HTTPS.bat`
- `开启HTTPS给手机用.bat`
- `config/public-url.txt`（本机隧道地址，cPanel 不需要）

已提供打包脚本会自动排除它们。

## 步骤（File Manager）

1. 登录 cPanel → **文件管理器** → 进入 `public_html`
2. 新建文件夹 `ai-ar`（可改名）
3. 上传 `ai-ar-cpanel.zip` → 解压到 `ai-ar`
4. 确认有：`index.php`、`settings.php`、`api/`、`assets/`、`config/`、`includes/`
5. 若没有 `config/config.php`：复制 `config/config.example.php` 为 `config/config.php`
6. 权限：文件夹 `755`，文件 `644`（一般默认即可）
7. 打开：`https://你的域名.com/ai-ar/settings.php`

## PHP 要求

- PHP **8.0+**（建议 8.2 / 8.3）
- 扩展：`curl`、`json`、`mbstring`、`pdo_mysql`（历史功能才需要 MySQL）
- cPanel → **Select PHP Version / MultiPHP** 选好版本
- 若上传大图失败：MultiPHP INI Editor 设置  
  `post_max_size=16M`、`upload_max_filesize=16M`、`max_execution_time=120`

## HTTPS（很重要）

手机相机需要 HTTPS。  
cPanel 域名开好 SSL（Let’s Encrypt）后，二维码会自动变成 `https://你的域名/...`，**手机不用同一 WiFi 也能用**。

## 配置 AI

1. 打开 Settings  
2. 填入 Gemini API Key（[Google AI Studio](https://aistudio.google.com/apikey)）  
3. Save settings → Test Connection  
4. 用手机扫本页二维码 → Allow camera → SCAN  

## 可选：扫描历史 MySQL

1. cPanel → MySQL 数据库 → 新建数据库/用户并授权  
2. phpMyAdmin 导入 `database/database.sql`（可改库名）  
3. Settings 开启 DB，填写主机/库名/用户/密码（cPanel 库名通常是 `账号_库名`）

## 安全

- `config/`、`includes/`、`database/`、`storage/` 已用 `.htaccess` 禁止直接访问  
- 不要把 API Key 发到公开聊天/GitHub  
- 建议 Settings 页自己加简单登录保护（cPanel Directory Privacy）

## 测通清单

- [ ] `https://域名/ai-ar/` 能打开  
- [ ] Settings 能保存 Key  
- [ ] Test Connection 显示 Gemini Connected  
- [ ] 手机扫二维码是 https 链接  
- [ ] 允许相机后能 SCAN 出结果  
- [ ] 讲英文 / 讲华文按钮正常  

## 常见问题

| 问题 | 处理 |
|------|------|
| 500 错误 | PHP 版本过低；看 Errors 日志 |
| 无法保存设置 | `config/` 要可写（755） |
| 相机打不开 | 必须用 https |
| 识别乱/失败 | Key 未填或配额不足 |
| php_value 报错 | 删 `.htaccess` 里 php_value，改用 MultiPHP INI |
