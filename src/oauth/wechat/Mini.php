<?php
namespace oauth\wechat;

use Yii;
use yii\base\Component;
use yii\web\UnauthorizedHttpException;
use yii\helpers\Json;

use app\components\Helper;

class Mini extends Component
{
    public $appID; //web.php
    public $appSecret; //web.php

    const CSHOST = 'https://api.weixin.qq.com/sns/jscode2session';
    const ATURL = 'https://api.weixin.qq.com/cgi-bin/token';

    public function init()
	{

    }

    public function getAccessToken()
    {
        $key = 'access_token';
        $result = Yii::$app->cache->get($key);

        if ( empty($result) )
        {
            $params = [];
			$params['grant_type'] = 'client_credential';
			$params['appid'] = $this->appID;
            $params['secret'] = $this->appSecret;

            $url = $this->buildUrl(Wechat::ATURL, $params);

			$token = Helper::curlContents($url);
            $result = Json::decode($token);

            if (is_array($result) && isset($result['errcode']))
			{
				throw new UnauthorizedHttpException('wechat login accesstoken failed ' . $result['errcode'] . ' ' . $result['errmsg']);
            }

            $result['expires_in'] = intval($result['expires_in'] * 0.8);

            Yii::$app->cache->set($key, $result, $result['expires_in']);
        }

        return $result['access_token'];
    }

    // code2Session get open_id and session_key
    public function codeTwoSession($js_code = '')
    {
        $params = [];
        $params['appid'] = $this->appID;
        $params['secret'] = $this->appSecret;
        $params['js_code'] = $js_code;
        $params['grant_type'] = 'authorization_code';

        $url = $this->buildUrl(Mini::CSHOST, $params);

        $token = Helper::curlContents($url);
        $result = Json::decode($token);

        if (is_array($result) && isset($result['errcode']))
        {
            throw new UnauthorizedHttpException('wechat login code2Session failed ' . $result['errcode'] . ' ' . $result['errmsg']);
        }

        // 自定义登录态
        $result['login_key'] = md5($result['openid']);

        return $result;
    }

    protected function buildUrl($url, $params = array())
	{
		if ($params) $url .= '?' . http_build_query($params, null, '&');
		return $url;
    }

}

?>
