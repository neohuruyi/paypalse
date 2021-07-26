<?php
namespace Paypalse;

use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Core\ProductionEnvironment;
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;
use PayPalCheckoutSdk\Orders\OrdersCaptureRequest;
use PayPalCheckoutSdk\Payments\CapturesRefundRequest;
use PayPalCheckoutSdk\Orders\OrdersGetRequest;

/**
 *paypal 支付
 */
class PaypalNew {

    /**
     *paypal 的配置参数说明
     */
    public function __construct(){
        $clientId = env('paypal_new.client_id');
        $clientSecret = env('paypal_new.secret');
        $sandbox = env('paypal_new.sandbox');
        if ($sandbox==1){
            $environment = new SandboxEnvironment($clientId, $clientSecret); //测试用
        }else{
            $environment = new ProductionEnvironment($clientId, $clientSecret); //生产用
        }
        $this->client = new PayPalHttpClient($environment);
    }

    //创建订单

    /**
     * @param $oder_sn 自定义订单号
     * @param $value 交易金额
     * @param string $currency 币种
     */
    public function order($oder_sn,$value,$currency='USD',$cancel_url,$return_url){

        $client = $this->client;
        $request = new OrdersCreateRequest();
        $request->prefer('return=representation');
        $request->body = [
            "intent" => "CAPTURE",
            "purchase_units" => [[
                "reference_id" => $oder_sn,
                "amount" => [
                    "value" => $value,
                    "currency_code" => $currency
                ]
            ]],

            "application_context" => [
                "cancel_url" => $cancel_url,
                "return_url" => $return_url, //支付成功后的同步地址
            ]
        ];

        try {
            // Call API with your client and get a response for your call
            $response = $client->execute($request);

            if (isset($response->statusCode) && $response->statusCode == '201'){
                $response = json_decode(json_encode($response),true);
                $res = [
                    'statusCode'=>$response['statusCode'],
                    'paypay_order_sn'=>$response['result']['id'],
                    'order_info'=>$response['result'],
                    'msg'=>'success',
                    'error'=>'',
                ];
            }else{
                $res = [
                    'statusCode'=>4000,
                    'paypay_order_sn'=>'',
                    'order_info'=>[],
                    'msg'=>'created  order fail',
                    'error'=>'',
                ];
            }
            return $res;//成功或者失败 做对应处理
            // If call returns body in response, you can get the deserialized version from the result attribute of the response
            //print_r($response);
        }catch (\Exception $ex) {
            $res = [
                'statusCode'=>500,
                'paypay_order_sn'=>'',
                'order_info'=>[],
                'msg'=>'created  order exception',
                'error'=>json_decode($ex->getMessage()),
            ];
            return $res; //成功或者失败 做对应处理

        }

    }

    //订单扣款
    /**
     * @param $oder_sn paypal 产生的订单号 demo 6AL24040FU581420C
     */
    public function captureOrder($oder_sn){
        $client = $this->client;
        $request = new OrdersCaptureRequest($oder_sn);
        $request->prefer('return=representation');
        try {
            // Call API with your client and get a response for your call
            $response = $client->execute($request);
            if (isset($response->statusCode) && $response->statusCode == '201'){
                $response = json_decode(json_encode($response),true);
                $res = [
                    'statusCode'=>$response['statusCode'],
                    'paypay_order_sn'=>$oder_sn,
                    'order_info'=>$response['result'],
                    'msg'=>'success',
                    'error'=>'',
                ];
            }else{
                $res = [
                    'statusCode'=>4000,
                    'paypay_order_sn'=>$oder_sn,
                    'order_info'=>[],
                    'msg'=>'capture  order fail',
                    'error'=>'',
                ];
            }
            return $res;
            // If call returns body in response, you can get the deserialized version from the result attribute of the response
        }catch (\Exception $ex) {
            $res = [
                'statusCode'=>500,
                'paypay_order_sn'=>'',
                'order_info'=>[],
                'msg'=>'capture  order exception',
                'error'=>json_decode($ex->getMessage()),
            ];
            return $res; //成功或者失败 做对应处理
        }
    }

