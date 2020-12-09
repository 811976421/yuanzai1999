<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------

// 应用公共文件
function create_order_no() {
    $order_no = date('Ymd').substr(time(), -5) . substr(microtime(), 2, 5) . sprintf('%02d', rand(1000, 9999));
    return $order_no;
}

function sucReturn($code, $message, $data = [], $json = 0) {

    $data = [
        'code' => $code,
        'message' => $message,
        'data' => $data
    ];

    if ($json) {
        return json_encode($data);
    }

    return $data;

}

function errReturn($code, $message, $json = 0) {

    $data = [
        'code' => $code,
        'message' => $message
    ];

    if ($json) {
        return json_encode($data);
    }

    return $data;
}

function doCurl($url, $data = [], $method = 0) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT , 120);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    if ($method == 1) {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        myLog('回调请求CURL异常code:'.curl_errno($ch));
    }
    curl_close($ch);
    return $result;
}

function checkAll($data, $rule) {
    $validate = new \think\Validate($rule);
    $result = $validate->check($data);
    if (!$result) {
        return $validate->getError();
    }
    return true;
}

function getChilds($lists,$pid){
    $list = [];
    $i=0;
    foreach ($lists as $value) {
        if ($value['pid'] == $pid) {
            $list[$i]=$value;
            $children = getChilds($lists,$value['id']);
            if ($children) {
                $list[$i]['children'] = $children;
            }
        }
        $i++;
    }
    if($list){
        $list = array_values($list);
        return $list;
    }else{
        return [];
    }

}

function initcode() {
    // 密码字符集，可任意添加你需要的字符
    $chars = array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h',
        'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's',
        't', 'u', 'v', 'w', 'x', 'y', 'z', 'A', 'B', 'C', 'D',
        'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O',
        'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z',
        '0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
    // 在 $chars 中随机取 $length 个数组元素的键名
    $keys = array_rand($chars, 6);
    $password = '';
    for ($i = 0; $i < 6; $i++) {
        // 获取随机抽出的值，在将 $length 个数组元素连接成字符串
        $password .= $chars[$keys[$i]];
    }
    return $password;
}

function tradeCertificate() {
    $nums = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    $chars = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h',
        'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's',
        't', 'u', 'v', 'w', 'x', 'y', 'z', 'A', 'B', 'C', 'D',
        'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O',
        'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];
    $stringA = '';
    $keys = array_rand($nums, 2);
    for ($i = 0; $i < 2; $i++) {
        // 获取随机抽出的值，在将 $length 个数组元素连接成字符串
        $stringA .= $nums[$keys[$i]];
    }
    $keys = array_rand($chars, 4);
    for ($i = 0; $i < 4; $i++) {
        // 获取随机抽出的值，在将 $length 个数组元素连接成字符串
        $stringA .= $chars[$keys[$i]];
    }
    return $stringA;
}

function ismobile()
{
    // 如果有HTTP_X_WAP_PROFILE则一定是移动设备
    if (isset ($_SERVER['HTTP_X_WAP_PROFILE']))
        return true;

    //此条摘自TPM智能切换模板引擎，适合TPM开发
    if (isset ($_SERVER['HTTP_CLIENT']) && 'PhoneClient' == $_SERVER['HTTP_CLIENT'])
        return true;
    //如果via信息含有wap则一定是移动设备,部分服务商会屏蔽该信息
    if (isset ($_SERVER['HTTP_VIA']))
        //找不到为flase,否则为true
        return stristr($_SERVER['HTTP_VIA'], 'wap') ? true : false;
    //判断手机发送的客户端标志,兼容性有待提高
    if (isset ($_SERVER['HTTP_USER_AGENT'])) {
        $clientkeywords = array(
            'nokia', 'sony', 'ericsson', 'mot', 'samsung', 'htc', 'sgh', 'lg', 'sharp', 'sie-', 'philips', 'panasonic', 'alcatel', 'lenovo', 'iphone', 'ipod', 'blackberry', 'meizu', 'android', 'netfront', 'symbian', 'ucweb', 'windowsce', 'palm', 'operamini', 'operamobi', 'openwave', 'nexusone', 'cldc', 'midp', 'wap', 'mobile'
        );
        //从HTTP_USER_AGENT中查找手机浏览器的关键字
        if (preg_match("/(" . implode('|', $clientkeywords) . ")/i", strtolower($_SERVER['HTTP_USER_AGENT']))) {
            return true;
        }
    }
    //协议法，因为有可能不准确，放到最后判断
    if (isset ($_SERVER['HTTP_ACCEPT'])) {
        // 如果只支持wml并且不支持html那一定是移动设备
        // 如果支持wml和html但是wml在html之前则是移动设备
        if ((strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') !== false) && (strpos($_SERVER['HTTP_ACCEPT'], 'text/html') === false || (strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') < strpos($_SERVER['HTTP_ACCEPT'], 'text/html')))) {
            return true;
        }
    }
    return false;
}

