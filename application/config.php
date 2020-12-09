<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

return [
    // +----------------------------------------------------------------------
    // | 应用设置
    // +----------------------------------------------------------------------

    // 应用调试模式
    'app_debug'              => true,
    // 应用Trace
    'app_trace'              => false,
    // 应用模式状态
    'app_status'             => '',
    // 是否支持多模块
    'app_multi_module'       => true,
    // 入口自动绑定模块
    'auto_bind_module'       => false,
    // 注册的根命名空间
    'root_namespace'         => [],
    // 扩展函数文件
    'extra_file_list'        => [THINK_PATH . 'helper' . EXT],
    // 默认输出类型
    'default_return_type'    => 'html',
    // 默认AJAX 数据返回格式,可选json xml ...
    'default_ajax_return'    => 'json',
    // 默认JSONP格式返回的处理方法
    'default_jsonp_handler'  => 'jsonpReturn',
    // 默认JSONP处理方法
    'var_jsonp_handler'      => 'callback',
    // 默认时区
    'default_timezone'       => 'PRC',
    // 是否开启多语言
    'lang_switch_on'         => false,
    // 默认全局过滤方法 用逗号分隔多个
    'default_filter'         => '',
    // 默认语言
    'default_lang'           => 'zh-cn',
    // 应用类库后缀
    'class_suffix'           => false,
    // 控制器类后缀
    'controller_suffix'      => false,

    // +----------------------------------------------------------------------
    // | 模块设置
    // +----------------------------------------------------------------------

    // 默认模块名
    'default_module'         => 'index',
    // 禁止访问模块
    'deny_module_list'       => ['common'],
    // 默认控制器名
    'default_controller'     => 'Index',
    // 默认操作名
    'default_action'         => 'index',
    // 默认验证器
    'default_validate'       => '',
    // 默认的空控制器名
    'empty_controller'       => 'Error',
    // 操作方法后缀
    'action_suffix'          => '',
    // 自动搜索控制器
    'controller_auto_search' => false,

    // +----------------------------------------------------------------------
    // | URL设置
    // +----------------------------------------------------------------------

    // PATHINFO变量名 用于兼容模式
    'var_pathinfo'           => 's',
    // 兼容PATH_INFO获取
    'pathinfo_fetch'         => ['ORIG_PATH_INFO', 'REDIRECT_PATH_INFO', 'REDIRECT_URL'],
    // pathinfo分隔符
    'pathinfo_depr'          => '/',
    // URL伪静态后缀
    'url_html_suffix'        => 'html',
    // URL普通方式参数 用于自动生成
    'url_common_param'       => false,
    // URL参数方式 0 按名称成对解析 1 按顺序解析
    'url_param_type'         => 0,
    // 是否开启路由
    'url_route_on'           => true,
    // 路由使用完整匹配
    'route_complete_match'   => false,
    // 路由配置文件（支持配置多个）
    'route_config_file'      => ['route'],
    // 是否开启路由解析缓存
    'route_check_cache'      => false,
    // 是否强制使用路由
    'url_route_must'         => false,
    // 域名部署
    'url_domain_deploy'      => false,
    // 域名根，如thinkphp.cn
    'url_domain_root'        => '',
    // 是否自动转换URL中的控制器和操作名
    'url_convert'            => true,
    // 默认的访问控制器层
    'url_controller_layer'   => 'controller',
    // 表单请求类型伪装变量
    'var_method'             => '_method',
    // 表单ajax伪装变量
    'var_ajax'               => '_ajax',
    // 表单pjax伪装变量
    'var_pjax'               => '_pjax',
    // 是否开启请求缓存 true自动缓存 支持设置请求缓存规则
    'request_cache'          => false,
    // 请求缓存有效期
    'request_cache_expire'   => null,
    // 全局请求缓存排除规则
    'request_cache_except'   => [],

    // +----------------------------------------------------------------------
    // | 模板设置
    // +----------------------------------------------------------------------

    'template'               => [
        // 模板引擎类型 支持 php think 支持扩展
        'type'         => 'Think',
        // 默认模板渲染规则 1 解析为小写+下划线 2 全部转换小写
        'auto_rule'    => 1,
        // 模板路径
        'view_path'    => '',
        // 模板后缀
        'view_suffix'  => 'html',
        // 模板文件名分隔符
        'view_depr'    => DS,
        // 模板引擎普通标签开始标记
        'tpl_begin'    => '{',
        // 模板引擎普通标签结束标记
        'tpl_end'      => '}',
        // 标签库标签开始标记
        'taglib_begin' => '{',
        // 标签库标签结束标记
        'taglib_end'   => '}',
    ],

    // 视图输出字符串内容替换
    'view_replace_str'       => [
        '__IMAGE__'     => 'https://www.yuanzai1999.com',
    ],
    // 默认跳转页面对应的模板文件
    'dispatch_success_tmpl'  => THINK_PATH . 'tpl' . DS . 'dispatch_jump.tpl',
    'dispatch_error_tmpl'    => THINK_PATH . 'tpl' . DS . 'dispatch_jump.tpl',

    // +----------------------------------------------------------------------
    // | 异常及错误设置
    // +----------------------------------------------------------------------

    // 异常页面的模板文件
    'exception_tmpl'         => THINK_PATH . 'tpl' . DS . 'think_exception.tpl',

    // 错误显示信息,非调试模式有效
    'error_message'          => '页面错误！请稍后再试～',
    // 显示错误信息
    'show_error_msg'         => false,
    // 异常处理handle类 留空使用 \think\exception\Handle
    'exception_handle'       => '',

    // +----------------------------------------------------------------------
    // | 日志设置
    // +----------------------------------------------------------------------

    'log'                    => [
        // 日志记录方式，内置 file socket 支持扩展
        'type'  => 'File',
        // 日志保存目录
        'path'  => LOG_PATH,
        // 日志记录级别
        'level' => [],
    ],

    // +----------------------------------------------------------------------
    // | Trace设置 开启 app_trace 后 有效
    // +----------------------------------------------------------------------
    'trace'                  => [
        // 内置Html Console 支持扩展
        'type' => 'Html',
    ],

    // +----------------------------------------------------------------------
    // | 缓存设置
    // +----------------------------------------------------------------------

    'cache'                  => [
        // 驱动方式
        'type'   => 'File',
        // 缓存保存目录
        'path'   => CACHE_PATH,
        // 缓存前缀
        'prefix' => '',
        // 缓存有效期 0表示永久缓存
        'expire' => 86400,
    ],

    // +----------------------------------------------------------------------
    // | 会话设置
    // +----------------------------------------------------------------------

    'session'                => [

        'id'             => '',
        // SESSION_ID的提交变量,解决flash上传跨域
        'var_session_id' => '',
        // SESSION 前缀
        'prefix'         => 'think',
        // 驱动方式 支持redis memcache memcached
        'type'           => '',
        // 是否自动开启 SESSION
        'auto_start'     => true,
        //过期时间
        'expire'         => 3600*12
    ],

    // +----------------------------------------------------------------------
    // | Cookie设置
    // +----------------------------------------------------------------------
    'cookie'                 => [
        // cookie 名称前缀
        'prefix'    => '',
        // cookie 保存时间
        'expire'    => 0,
        // cookie 保存路径
        'path'      => '/',
        // cookie 有效域名
        'domain'    => '',
        //  cookie 启用安全传输
        'secure'    => false,
        // httponly设置
        'httponly'  => '',
        // 是否使用 setcookie
        'setcookie' => true,
    ],

    //分页配置
    'paginate'               => [
        'type'      => 'bootstrap',
        'var_page'  => 'page',
        'list_rows' => 15,
    ],

    //角色配置
    'roles'                  => [
        'admin'     => 1,
        'merchant'  => 3,
    ],

    //元载微信商户号
    'mch_conf' => [
        'appid'  => 'wx603119ddfbf9b125',
        'mch_id' => '1602151525',
        'key'    => 'VWOpj1k18APzU2LsQStsCx2YREfeJULM',
    ],

    //元载支付宝
    'yz_aliConf' => [
        'appId' => '2021001191648433',
        'rsaPrivateKey' => 'MIIEvAIBADANBgkqhkiG9w0BAQEFAASCBKYwggSiAgEAAoIBAQCADhcUFpCwG9LvASrCUgMvXmzRKo/oTybfef9J5rhbNfmbZNpN0RAiG5Xf6w9PwwtSDTTFcBNZuDt8B8bvS6TRBLzphKSOK7RdvCV/MsX8/1HDWSA1BSq12oXN8huIEW7bgViOixxIjPLia4s82bM304cEf6ixt78j3qxiYdRnpbhXA/b55CLapKVDWs8/j9KSzPpsUPzVB5srcsM7eQ/TZpkMtTiOJh3+kVnRLkJltQXP21z+e+zHtTuU0EZKW/0S5yTkHKCZOtnb7FN3/JEgiGf9bf9JC2ngzfoLjbLZgR+VsJDMpwQN8sU7i/eKezwIuHAEoXmj0KEdFF0pB9VlAgMBAAECggEAef5yaBXTQ6NOQZnIBZYiKKd5XY+Bx5xYeUB9QAdIjMSQBkV/X/ESwJutHcSPMrqq90FTi+Do3mmQaeny0Jgs7V/pJULTjgn+6+nJgzN4wTzvxP4Mm9ZPSS5kmL3VEr0g8od4OVw31rpIzgIMhS0U+tNO5q9DpANcWizR52wx8YbrWYrtAksyu/WdyOnW7jNoCX6/WvVHPPT8Ur1vEXkzEOPsEl9ZDPXvC1YAKo6XZeEvwXGZu9MkVnNotaMwMf+V9qZRfIfxo4zwpmmjTRqrNTZGWH/5Gm+BCBMvIBSiru8rh/n3fcigJ/AGq9fICocYPw6ZHu4jeCnh2eyTOfZRBQKBgQC4qmrlLxAgO5oKyPbBpurEe4rKdssaL2ObRf+gOzbTXuUmcIyqEHRxUMXWBL4mV9A/saxQhytvN5ehYU+6Fk3K5akU/AYp+A1JReOwsEW3cU15Wcix+vyYKredUA+8MSR9yuQKAYMxcCtjZj+VJwc32mgc6cEMU68HzPRYFnPXRwKBgQCxhXKNr1sE/67m7fAU9c4zQYyvhxeSLrc0Ptg+9P136aRdnpm30xHe7TBhGIPlzDJinDAC86OYDnIg7nuZbhZfQQqciLKLGaZKyjiA5pTHqx2b4WPpnyiboWm2KpCpY9OXT7HU91DRu4JUynFwW1iuSKqzsVfPBFg4+EmXfrIb8wKBgHJixXIPM+rzYFi74PVVQmjQqcj9ypL+tbFRq6UB6NUHG+QypT8WkDo8sT7/kxVHIazCjp0XDVWH1vnUwEDhXfCHT7O65MqMZxZzHzWsCpM1sKqxbbqpYFuVYkUkeYq12ge9bIGyLCSseYPJqyrlkPgM5p66QKU1FN89GkGSLtEzAoGAF9Qe3U/letVLR+o2aMnZ5N1uof0TN+cXZmbtJf5Cq77r9jkczyUA6BiUfuQIiGcReFUYyLylf/qobS014BF75UtYvkxHEw7dWHqufPb2j5qzfcISZECd9c4D35T1GBezRkNHTpvn+E8gHnuSII/SZoqQh1BNxhcCNJt3XWN/SusCgYAezrTaAbHdSnOimq3bYDlUqUbf3OnKlEpzK/i2IEISRG2EYzQrhhF4Epg9pNBJBr6udf6YpgEQJSbzS6hbTCwdHNABhLIGs9cPNMCaaxR9IycWfqasOgQSyJMchuw/lY+bUV3R3OgQtazZOftQJ93E0fAtw8d3T8bN5ZIZ/guDpQ==',
        'alipayrsaPublicKey' => 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAsuznBnM3idXjVOaAUKtaAKk5TNHUThDzVBagUNbZ6A18UFrlGuR6oHigRHUGUmzGAtyowpnBooMQQcHyZ9Hp9QXRa/LeMESGFbvKVrKocwLCPWHMmEyyycIlXGTc5csBHOGMtP1ycCRHL5QV+3C2Z8TZrTxRvhtl0w8zIPn1EyF8BAhTfUlAm27+n4MM9DHZFnO6XYswErH1kynnjguAKN0+xot/TZeluorOp1EVQq6EhpIeMZ+PHmPJvHz2Ns9O0nt45POxU10Ddsz0wb7ToIr0r9i1/CNyqtHJ7TwLXtq8zoDWYmjTyRgXxkWqwmg9u8YiLrzTLb88STdi5ka2DQIDAQAB',
    ],

    //聚合支付话费充值
    'juhe_conf' => [
        'domain' => 'http://op.juhe.cn',
        'key'    => '8cdbdb0aa7a407df699410af94c11654',
        'openId' => 'JHecc1cb0c41ed97fb84ee159dca0b796d',
    ],

    'db_connect' => [
        // 数据库类型
        'type'        => 'mysql',
        // 服务器地址
        'hostname'    => '127.0.0.1',
        // 数据库名
        'database'    => 'cps.yuanzai.com',
        // 数据库用户名
        'username'    => 'cps.yuanzai.com',
        // 数据库密码
        'password'    => 'TPcEeySBBSjK5At7',
        // 数据库编码默认采用utf8
        'charset'     => 'utf8',
        // 数据库表前缀
        'prefix'      => 'center_',
        // 数据库调试模式
        'debug'       => false,
    ]

];