    /**
     * @param $oder_sn
     * 查看订单详情
     */
    public function checkOrder($oder_sn){
        $client = $this->client;
        $request = new OrdersGetRequest($oder_sn);

        try {
            // Call API with your client and get a response for your call
            $response = $client->execute($request);
            // If call returns body in response, you can get the deserialized version from the result attribute of the response
            if (isset($response->statusCode) && $response->statusCode == '201'){
                $response = json_decode(json_encode($response),true);
                $res = [
                    'statusCode'=>$response['statusCode'],
                    'paypay_order_sn'=>$oder_sn,
                    'order_info'=>$response['result'],
                    'msg'=>'success',
                    'error'=>'',
                ];
            }else{
                $res = [
                    'statusCode'=>4000,
                    'paypay_order_sn'=>$oder_sn,
                    'order_info'=>[],
                    'msg'=>'check  order fail',
                    'error'=>'',
                ];
            }
            return $res;
        }catch (\Exception $ex) {
            $res = [
                'statusCode'=>500,
                'paypay_order_sn'=>'',
                'order_info'=>[],
                'msg'=>'check  order exception',
                'error'=>json_decode($ex->getMessage()),
            ];
            return $res;
        }
    }

    /**
     * @param $raw_post_data 异步返回的数据
     * @return bool
     */
    public function checkSign(){
        $raw_post_data = file_get_contents('php://input');
        $raw_post_array = explode('&', $raw_post_data);
        $myPost = array();
        foreach ($raw_post_array as $keyval) {
            $keyval = explode('=', $keyval);

            if (count($keyval) == 2) {
                // Since we do not want the plus in the datetime string to be encoded to a space, we manually encode it.
                if ($keyval[0] === 'payment_date') {
                    if (substr_count($keyval[1], '+') === 1) {
                        $keyval[1] = str_replace('+', '%2B', $keyval[1]);
                    }
                }
                $myPost[$keyval[0]] = urldecode($keyval[1]);
            }
        }
        $req = 'cmd=_notify-validate';
        $get_magic_quotes_exists = false;
        if (function_exists('get_magic_quotes_gpc')) {
            $get_magic_quotes_exists = true;
        }
        foreach ($myPost as $key => $value) {
            if ($get_magic_quotes_exists == true && get_magic_quotes_gpc() == 1) {
                $value = urlencode(stripslashes($value));
            } else {
                $value = urlencode($value);
            }
            $req .= "&$key=$value";
        }

        //https://ipnpb.sandbox.paypal.com/cgi-bin/webscr (for Sandbox IPNs) 测试
        //https://ipnpb.paypal.com/cgi-bin/webscr (for live IPNs) 生产

//        $sandbox = env('paypal_new.sandbox');
//        if ($sandbox==1){
//            $url = 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr';
//        }else{
//            $url = 'https://ipnpb.paypal.com/cgi-bin/webscr';
//        }

        $url = 'https://ipnpb.paypal.com/cgi-bin/webscr';

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
        //        curl_setopt($ch, CURLOPT_SSLVERSION, 6);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);    // https请求 不验证证书和hosts
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);

        // curl_setopt($ch, CURLOPT_CAINFO,  __ROOT__.'/Public/cert/cacert.pem');

        curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: Close'));
        $res = curl_exec($ch);
        curl_close($ch);

        // Check if paypal verfifes the IPN data, and if so, return true.
        if ($res == "VERIFIED") {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 退款
     * @param string $captureId 退款订单号是扣款完返回的id 不是之前创建订单的id
     * @param float $value 退款金额 最小值0.01 最大值不能超过订单金额
     * @param bool $debug
     */
    public function refund($captureId,$value){

        $request = new CapturesRefundRequest($captureId);
        $request->body = [
            'amount' =>
                [
                    'value' => $value,
                    'currency_code' => 'USD'
                ]
        ];

        $request->prefer('return=representation');
        $client = $this->client;

        try {
            // Call API with your client and get a response for your call
            $response = $client->execute($request);
            if (isset($response->statusCode) && $response->statusCode == '201'){
                $response = json_decode(json_encode($response),true);
                $res = [
                    'statusCode'=>$response['statusCode'],
                    'refund_order_sn'=>$captureId,
                    'order_info'=>$response['result'],
                    'msg'=>'refund success',
                    'error'=>'',
                ];
                //
            }else{
                $res = [
                    'statusCode'=>4000,
                    'refund_order_sn'=>$captureId,
                    'order_info'=>[],
                    'msg'=>'refund  order fail',
                    'error'=>'',
                ];
            }
            return $res;
            // If call returns body in response, you can get the deserialized version from the result attribute of the response
        }catch (\Exception $ex) {
            $res = [
                'statusCode'=>4000,
                'paypay_order_sn'=>'',
                'order_info'=>[],
                'msg'=>'refund  order exception',
                'error'=>json_decode($ex->getMessage()),
            ];
            return $res; //成功或者失败 做对应处理
        }
    }


}

?>