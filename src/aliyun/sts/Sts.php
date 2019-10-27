<?php

/**
 * 阿里云临时安全令牌（Security Token Service，STS）是阿里云提供的一种临时访问权限管理服务。
 */

namespace aliyun\sts;

use yii\base\Component;


class Sts extends Component
{
    /**
     * @var string
     */
    public $accessKeyId = '';

    /**
     * @var string
     */
    public $accessKeySecret = '';

    /**
     * @var string
     */
    public $accountID = '';  //main.php

    /**
     * @var string
     */
    public $roleName  = '';  //main.php

    /**
     * HTTP Method
     * @var string
     */
    const HTTP_METHOD = 'GET';

    /**
     * STS的访问域名
     */
    const STS_HOST = 'https://sts.aliyuncs.com';


    /**
     * 调用AssumeRole接口获取一个扮演该角色的临时身份
     * @return string
     */
    public function getAssumeRole()
    {
        $apiParams = $this->pubParams();

        $apiParams['Action']           = 'AssumeRole';
        $apiParams['RoleArn']          = 'acs:ram::' . $this->accountID . ':role/' . $this->roleName;
        $apiParams['RoleSessionName']  = $this->generateRandomString(20);
        $apiParams['DurationSeconds']  = 3600;

        // 签名结果串。
        $apiParams['Signature']        = $this->computeSignature($apiParams, $this->accessKeySecret);

        $uri = self::STS_HOST . '?' . http_build_query($apiParams);

        $response = $this->curlContents($uri);

        return $response;
    }

    /**
     * 调用GetCallerIdentity接口获取当前调用者的身份信息。
     * @return string
     */
    public function getCallerIdentity()
    {
        $apiParams = $this->pubParams();

        $apiParams['Action'] = 'GetCallerIdentity';

        // 签名结果串。
        $apiParams['Signature']        = $this->computeSignature($apiParams, $this->accessKeySecret);

        $uri = self::STS_HOST . '?' . http_build_query($apiParams);

        $response = $this->curlContents($uri);

        return $response;
    }


    /**
     * 公共请求参数
     * @return mixed
     */
    public function pubParams()
    {
        $apiParams['Format']         = 'JSON';
        $apiParams['Version']        = '2015-04-01';
        $apiParams['Timestamp']      = $this->requestGMDate();

        $apiParams['SignatureMethod']  = $this->getSignatureMethod();
        $apiParams['SignatureVersion'] = $this->getSignatureVersion();
        $apiParams['SignatureNonce']   = md5(uniqid(mt_rand(), true));

        $apiParams['AccessKeyId']      = $this->accessKeyId;

        return $apiParams;
    }


    /**
     * 构造签名字符串
     * @param $parameters
     * @param $accessKeySecret
     * @param $iSigner
     *
     * @return mixed
     */
    private function computeSignature($parameters, $accessKeySecret)
    {
        ksort($parameters);
        $canonicalizedQueryString = '';
        foreach ($parameters as $key => $value) {
            $canonicalizedQueryString .= '&' . $this->percentEncode($key) . '=' . $this->percentEncode($value);
        }
        $stringToBeSigned =
            self::HTTP_METHOD . '&%2F&' . $this->percentEncode(substr($canonicalizedQueryString, 1));
        return $this->signString($stringToBeSigned, $accessKeySecret . '&');
    }

    /**
     * 编码参数
     * @param $str
     *
     * @return string|string[]|null
     */
    protected function percentEncode($str)
    {
        $res = urlencode($str);
        $res = str_replace(array('+', '*'), array('%20', '%2A'), $res);
        $res = preg_replace('/%7E/', '~', $res);
        return $res;
    }

    /**
     * 计算签名字符串
     * @param $source
     * @param $accessSecret
     *
     * @return string
     */
    public function signString($source, $accessSecret)
    {
        return base64_encode(hash_hmac('sha1', $source, $accessSecret, true));
    }

    /**
     * 请求的时间戳,UTC时间格式
     * @return false|string
     */
    public function requestGMDate()
    {
        return gmdate('Y-m-d\TH:i:s\Z');
    }

    /**
     * 签名方式，目前支持HMAC-SHA1
     * @return string
     */
    public function getSignatureMethod()
    {
        return 'HMAC-SHA1';
    }

    /**
     * 签名算法版本，目前版本是1.0
     * @return string
     */
    public function getSignatureVersion()
    {
        return '1.0';
    }

    /**
     * 生成随机字符串
     * @param int $length
     * @return string
     */
    public function generateRandomString($length = 10)
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $randomString;
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
    public function curlContents($url, $method = 'GET', $args = array(), $header = array(), $timeout = 30)
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