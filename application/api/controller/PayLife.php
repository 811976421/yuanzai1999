<?php
namespace app\api\controller;

use think\Controller;
use think\Db;
use think\Request;

class Paylife extends Controller {

    protected $domain = 'http://open.jiaofei100.com';
    protected $appid = '20201602683693';
    protected $apiKey = '7E0A30A2E9229F79B3B578D6BF016AF5';
    protected $account;
    protected $province;
    protected $tradeType = [
        '水费查询'        => 32,
        '电费查询'        => 33,
        '燃气费查询'      => 34,
        '有线电视费查询'  => 35,
        '水费代缴'       => 14,
        '电费代缴'       => 15,
        '燃气费代缴'     => 16,
        '有线电视费代缴' => 17,
    ];
    protected $orderStatus = [
        ['code' => '10007', 'msg' => '参数错误', 'remark' => '失败处理'],
        ['code' => '10008', 'msg' => '订单超时', 'remark' => '不能直接作失败处理'],
        ['code' => '10009', 'msg' => '参数校验错误', 'remark' => '人工确认原因'],
        ['code' => '10010', 'msg' => '代理商 ID 不存在', 'remark' => '失败处理'],
        ['code' => '10011', 'msg' => '订单号长度大于 36', 'remark' => '失败处理'],
        ['code' => '10012', 'msg' => '代理商状态错误', 'remark' => '失败处理'],
        ['code' => '10013', 'msg' => '账户余额不足', 'remark' => '失败处理'],
        ['code' => '10014', 'msg' => 'IP 地址验证失败', 'remark' => '失败处理'],
        ['code' => '10015', 'msg' => '充值号码有误', 'remark' => '人工确认原因'],
        ['code' => '10016', 'msg' => '暂不支持该号码', 'remark' => '失败处理'],
        ['code' => '10017', 'msg' => '禁止采购该商品', 'remark' => '失败处理'],
        ['code' => '10018', 'msg' => '订单提交成功', 'remark' => '不能直接作失败处理'],
        ['code' => '10020', 'msg' => '订单提交失败', 'remark' => '人工确认原因'],
        ['code' => '10021', 'msg' => '未知错误', 'remark' => '	人工确认原因'],
        ['code' => '10022', 'msg' => '订单号重复', 'remark' => '不能直接作失败处理'],
        ['code' => '10024', 'msg' => '暂不支持该面值', 'remark' => '失败处理'],
        ['code' => '10025', 'msg' => '订单处理中', 'remark' => '查询订单状态时返回'],
        ['code' => '10026', 'msg' => '交易失败', 'remark' => '查询订单状态时返回'],
        ['code' => '10027', 'msg' => '交易成功', 'remark' => '查询订单状态时返回'],
        ['code' => '10029', 'msg' => '订单不存在', 'remark' => '不能直接作失败处理'],
        ['code' => '10035', 'msg' => '限制5分钟内同一时间和同一个金额', 'remark' => '失败处理'],
        ['code' => '10036', 'msg' => '系统维护', 'remark' => '失败处理'],
        ['code' => '10037', 'msg' => '活动未开始', 'remark' => '失败处理'],
        ['code' => '10038', 'msg' => '活动已经结束', 'remark' => '失败处理'],
    ];

