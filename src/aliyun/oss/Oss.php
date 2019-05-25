<?php

namespace aliyun\oss;

use yii\base\Component;
use yii\base\Exception;
use yii\helpers\Json;

class OssException extends Exception
{
	
}

/**
* 
*/
class Oss extends Component
{
	public $accessKeyId;      //main.php
	public $accessKeySecret;  //main.php
    public $endpoint;         //main.php
    public $bucket;           //main.php

    const EXPIRATION = 86400;     //有效期一天
    const CONTENTSIZE = 20971520; //文件最大20M

    public function init()
    {
    
    }

    /**
     * Param of Post Object
     * @param $objectName ('path/fileName.jpg')
     * @return array
     */
    public function postObjectParam($objectName = '')
    {
        $policy = $this->createPolicy($objectName);
        $signature = $this->createSignature($policy);

        $data = [];
        $data['api_url'] = $this->bucketHost();
        $data['OSSAccessKeyId'] = $this->accessKeyId;
        $data['policy'] = $policy;
        $data['Signature'] = $signature;
        $data['key'] = $objectName;
        $data['success_action_status'] = '200';

        return $data;
    }

    /**
     * Post Policy
     * @return string
     */
    public function createPolicy($object = '')
    {
        $policy = [];
        $policy['expiration'] = $this->getGMT(self::EXPIRATION + time());
        $policy['conditions'][] = ["eq", "\$bucket", $this->bucket];
        $policy['conditions'][] = ["eq", "\$key", $object];
        $policy['conditions'][] = ['content-length-range', 0, self::CONTENTSIZE];
        
        $j_res = Json::encode($policy);
        $base64policy  = base64_encode($j_res);
        
        return $base64policy;
    }

    /**
     * Post Signature
     * @return string
     */
    public function createSignature($base64policy)
    {
        return base64_encode(hash_hmac('sha1', $base64policy, $this->accessKeySecret, true));
    }

    /**
     * Bucket Host (api_url)
     * @return string
     */
    public function bucketHost()
    {
        return 'https://' . $this->bucket . '.' . $this->endpoint;
    }

    /**
     * ISO8601 GMT时间
     * @param Unix时间戳
     * @return string
     */
    public function getGMT($unixTime = 0)
    {
        $timezone = +8; //China
        $resTime = 3600 * ($timezone + date("I")) + $unixTime;
        return gmdate("Y-m-d\TH:i:s\Z", $resTime);
    }

    /**
     * Header Signature
     * @return string
     */
    public function createHeaderSignature($method, $gmtdate, $object, $ContentMD5='', $ContentType='')
    {
        $signString = $method . "\n" . $ContentMD5 . "\n" . $ContentType . "\n" . $gmtdate . "\n" . '/' . $this->bucket . $object;
        
        //$string_to_sign = "DELETE\n\n\nMon, 27 Aug 2018 03:50:33 GMT\n/cn-admin/upload/1535333108.jpg";
        return base64_encode(hash_hmac('sha1', $signString, $this->accessKeySecret, true));
    }

    /**
     * Header Auth Param
     * @return string
     */
    public function createHeaderAuth($method, $object)
    {
        $header = [];

        //操作的GMT时间
        $gmtdate = gmdate('D, d M Y H:i:s \G\M\T');

        $headerSignature = $this->createHeaderSignature($method, $gmtdate, $object);
		$header['Authorization'] = 'Authorization: ' . "OSS " . $this->accessKeyId . ":" . $headerSignature;
        $header['Date'] = 'Date: ' . $gmtdate;
        
        return $header;
    }


    private function stringToSignSorted($string_to_sign)
    {
        $queryStringSorted = '';
        $explodeResult = explode('?', $string_to_sign);
        $index = count($explodeResult);
        if ($index === 1)
            return $string_to_sign;

        $queryStringParams = explode('&', $explodeResult[$index - 1]);
        sort($queryStringParams);

        foreach($queryStringParams as $params)
        {
             $queryStringSorted .= $params . '&';    
        }

        $queryStringSorted = substr($queryStringSorted, 0, -1);

        return $explodeResult[0] . '?' . $queryStringSorted;
    }

 


}
?>