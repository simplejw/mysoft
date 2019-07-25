<?php

namespace pay\wechat;

use Yii;
use yii\base\Component;
use yii\base\Exception;

class WeChatPayException extends Exception
{
	public function errorMessage()
	{
		return $this->getMessage();
	}
}

class WePay extends Component
{

	public $appID; //main.php
	public $mchID; //main.php
	public $key;
	public $appSecret; //main.php
	
	public $sign_data = array();
	
	public $curl_timeout = 30;
	
	const CBHOST = '';
	const ATURL = 'https://api.weixin.qq.com/cgi-bin/token';
	const LOGINURL = 'https://open.weixin.qq.com/connect/oauth2/authorize';
	const GRAPHURL = 'https://api.weixin.qq.com/sns/oauth2/access_token';
	const USERURL = 'https://api.weixin.qq.com/cgi-bin/user/info';
	const MSGURL = 'https://api.weixin.qq.com/cgi-bin/message/custom/send';
	
	const UNIPAYURL = "https://api.mch.weixin.qq.com/pay/unifiedorder";

	const ORDERQUERY= "https://api.mch.weixin.qq.com/pay/orderquery";
	
	//=======【curl代理设置】===================================
	/**
	 * TODO：这里设置代理机器，只有需要代理的时候才设置，不需要代理，请设置为0.0.0.0和0
	 * 本例程通过curl使用HTTP POST方法，此处可修改代理服务器，
	 * 默认CURL_PROXY_HOST=0.0.0.0和CURL_PROXY_PORT=0，此时不开启代理（如有需要才设置）
	 * @var unknown_type
	 */
	const CURL_PROXY_HOST = "0.0.0.0";//"10.152.18.220";
	const CURL_PROXY_PORT = 0;//8080;
	
	const SSLCERT_PATH = '';
	const SSLKEY_PATH = '';
	
	public function init()
	{
	
	}

	/**
	 *
	 * 统一下单，WxPayUnifiedOrder中out_trade_no、body、total_fee、trade_type必填
	 * appid、mchid、spbill_create_ip、nonce_str不需要填入
	 * @param WxPayUnifiedOrder $inputObj
	 * @param int $timeOut
	 * @throws WxPayException
	 * @return 成功时返回，其他抛异常
	 */
	public function unifiedOrder($inputObj, $timeOut = 6)
	{
		
		//检测必填参数
		if(!array_key_exists('out_trade_no', $inputObj)) {
			throw new WeChatPayException("缺少统一支付接口必填参数out_trade_no！");
		}else if(!array_key_exists('body', $inputObj)){
			throw new WeChatPayException("缺少统一支付接口必填参数body！");
		}else if(!array_key_exists('total_fee', $inputObj)) {
			throw new WeChatPayException("缺少统一支付接口必填参数total_fee！");
		}else if(!array_key_exists('trade_type', $inputObj)) {
			throw new WeChatPayException("缺少统一支付接口必填参数trade_type！");
		}
	
		//关联参数
		if($inputObj['trade_type'] == "JSAPI" && !array_key_exists('openid', $inputObj)){
			throw new WeChatPayException("统一支付接口中，缺少必填参数openid！trade_type为JSAPI时，openid为必填参数！");
		}
		if($inputObj['trade_type'] == "NATIVE" && !array_key_exists('product_id', $inputObj)){
			throw new WeChatPayException("统一支付接口中，缺少必填参数product_id！trade_type为JSAPI时，product_id为必填参数！");
		}
	
		//异步通知url未设置，则使用配置文件中的url
		if(!array_key_exists('notify_url', $inputObj)){
			throw new WeChatPayException("缺少统一支付接口必填参数异步通知url！");//异步通知url
		}
	
		$inputObj['appid'] = $this->appID;//公众账号ID
		$inputObj['mch_id'] = $this->mchID;//商户号
		$inputObj['spbill_create_ip'] = $_SERVER['REMOTE_ADDR'];//终端ip
		//$inputObj->SetSpbill_create_ip("1.1.1.1");
		$inputObj['nonce_str'] = $this->getNonceStr();//随机字符串
		
		$this->sign_data = $inputObj;
		//签名
		$inputObj['sign'] = $this->MakeSign($inputObj);
		$xml = $this->ToXml($inputObj);
		
		//$startTimeStamp = self::getMillisecond();//请求开始时间
		$response = $this->postXmlCurl($xml, WePay::UNIPAYURL, false, $timeOut);
		$result = $this->CheckResponse($response);
		//self::reportCostTime(WePay::UNIPAYURL, $startTimeStamp, $result);//上报请求花费时间
		
		return $result;
	}