    protected $cityCode = [
        '北京' => ['queryCode' => '50000012', 'payCode' => '20000012', 'saveCode' => '15007571'],
        '天津' => ['queryCode' => '33232996', 'payCode' => '20002550', 'saveCode' => '15231089'],
        '河北' => ['queryCode' => '33214264', 'payCode' => '15217490', 'saveCode' => '15234689'],
        '山西' => ['queryCode' => '33102113', 'payCode' => '15102640', 'saveCode' => '15233477'],
        '内蒙古' => ['queryCode' => '33205173', 'payCode' => '15207681', 'saveCode' => '15233775'],
        '辽宁' => ['queryCode' => '33224746', 'payCode' => '15225371', 'saveCode' => '15233663'],
        '吉林' => ['queryCode' => '33231888', 'payCode' => '15232657', 'saveCode' => '15236721'],
        '黑龙江' => ['queryCode' => '33249645', 'payCode' => '15243706', 'saveCode' => '15234636'],
        '上海' => ['queryCode' => '33282104', 'payCode' => '15232657', 'saveCode' => '15239107'],
        '江苏' => ['queryCode' => '33258334', 'payCode' => '15258154', 'saveCode' => '15231252'],
        '浙江' => ['queryCode' => '33262734', 'payCode' => '15267798', 'saveCode' => '15234025'],
        '安徽' => ['queryCode' => '33003120', 'payCode' => '15006197', 'saveCode' => '15231313'],
        '福建' => ['queryCode' => '33012791', 'payCode' => '15014701', 'saveCode' => '15239727'],
        '江西' => ['queryCode' => '33027420', 'payCode' => '15026389', 'saveCode' => '15233029'],
        '山东' => ['queryCode' => '33031223', 'payCode' => '15038577', 'saveCode' => '15233729'],
        '河南' => ['queryCode' => '33043209', 'payCode' => '15043881', 'saveCode' => '15235002'],
        '湖南' => ['queryCode' => '33063953', 'payCode' => '15068470', 'saveCode' => '15236900'],
        '重庆' => ['queryCode' => '33741871', 'payCode' => '15746077', 'saveCode' => '15237444'],
        '四川' => ['queryCode' => '33118657', 'payCode' => '15117792', 'saveCode' => '15232502'],
        '西藏' => ['queryCode' => '33141133', 'payCode' => '15147969', 'saveCode' => '15238376'],
        '陕西' => ['queryCode' => '33156347', 'payCode' => '15151699', 'saveCode' => '15237762'],
        '甘肃' => ['queryCode' => '33168990', 'payCode' => '15168115', 'saveCode' => '15232193'],
        '青海' => ['queryCode' => '33175775', 'payCode' => '15171219', 'saveCode' => '15235250'],
        '宁夏' => ['queryCode' => '50002463', 'payCode' => '15186336', 'saveCode' => '15234923'],
        '新疆' => ['queryCode' => '33199035', 'payCode' => '15196108', 'saveCode' => '15234623'],
        '湖北' => ['queryCode' => '33054696', 'payCode' => '15055597', 'saveCode' => '15232598'],
    ];



    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->account = input('account');
        $this->province = input('province');
    }

    public function index() {
        return $this->fetch();
    }

    public function choose() {
        $this->assign('citys', $this->cityCode);
        return $this->fetch();
    }

    public function pay() {
        $this->assign('province', $_GET['province']);
        return $this->fetch();
    }

    public function query() {
        //申请订单
        $url = $this->domain . '/Api/PayLife.aspx';
        $param = [
            'APIID' => $this->appid,
            'TradeType' => 33, //电费查询
            'Account' => $this->account,
            'GoodsID' => $this->cityCode[$this->province]['queryCode'],
            'Yearmonth' => 'all',
            'CreateTime' => time(),
            'ServiceType' => 'CreateBillOrder',
        ];
        $param['Sign'] = strtoupper(Md5('Account=' .$param['Account'].'&APIID=' . $param['APIID'] . '&CreateTime=' . $param['CreateTime'] . '&GoodsID=' . $param['GoodsID'] . '&ServiceType=' . $param['ServiceType'] . '&TradeType=' . $param['TradeType'] . '&Yearmonth=' . $param['Yearmonth'] . '&APIKEY=' . $this->apiKey));
        $res = doCurl($url, $param,1);
        $result = json_decode($res, true);
        if (isset($result['Code']) && $result['Code'] == 'success') {
            Db::connect(Config('db_connect'))->name('paylife_orders')->insert([
                'order_id' => $result['OrderID'],
                'type' => '1',
                'account' => $this->account,
            ]);
            sleep(5);
            //查询电费详情
            $res = doCurl($url, [
                'APIID' => $this->appid,
                'OrderID' => $result['OrderID'],
                'ServiceType' => 'SelectBillOrder',
                'Sign' => strtoupper(Md5('APIID='.$this->appid.'&OrderID='.$result['OrderID'].'&APIKEY='.$this->apiKey))
            ], 1);
            $result = json_decode($res, true);
            $this->assign('orderId', $result['OrderID']);
            $this->assign('info', (isset($result['RetuInfo']) && is_array($result['RetuInfo'])) ? $result['RetuInfo'][0] : null);
            return $this->fetch();
        }
    }

    public function order() {
        $res = Db::connect(Config('db_connect'))->name('paylife_orders')->where([
            'order_id' => input('orderId'),
            'pay_status' => '0',
        ])->update(['money' => input('money'), 'payWay' => input('payWay')]);
        if (input('payWay') == '1') {
            if (!$res) {
                return errReturn(400, '订单号异常,请刷新页面重新发起支付');
            }
            //微信支付
            $param = [
                'body'         => '电费缴费' . input('money') . '元',
                'attach'       => $this->account . '|' . $this->cityCode[$this->province]['payCode'] . '|' . input('money'),
                'out_trade_no' => input('orderId'),
                'total_fee'    => input('money') * 100,
                'scene_info'   => '{"h5_info": {"type":"Wap","wap_url": "http://cps.yuanzai1999.com","wap_name": "电费充值"}}',
                'notify_url'   => Request::instance()->domain() . '/api/paylife/wxnotify',
                'redirect'     => Request::instance()->domain() . '/api/paylife/search?orderId=' . input('orderId'),
            ];
            $wxpay = new Wxpay($param);
            $res = $wxpay->unifiedorder();
            if ($res) {
                return sucReturn(200,'',$res);
            } else {
                return errReturn(400,'支付失败');
            }
        } elseif (input('payWay') == '2') {
            if (!$res) {
                $this->error('订单号异常,请刷新页面重新发起支付');
            }
            //支付宝
            $param = [
                'outTradeNo' => input('orderId'),
                'realMoney'  => input('money'),
                'subject'    => '电费缴费' . input('money') . '元',
                'body'       => $this->account . '|' . $this->cityCode[$this->province]['payCode'] . '|' . input('money') ,
                'return_url' => Request::instance()->domain() . '/api/paylife/search?orderId=' . input('orderId'),
                'notify_url' => Request::instance()->domain() . '/api/paylife/alinotify',
            ];
            $alipay = new Alipay($param);
            $res = $alipay->doPay();
            echo $res;
        }

    }

    public function search() {
        $orderInfo = Db::connect(Config('db_connect'))
            ->name('paylife_orders')
            ->where('order_id', $_GET['orderId'])
            ->find();

        $this->assign('info', $orderInfo);
        return $this->fetch();
    }

    public function wxNotify() {
        $xml = file_get_contents('php://input');//监听是否有数据传入
        if(!empty($xml)){
            //微信返回信息
            $data = xml_to_data($xml);
            $sign = $data['sign'];
            unset($data['sign']);
            if ($sign == $this->MakeSign($data)) {
                if($data['result_code'] == 'SUCCESS'){
                    $attach = explode("|",$data['attach']);
                    $this->payHandler($data['out_trade_no'], $attach, $data['total_fee'] * 10);
                }
                echo 'success';
            }
        }
    }

    public function aliNotify() {
        import('.alipay.aop.AopClient', '', '.php');
        import('.alipay.aop.request.AlipayTradeAppPayRequest', '', '.php');
        $order_id = $_POST['out_trade_no'];
        $attach = explode("|",$_POST['body']);
        $aop = new \AopClient();
        $aop->alipayrsaPublicKey = Config('alipayrsaPublicKey');//支付宝公钥
        $flag = $aop->rsaCheckV1($_POST, NULL, "RSA2");
        //验证通过
        if($flag == true) {
            $this->payHandler($order_id, $attach, $_POST['total_amount'] * 1000);
            echo "success";	//支付宝
        } else {
            echo "fail";
        }
    }

    protected function payHandler($order_id, $attach, $amount) {
        $res = Db::connect(Config('db_connect'))
            ->name('paylife_orders')
            ->where(['order_id' => $order_id, 'pay_status' => '0', 'money' => $attach[2]])
            ->setField('pay_status', '1');
        if ($res) {
            //业务逻辑
            $url = $this->domain . '/Api/PayLife.aspx';
            //缴纳电费
            $param = [
                'APIID' => $this->appid,
                'TradeType' => 15, //电费代缴
                'Account' => $attach[0],
                'GoodsID' => $attach[1],
                'Yearmonth' => 'all',
                'TotalPrice' => $amount,
                'OrderID' => $order_id,
                'CreateTime' => time(),
                'isCallBack' => 0,
                'ServiceType' => 'PayLifeOrder',
            ];
            $param['sign'] = strtoupper(Md5('APIID='.$param['APIID'].'&Account='.$param['Account'].'&CreateTime='.
                $param['CreateTime'].'&GoodsID='.$param['GoodsID'].'&isCallBac='. $param['isCallBack'].
                '&OrderID='.$param['OrderID'].'&ServiceType='.$param['ServiceType'].'&TotalPrice='.
                $param['TotalPrice'].'&TradeType='.$param['TradeType'].'&Yearmonth='.
                $param['Yearmonth'].'&APIKEY='.$this->apiKey));

            $res = doCurl($url, $param, 1);
            $result = json_decode($res, true);
            if (isset($result['Code']) && $result['Code'] == '10018') {
                Db::connect(Config('db_connect'))
                    ->name('paylife_orders')
                    ->where(['order_id' => $param['OrderID'], 'lufu_status' => '0'])->setField('lufu_status', '1');
            } else {
                myLog($param['OrderID'] . '提交璐付失败');
            }
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

}