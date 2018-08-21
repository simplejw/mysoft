<?php

namespace oauth\wechat;

use Yii;
use yii\base\Component;
use yii\base\Exception;
use app\components\Helper;
use yii\helpers\Json;

class WeChatOAuthException extends Exception
{
	
}

class WeChat extends Component
{
	public $appID; //main.php
	public $appSecret; //main.php
	public $callback = 'wechat';
	
	const CBHOST = '';
	const ATURL = 'https://api.weixin.qq.com/cgi-bin/token';
	const LOGINURL = 'https://open.weixin.qq.com/connect/oauth2/authorize';
	const GRAPHURL = 'https://api.weixin.qq.com/sns/oauth2/access_token';
	const USERURL = 'https://api.weixin.qq.com/cgi-bin/user/info';
	const MSGURL = 'https://api.weixin.qq.com/cgi-bin/message/custom/send';
	
	public function init()
	{
		
	}
	
	public function createLoginUrl($state = "")
	{
		$params = array();
		$params['appid'] = $this->appID;
		$params['redirect_uri'] = Wechat::CBHOST . '/' . $this->callback;
		$params['response_type'] = 'code';
		$params['scope'] = 'snsapi_base';
		$params['state'] = $state;
		$url = $this->buildUrl(Wechat::LOGINURL, $params) . "#wechat_redirect";
		return $url;
	}
	
	public function getAccessToken()
	{
		$now = time();
		$result = array(
			'access_token' => '',
			'expires_in' => 0,
		);
		
		$sql = "select var_name, var_value from {{sx_config}} where var_name in ('access_token', 'expires_in')";
		foreach (Yii::$app->db->createCommand($sql)->queryAll() as $row)
		{
			$result[$row['var_name']] = $row['var_value'];
		}
		
		if (empty($result['access_token']) || $result['expires_in'] <= $now)
		{
			$params = array();
			$params['grant_type'] = 'client_credential';
			$params['appid'] = $this->appID;
			$params['secret'] = $this->appSecret;
			
			$url = $this->buildUrl(Wechat::ATURL, $params);

			$token = Helper::curlContents($url);
			$result = Json::decode($token);
	
			if (is_array($result) && isset($result['errcode']))
			{
				throw new WeChatOAuthException('wechat login accesstoken failed ' . $result['errcode'] . ' ' . $result['errmsg']);	
			}
			
			$result['expires_in'] = $now + intval($result['expires_in'] * 0.8);
			
			$sql = "update {{sx_config}} set var_value = :var_value where var_name = 'access_token'";
			Yii::$app->db->createCommand($sql)->bindValue(':var_value', $result['access_token'])->execute();
			
			$sql = "update {{sx_config}} set var_value = :var_value where var_name = 'expires_in'";
			Yii::$app->db->createCommand($sql)->bindValue(':var_value', $result['expires_in'])->execute();
		}
		
		return $result['access_token'];
	}
	
	/**
	 * 获取微信APITicket
	 * @return string
	 */
	public function getJsApiTicket()
	{
		$ticket = Yii::$app->redis->get("wechatticket");
		if(empty($ticket))
		{
			$accessToken = $this->getAccessToken();
	
			$url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=$accessToken";
			$ticket = Helper::curlContents($url);
				
			$is_true_ticket = Json::decode($ticket);
				
			if($is_true_ticket['errmsg'] == 'ok')
			{
				Yii::$app->redis->set("wechatticket", $ticket, 7200);
			}
		}
	
		$ticket = Json::decode($ticket);
	
		return $ticket['ticket'];
	}
	
	/**
	 * 微信签名随机码
	 * @return string
	 */
	private function createNonceStr($length = 16) {
		$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
		$str = "";
		for ($i = 0; $i < $length; $i++) {
			$str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
		}
		return $str;
	}
	
	/**
	 *  获取微信签名
	 * @return array
	 */
	public function getSignPackage($current_url = '') {
		$jsapiTicket = $this->getJsApiTicket();
		$url = $current_url;
	
		$timestamp = time();
		$nonceStr = $this->createNonceStr();
		// 这里参数的顺序要按照 key 值 ASCII 码升序排序
		$string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=".$url;
	
		$signature = sha1($string);
	
		$signPackage = array(
				"appId"     => Yii::$app->wechat->appID,
				"nonceStr"  => $nonceStr,
				"timestamp" => $timestamp,
				"url"       => $url,
				"signature" => $signature,
				"rawString" => $string
		);
		return $signPackage;
	}
	
	public function getUserAccessToken($code)
	{
		$params = array();
		$params['appid'] = $this->appID;
		$params['secret'] = $this->appSecret;
		$params['code'] = $code;
		$params['grant_type'] = 'authorization_code';
		$url = $this->buildUrl(Wechat::GRAPHURL, $params);

		$token = Helper::curlContents($url);
		$result = Json::decode($token);

		if (is_array($result) && isset($result['errcode']))
		{
			throw new WeChatOAuthException('wechat login accesstoken failed ' . $result['errcode'] . ' ' . $result['errmsg']);	
		}

		return $result;
	}
	
	public function getUserInfo($openid)
	{
		$params = array();
		$params['access_token'] = $this->getAccessToken();
		$params['openid'] = $openid;
		
		$url = $this->buildUrl(Wechat::USERURL, $params);
		$token = Helper::curlContents($url);
		$result = Json::decode($token);

		if (is_array($result) && (isset($result['errcode']) || empty($result['subscribe'])))
		{
			throw new WeChatOAuthException('user not subscribe');	
		}

		return $result;
	}
	
	public function sendMsg($openid, $msg, $type = 'text')
	{
		$params = array();
		$params['access_token'] = $this->getAccessToken();
		$url = $this->buildUrl(Wechat::MSGURL, $params);
		
		$body = array(
			'touser' => $openid,
			'msgtype' => $type,
			'text' => array(
				'content' => $msg,
			),
		);
		
		$body =  json_encode($body, JSON_UNESCAPED_UNICODE);
		
		$res = Helper::curlContents($url, 'POST', $body);
		$result = Json::decode($res);
		
		if (!empty($result["errcode"]))
		{
			throw new WeChatOAuthException('can not send');	
		}
	}
	
	protected function buildUrl($url, $params = array())
	{
		if ($params) $url .= '?' . http_build_query($params, null, '&');
		return $url;
	}
}
?>