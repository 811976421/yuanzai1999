<?php
namespace app\api\controller;

use think\Controller;
use think\Db;
use think\Request;

class Wxpay extends Controller {

    protected $appid;
    protected $mch_id;
    protected $key;
    protected $params;
    protected $redirect;

    public function __construct($param, $config = '', Request $request = null)
    {
        parent::__construct($request);
        $mchConf = is_array($config) ? $config : Config('mch_conf');
        $this->appid = isset($mchConf['appid']) ? $mchConf['appid'] : '';
        $this->mch_id = isset($mchConf['mch_id']) ? $mchConf['mch_id'] : '';
        $this->key = isset($mchConf['key']) ? $mchConf['key'] : '';
        if (isset($param['redirect'])) {
            $this->redirect = $param['redirect'];
            unset($param['redirect']);
        }
        $this->params = $param;
    }

    public function unifiedorder() {
        $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        $this->params['trade_type'] = 'MWEB';    //交易类型，h5支付，默认如此
        $this->params['spbill_create_ip'] = request()->ip();   //终端IP
        $this->params['appid'] = $this->appid;
        $this->params['mch_id'] = $this->mch_id;
        $this->params['nonce_str'] = $this->genRandomString();    //随机字符串
        //获取签名数据
        $this->params['sign'] = $this->MakeSign( $this->params );   //签名
        $xml = $this->data_to_xml($this->params);
        $response = $this->postXmlCurl($url,$xml);   //自定义封装的xml请求格式，文章最下面为参考postxml
        if( !$response ){
            return false;
        }
        $result = $this->xml_to_data( $response );
        if( !empty($result['result_code']) && !empty($result['err_code']) ){
            $result['err_msg'] = $this->error_code( $result['err_code'] );
        }

        if($result['result_code'] == 'SUCCESS' && $result['return_code'] == 'SUCCESS'){
            //发起微信支付url
            $pay_url = $result['mweb_url'].'&redirect_url='.urlencode($this->redirect);
            //返回发起支付url，微信外浏览器访问
            return $pay_url;
        }
    }

    public function MakeSign( $params ){
        //签名步骤一：按字典序排序数组参数
        ksort($params);
        $stringA = $this->ToUrlParams($params);
        //签名步骤二：在string后加入KEY
        $stringA = $stringA . "&key=".$this->key;
        //签名步骤三：MD5加密
        $stringA = md5($stringA);
        //签名步骤四：所有字符转为大写
        $result = strtoupper($stringA);
        return $result;
    }
    /**
     * 将参数拼接为url: key=value&key=value
     * @param   $params
     * @return  string
     */
    public function ToUrlParams( $params ){
        $string = '';
        if( !empty($params) ){
            $array = array();
            foreach( $params as $key => $value ){
                $array[] = $key.'='.$value;
            }
            $string = implode("&",$array);
        }
        return $string;
    }
    /**
     * 输出xml字符
     * @param   $params     参数名称
     * return   string      返回组装的xml
     **/
    public function data_to_xml( $params ){
        if(!is_array($params)|| count($params) <= 0)
        {
            return false;
        }
        $xml = "<xml>";
        foreach ($params as $key=>$val)
        {
            if (is_numeric($val)){
                $xml.="<".$key.">".$val."</".$key.">";
            }else{
                $xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
            }
        }
        $xml.="</xml>";
        return $xml;
    }
    /**
     * 将xml转为array
     * @param string $xml
     * return array
     */
    public function xml_to_data($xml){
        if(!$xml){
            return false;
        }
        //将XML转为array
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $data;
    }

    public function genRandomString($len = 32) {
        $chars = array(
            "a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k",
            "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v",
            "w", "x", "y", "z", "A", "B", "C", "D", "E", "F", "G",
            "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R",
            "S", "T", "U", "V", "W", "X", "Y", "Z", "0", "1", "2",
            "3", "4", "5", "6", "7", "8", "9"
        );
        $charsLen = count($chars) - 1;
        // 将数组打乱
        shuffle($chars);
        $output = "";
        for ($i = 0; $i < $len; $i++) {
            $output .= $chars[mt_rand(0, $charsLen)];
        }
        return $output;
    }

    public function postXmlCurl($url,$xml,$second = 30){
        $ch = curl_init();
        //设置超时
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,FALSE);
        //设置 header
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        //post 提交方式
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        //运行 curl
        $data = curl_exec($ch);
        //返回结果
        if($data){
            curl_close($ch);
            return $data;
        }else{
            $error = curl_errno($ch);
            curl_close($ch);
            echo "curl 出错，错误码:$error"."<br>";
        }
    }

    public function error_code( $code ){
        $errList = array(
            'NOAUTH'                =>  '商户未开通此接口权限',
            'NOTENOUGH'             =>  '用户帐号余额不足',
            'ORDERNOTEXIST'         =>  '订单号不存在',
            'ORDERPAID'             =>  '商户订单已支付，无需重复操作',
            'ORDERCLOSED'           =>  '当前订单已关闭，无法支付',
            'SYSTEMERROR'           =>  '系统错误!系统超时',
            'APPID_NOT_EXIST'       =>  '参数中缺少APPID',
            'MCHID_NOT_EXIST'       =>  '参数中缺少MCHID',
            'APPID_MCHID_NOT_MATCH' =>  'appid和mch_id不匹配',
            'LACK_PARAMS'           =>  '缺少必要的请求参数',
            'OUT_TRADE_NO_USED'     =>  '同一笔交易不能多次提交',
            'SIGNERROR'             =>  '参数签名结果不正确',
            'XML_FORMAT_ERROR'      =>  'XML格式错误',
            'REQUIRE_POST_METHOD'   =>  '未使用post传递参数 ',
            'POST_DATA_EMPTY'       =>  'post数据不能为空',
            'NOT_UTF8'              =>  '未使用指定编码格式',
        );
        if( array_key_exists( $code , $errList ) ){
            return $errList[$code];
        }
    }



}
