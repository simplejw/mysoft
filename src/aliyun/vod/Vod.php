<?php

namespace aliyun\vod;

use yii\base\Component;
use aliyun\vod\Oss;

class Vod extends Component
{
    /**
     * @var string
     */
    public $accessKeyId = '';

    /**
     * @var string
     */
    public $accessSecret = '';

    /**
     * 存储区域标识
     * @var string
     */
    protected $regionId = 'cn-shanghai';

    /**
     * HTTP Method
     * @var string
     */
    const HTTP_METHOD = 'GET';

    /**
     * 点播中心的访问域名 华东2（上海）
     */
    const VOD_HOST_SH = 'https://vod.cn-shanghai.aliyuncs.com';


    /**
     * 未完待续
     * @param string $objectName
     * @param string $uploadAddress
     * @param string $authData
     * @return array
     */
    public function formatResponseData($response = '')
    {
        $responseArr = json_decode($response, true);

        $uploadAddressArr = json_decode(base64_decode($responseArr['UploadAddress']), true);
        $uploadAuthArr    = json_decode(base64_decode($responseArr['UploadAuth']), true);

        $endPoint   = str_replace("https://", '', $uploadAddressArr['Endpoint']);
        $bucket     = $uploadAddressArr['Bucket'];
        $objectName = $uploadAddressArr['FileName'];

        $accessKeyId     = $uploadAuthArr['AccessKeyId'];
        $accessKeySecret = $uploadAuthArr['AccessKeySecret'];
        $securityToken   = $uploadAuthArr['SecurityToken'];
        $expireUTCTime   = $uploadAuthArr['ExpireUTCTime'];
        $expiration      = $uploadAuthArr['Expiration'];
        $region          = $uploadAuthArr['Region'];

        $oss = new Oss(
            $accessKeyId,
            $accessKeySecret,
            $securityToken,
            $bucket,
            $endPoint,
            $objectName,
            $expiration
        );

        return $res;
    }

    /**
     * 刷新视频上传凭证
     * @param string $videoId 视频ID
     * @return string
     */
    public function refreshUploadVideo($videoId = '')
    {
        $apiParams = $this->pubParams();

        $apiParams['Action']      = 'RefreshUploadVideo';
        $apiParams['VideoId']       = $videoId;

        // 签名结果串。
        $apiParams['Signature']        = $this->computeSignature($apiParams, $this->accessSecret);

        $uri = self::VOD_HOST_SH . '?' . http_build_query($apiParams);

        $response = $this->curlContents($uri);

        return $response;
    }

    /**
     * 获取视频上传地址和凭证
     * @param string $title 视频标题
     * @param string $fileName 视频源文件名 必须带扩展名
     * @param string $coverURL 自定义视频封面URL地址
     * @param string $description 视频描述 长度不超过1024个字符或汉字
     * @param int $catId 视频分类ID
     * @param array $tags 视频标签 最多不超过16个标签
     *
     * @return string 有效期为3000秒
     */
    public function getUploadAuth($title = '', $fileName = '', $coverURL = '', $description = '', $catId = 0, $tags = [])
    {
        $apiParams = $this->pubParams();

        $apiParams['Action']      = 'CreateUploadVideo';
        $apiParams['Title']       = $title;
        $apiParams['FileName']    = $fileName;
        $apiParams['Description'] = $description;
        $apiParams['CoverURL']    = $coverURL;
        if (intval($catId) !== 0) {
            $apiParams['CateId']  = intval($catId);
        }
        $apiParams['Tags']        = empty($tags) ? '' : implode(",", $tags);

        // 签名结果串。
        $apiParams['Signature']        = $this->computeSignature($apiParams, $this->accessSecret);

        $uri = self::VOD_HOST_SH . '?' . http_build_query($apiParams);

        $response = $this->curlContents($uri);

        return $response;
    }

    /**
     * 获取视频播放地址
     * @param string $videoId 视频ID
     * @param string $videoFormat 视频流格式
     * @param int $authTimeout 播放地址过期时间,单位:秒
     * @return string
     */
    public function getPlayInfo($videoId = '', $videoFormat = '', $authTimeout = 3600)
    {
        $apiParams = $this->pubParams();

        $apiParams['Action']           = 'GetPlayInfo';
        $apiParams['VideoId']          = $videoId;
        $apiParams['AuthTimeout']      = $authTimeout;

        if (in_array($videoFormat, ['mp4', 'm3u8', 'mp3', 'mpd'])) {
            $apiParams['Formats']      = $videoFormat;
        }

        // 签名结果串。
        $apiParams['Signature']        = $this->computeSignature($apiParams, $this->accessSecret);

        $uri = self::VOD_HOST_SH . '?' . http_build_query($apiParams);

        $response = $this->curlContents($uri);

        return $response;
    }

    /**
     * 获取视频播放凭证
     * @param string $videoId 视频ID
     * @return string
     */
    public function getPlayAuth($videoId = '')
    {
        $apiParams = $this->pubParams();

        $apiParams['Action']           = 'GetVideoPlayAuth';
        $apiParams['VideoId']          = $videoId;
        // 播放凭证过期时间。取值范围：100~3000。
        $apiParams['AuthInfoTimeout']  = 100;
        // 签名结果串。
        $apiParams['Signature']        = $this->computeSignature($apiParams, $this->accessSecret);

        $uri = self::VOD_HOST_SH . '?' . http_build_query($apiParams);

        $response = $this->curlContents($uri);

        return $response;
    }

    /**
     * 公共请求参数
     * @return mixed
     */
    public function pubParams()
    {
        $apiParams['RegionId']         = $this->regionId;
        $apiParams['AccessKeyId']      = $this->accessKeyId;
        $apiParams['Format']           = 'JSON';
        $apiParams['SignatureMethod']  = $this->getSignatureMethod();
        $apiParams['SignatureVersion'] = $this->getSignatureVersion();

        $apiParams['SignatureNonce'] = md5(uniqid(mt_rand(), true));
        $apiParams['Timestamp']      = $this->requestGMDate();
        $apiParams['Version']        = '2017-03-21';

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
        return gmdate('Y-m-d\TH:i:s\Z');
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
