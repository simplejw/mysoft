<?php

namespace aliyun\vod;


use yii\base\Component;
use yii\base\Exception;


class OssException extends Exception
{

}

class Vod extends Component
{
    /**
     * @var string
     */
    private $accessKeyId = 'LTAI4FfXagsZehawf45M4Y9f';

    /**
     * @var string
     */
    private $accessSecret = 'Icj6G6fOP3hoM6YQ2tFFFZwEZnO8r7';

    /**
     * @var string
     */
    protected $version = '2017-03-21';

    /**
     * @var string
     */
    protected $regionId = 'cn-shanghai';

    /**
     * @var string
     */
    protected $actionName = 'GetVideoPlayAuth';

    /**
     * @var string
     */
    protected $acceptFormat = 'JSON';

    /**
     * @var string
     */
    private $dateTimeFormat = 'Y-m-d\TH:i:s\Z';

    /**
     * @var string
     */
    protected $method = 'GET';


    public function init()
    {

    }

    public function composeUrl()
    {
        $apiParams['RegionId']         = $this->regionId;
        $apiParams['AccessKeyId']      = $this->accessKeyId;
        $apiParams['Format']           = $this->acceptFormat;
        $apiParams['SignatureMethod']  = $this->getSignatureMethod();
        $apiParams['SignatureVersion'] = $this->getSignatureVersion();

        $apiParams['SignatureNonce'] = md5(uniqid(mt_rand(), true));
        $apiParams['Timestamp']      = $this->requestGMDate();
        $apiParams['Action']         = $this->actionName;
        $apiParams['Version']        = $this->version;

        $apiParams['Signature'] = $this->computeSignature($apiParams, $this->accessSecret);

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
            $this->method . '&%2F&' . $this->percentEncode(substr($canonicalizedQueryString, 1));
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
     * 请求的时间戳,UTC时间格式
     * @return false|string
     */
    public function requestGMDate()
    {
        return gmdate($this->dateTimeFormat);
    }

}
?>