<?php
namespace oauth\wechat;

use Yii;
use yii\base\Component;
use yii\web\UnauthorizedHttpException;
use yii\helpers\Json;


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

			$token = $this->curlContents($url);
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

    /**
     * auth.code2Session登陆, 获取 open_id and session_key
     * @param string $jsCode
     * @return array|mixed
     * @throws UnauthorizedHttpException
     */
    public function codeTwoSession($jsCode = '')
    {
        $params['appid'] = $this->appID;
        $params['secret'] = $this->appSecret;
        $params['js_code'] = $jsCode;
        $params['grant_type'] = 'authorization_code';

        $url = $this->buildUrl(Mini::CSHOST, $params);

        $response = $this->curlContents($url);
        $result = Json::decode($response);

        if (is_array($result) && isset($result['errcode']))
        {
            throw new UnauthorizedHttpException('wechat login code2Session failed ' . $result['errcode'] . ' ' . $result['errmsg']);
        }

        return $result;
    }

    protected function buildUrl($url, $params = array())
	{
		if ($params) $url .= '?' . http_build_query($params, null, '&');
		return $url;
    }

    /**
     * 通过http请求数据
     * @access public
     * @param  string $url 网址
     * @param  string $method 请求方式 默认GET
     * @param  array  $args 请求参数
     * @param  array  $header 头部
     * @param  integer $timeout 超时时间 默认30秒
     * @return string
     */
    public static function curlContents($url, $method = 'GET', $args = array(), $header = array(), $timeout = 30)
    {
        $response = '';

        if (filter_var($url, FILTER_VALIDATE_URL))
        {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.115 Safari/537.36');

            if ($method == 'POST')
            {
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $args);
            }

            $response = curl_exec($ch);
            curl_close($ch);
        }

        return $response;
    }

}

?>