    /**
     * 查询订单
     * @param string $order_sn
     * @param int $timeOut
     * @return mixed
     * @throws WeChatPayException
     */
	public function orderQuery($order_sn = '', $timeOut = 6)
    {
        if ($order_sn == '') {
            throw new WeChatPayException("缺少商户订单号"); //商户订单号
        }
        $inputObj['out_trade_no'] = $order_sn;

        $inputObj['appid'] = $this->appID;              //公众账号ID
        $inputObj['mch_id'] = $this->mchID;             //商户号
        $inputObj['nonce_str'] = $this->getNonceStr();  //随机字符串
        $inputObj['sign'] = $this->MakeSign($inputObj); //签名

        $xml = $this->ToXml($inputObj);
        $response = $this->postXmlCurl($xml, WePay::ORDERQUERY, false, $timeOut);
        $result = $this->CheckResponse($response);

        return $result;
    }
	
	/**
	 * 以post方式提交xml到对应的接口url
	 *
	 * @param string $xml  需要post的xml数据
	 * @param string $url  url
	 * @param bool $useCert 是否需要证书，默认不需要
	 * @param int $second   url执行超时时间，默认30s
	 * @throws WxPayException
	 */
	protected function postXmlCurl($xml, $url, $useCert = false, $second = 30)
	{
		$ch = curl_init();
		//设置超时
		curl_setopt($ch, CURLOPT_TIMEOUT, $second);
	
		//如果有配置代理这里就设置代理
		if(WePay::CURL_PROXY_HOST != "0.0.0.0"
				&& WePay::CURL_PROXY_PORT != 0){
					curl_setopt($ch,CURLOPT_PROXY, WePay::CURL_PROXY_HOST);
					curl_setopt($ch,CURLOPT_PROXYPORT, WePay::CURL_PROXY_PORT);
		}
		curl_setopt($ch,CURLOPT_URL, $url);
		curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,TRUE);
		curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,2);//严格校验
		//设置header
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		//要求结果为字符串且输出到屏幕上
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	
		if($useCert == true){
			//设置证书
			//使用证书：cert 与 key 分别属于两个.pem文件
			curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
			curl_setopt($ch,CURLOPT_SSLCERT, WePay::SSLCERT_PATH);
			curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
			curl_setopt($ch,CURLOPT_SSLKEY, WePay::SSLKEY_PATH);
		}
		//post提交方式
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
		//运行curl
		$data = curl_exec($ch);
		//返回结果
		if($data){
			curl_close($ch);
			return $data;
		} else {
			$error = curl_errno($ch);
			curl_close($ch);
			throw new WeChatPayException("curl出错，错误码:$error");
		}
	}

	
	/**
	 *
	 * 拼接签名字符串
	 * @param array $urlObj
	 *
	 * @return 返回已经拼接好的字符串
	 */
	private function ToUrlParams($urlObj)
	{
		$buff = "";
		foreach ($urlObj as $k => $v)
		{
			if($k != "sign"){
				$buff .= $k . "=" . $v . "&";
			}
		}
	
		$buff = trim($buff, "&");
		return $buff;
	}
	
	/**
	 * 生成签名
	 * @return 签名，本函数不覆盖sign成员变量，如要设置签名需要调用SetSign方法赋值
	 */
	public function MakeSign($values)
	{
		//签名步骤一：按字典序排序参数
		ksort($values);
		$string = $this->ToUrlParams($values);
		//签名步骤二：在string后加入KEY
		$string = $string . "&key=".$this->key;
		//签名步骤三：MD5加密
		$string = md5($string);
		//签名步骤四：所有字符转为大写
		$result = strtoupper($string);
		return $result;
	}
	
	/**
	 * 输出xml字符
	 * @throws WxPayException
	 **/
	public function ToXml($values)
	{
		if(!is_array($values)
				|| count($values) <= 0)
		{
			throw new WeChatPayException("数组数据异常！");
		}
		 
		$xml = "<xml>";
		foreach ($values as $key=>$val)
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
	 * @throws WxPayException
	 */
	public function CheckResponse($value)
	{
		$data = $this->FromXml($value);
		//fix bug 2015-06-29
		if($data['return_code'] != 'SUCCESS'){
			return $data;
		}
		$res = $this->CheckSign($data);
		return $res;
	}
	
	/**
	 * 将xml转为array
	 * @param string $xml
	 * @throws WxPayException
	 */
	public function FromXml($xml)
	{
		if(!$xml){
			throw new WeChatPayException("xml数据异常！");
		}
		//将XML转为array
		//禁止引用外部xml实体
		libxml_disable_entity_loader(true);
		$values = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
		return $values;
	}
	
	/**
	 *
	 * 检测签名
	 */
	public function CheckSign($values)
	{
		//fix异常
		if(!array_key_exists('sign', $values)){
			throw new WeChatPayException("签名错误！");
		}
	
		$sign = $this->MakeSign($values);
		if($values['sign'] == $sign){
			return $values;
		}
		throw new WeChatPayException("签名错误！");
	}
	
	/**
	 *
	 * 产生随机字符串，不长于32位
	 * @param int $length
	 * @return 产生的随机字符串
	 */
	public function getNonceStr($length = 32)
	{
		$chars = "abcdefghijklmnopqrstuvwxyz0123456789";
		$str ="";
		for ( $i = 0; $i < $length; $i++ )  {
			$str .= substr($chars, mt_rand(0, strlen($chars)-1), 1);
		}
		return $str;
	}
	
}