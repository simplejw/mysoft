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

    /**
     * 获取小程序全局唯一后台接口调用凭据（access_token）
     */
    public function getAccessToken()
    {
        $key = 'access_token';
        $cacheData = Yii::$app->cache->get($key);

        if ($cacheData === false) {
            $params = [
                'appid'      => $this->appID,
                'secret'     => $this->appSecret,
                'grant_type' => 'client_credential',
            ];
            $url = $this->buildUrl(self::ATURL, $params);
            $response = $this->curlContents($url);
            $cacheData = Json::decode($response);

            if (isset($cacheData['errcode'])) {
                throw new UnauthorizedHttpException('wechat login accesstoken failed ' . $cacheData['errcode'] . ' ' . $cacheData['errmsg']);
            }

            $cacheData['expires_in'] = intval($cacheData['expires_in'] * 0.8);
            Yii::$app->cache->set($key, $cacheData, $cacheData['expires_in']);
        }
        return $cacheData['access_token'];
    }

    /**
     * auth.code2Session登陆, 获取 open_id and session_key
     * @see https://developers.weixin.qq.com/miniprogram/dev/api-backend/open-api/login/auth.code2Session.html
     * @param int $memberId
     * @param string $jsCode
     * @return mixed|null
     * @throws UnauthorizedHttpException
     */
    public function codeTwoSession($jsCode = '')
    {
        if ($jsCode === '') {
            throw new UnauthorizedHttpException();
        }

        $params = [
            'appid'      => $this->appID,
            'secret'     => $this->appSecret,
            'js_code'    => $jsCode,
            'grant_type' => 'authorization_code',
        ];
        $url = $this->buildUrl(self::CSHOST, $params);
        $response = $this->curlContents($url);
        $infoData = Json::decode($response);

        if (isset($infoData['errcode'])) {
            throw new UnauthorizedHttpException('wechat login accesstoken failed ' . $infoData['errcode'] . ' ' . $infoData['errmsg']);
        }

        $infoData['expires_in'] = intval($infoData['expires_in'] * 0.8);

        return $infoData;
    }

    private function buildUrl($url, $params = array())
	{
	    if (!empty($params)) {
            $url .= '?' . http_build_query($params, null, '&');
        }
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