function getSign($arr, $key) {
    ksort($arr);
    $stringA = '';
    foreach ($arr as $k => $v) {
        $stringA .= $k . '=' . $v . '&';
    }

    $stringA .= $key;
    $stringA = MD5($stringA);
    $stringA = strtoupper($stringA);
    return $stringA;
}

function sendEmail($subject,$content) {
    $mail = new \PHPMailer\PHPMailer();
    $mail->isSMTP();// 使用SMTP服务
    $mail->CharSet = "utf8";// 编码格式为utf8，不设置编码的话，中文会出现乱码
    $mail->Host = "smtp.163.com";// 发送方的SMTP服务器地址
    $mail->SMTPAuth = true;// 是否使用身份验证
    $mail->Username = "easy_pay@163.com";/// 发送方的163邮箱用户名，就是你申请163的SMTP服务使用的163邮箱
    $mail->Password = "OLPLAPJWEYWLECUJ";// 发送方的邮箱密码，注意用163邮箱这里填写的是“客户端授权密码”而不是邮箱的登录密码！
    $mail->SMTPSecure = "ssl";// 使用ssl协议方式
    $mail->Port = 465;// 163邮箱的ssl协议方式端口号是465/994

    $mail->setFrom("easy_pay@163.com","admin");// 设置发件人信息，如邮件格式说明中的发件人，这里会显示为Mailer(xxxx@163.com），Mailer是当做名字显示

    $contactInfo = \think\Db::name('contact')->where('status', '1')->find();

    if (!$contactInfo) {
        myLog('无可用值班人');
        die();
    }

    $name = $contactInfo['name'];

    $email = $contactInfo['email'];

    $mail->addAddress($email, $name);// 设置收件人信息，如邮件格式说明中的收件人，这里会显示为Liang(yyyy@163.com)

    $mail->Subject = $subject;// 邮件标题

    $mail->Body = $content;

    if(!$mail->send()){// 发送邮件
        myLog('发送邮件异常:' . $mail->ErrorInfo);
    }
}

function convertUrlQuery($query)
{
    $queryParts = explode('&', $query);

    $params = array();
    foreach ($queryParts as $param)
    {
        $item = explode('=', $param);
        $params[$item[0]] = $item[1];
    }

    return $params;
}

function get_position($ip){
    if(empty($ip)){

        $ip = GetIp();

    }

    $res = @file_get_contents('http://int.dpool.sina.com.cn/iplookup/iplookup.php?format=js&ip=' . $ip);

    if(empty($res)){ return false; }

    $jsonMatches = array();

    preg_match('#\{.+?\}#', $res, $jsonMatches);

    if(!isset($jsonMatches[0])){ return false; }

    $json = json_decode($jsonMatches[0], true);

    if(isset($json['ret']) && $json['ret'] == 1){

        $json['ip'] = $ip;

        unset($json['ret']);

    }else{

        return false;

    }

    return $json;
}

function myLog($content){
    $filename = RUNTIME_PATH . 'log/'.date("Y-m-d").'_myLog.log';
    $Ts = fopen($filename,"a+");
    fputs($Ts,"执行日期：" . date('Y-m-d H:i:s',time()) .  ' ' . "\n" .$content."\n");
    fclose($Ts);
}

function getMillisecond() {
    list($t1, $t2) = explode(' ', microtime());
    return (float)sprintf('%.0f',(floatval($t1)+floatval($t2))*1000);
}

function num_to_rmb($num){
    $c1 = "零壹贰叁肆伍陆柒捌玖";
    $c2 = "分角元拾佰仟万拾佰仟亿";
    //精确到分后面就不要了，所以只留两个小数位
    $num = round($num, 2);
    //将数字转化为整数
    $num = $num * 100;
    if (strlen($num) > 10) {
        return "金额太大，请检查";
    }
    $i = 0;
    $c = "";
    while (1) {
        if ($i == 0) {
            //获取最后一位数字
            $n = substr($num, strlen($num)-1, 1);
        } else {
            $n = $num % 10;
        }
        //每次将最后一位数字转化为中文
        $p1 = substr($c1, 3 * $n, 3);
        $p2 = substr($c2, 3 * $i, 3);
        if ($n != '0' || ($n == '0' && ($p2 == '亿' || $p2 == '万' || $p2 == '元'))) {
            $c = $p1 . $p2 . $c;
        } else {
            $c = $p1 . $c;
        }
        $i = $i + 1;
        //去掉数字最后一位了
        $num = $num / 10;
        $num = (int)$num;
        //结束循环
        if ($num == 0) {
            break;
        }
    }
    $j = 0;
    $slen = strlen($c);
    while ($j < $slen) {
        //utf8一个汉字相当3个字符
        $m = substr($c, $j, 6);
        //处理数字中很多0的情况,每次循环去掉一个汉字“零”
        if ($m == '零元' || $m == '零万' || $m == '零亿' || $m == '零零') {
            $left = substr($c, 0, $j);
            $right = substr($c, $j + 3);
            $c = $left . $right;
            $j = $j-3;
            $slen = $slen-3;
        }
        $j = $j + 3;
    }
    //这个是为了去掉类似23.0中最后一个“零”字
    if (substr($c, strlen($c)-3, 3) == '零') {
        $c = substr($c, 0, strlen($c)-3);
    }
    //将处理的汉字加上“整”
    if (empty($c)) {
        return "零元整";
    }else{
        return $c . "整";
    }
}

function object_array($array) {  
    if(is_object($array)) {  
        $array = (array)$array;  
    } 
    if(is_array($array)) {
        foreach($array as $key=>$value) {  
            $array[$key] = object_array($value);  
        }  
    }  
    return $array;  
}

function get_time($targetTime)
{
    // 今天最大时间
    $todayLast   = strtotime(date('Y-m-d 23:59:59'));
    $agoTimeTrue = time() - $targetTime;
    $agoTime     = $todayLast - $targetTime;
    $agoDay      = floor($agoTime / 86400);

    if ($agoTimeTrue < 60) {
        $result = '刚刚';
    } elseif ($agoTimeTrue < 3600) {
        $result = (ceil($agoTimeTrue / 60)) . '分钟前';
    } elseif ($agoTimeTrue < 3600 * 12) {
        $result = (ceil($agoTimeTrue / 3600)) . '小时前';
    } elseif ($agoDay == 0) {
        $result = '今天 ' . date('H:i', $targetTime);
    } elseif ($agoDay == 1) {
        $result = '昨天 ' . date('H:i', $targetTime);
    } elseif ($agoDay == 2) {
        $result = '前天 ' . date('H:i', $targetTime);
    } elseif ($agoDay > 2 && $agoDay < 16) {
        $result = $agoDay . '天前 ' . date('H:i', $targetTime);
    } else {
        $format = date('Y') != date('Y', $targetTime) ? "Y-m-d H:i" : "m-d H:i";
        $result = date($format, $targetTime);
    }
    return $result;
}

