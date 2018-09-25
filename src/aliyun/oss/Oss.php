<?php

namespace oauth\wechat;

use Yii;
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
	public $accessKeyId; //main.php
	public $accessKeySecret; //main.php
    public $endpoint; //main.php
    public $bucket;

    public function init()
    {
    
    }

    public function createPolicy($object = '')
    {
        $policy = array();
        $policy['expiration'] = $this->getGMT(86400);
        $policy['conditions'][] = array("eq", "\$bucket", $this->bucket);
        $policy['conditions'][] = array("eq", "\$key", $object);
        $policy['conditions'][] = array('content-length-range', 0, 20971520);
        
        $j_res = Json::encode($policy);
        $base64policy  = base64_encode($j_res);
        
        return $base64policy;
    }

    public function createSignature($base64policy)
    {
        return base64_encode(hash_hmac('sha1', $base64policy, $this->accessKeySecret, true));
    }

    public function bucketHost()
    {
        return 'http://' . $this->bucket . '.' . $this->endpoint;
    }

    public function getGMT($addTime = 0)
    {
        $timezone  = +8; //China
        $resTime = time() + 3600*($timezone+date("I")) + $addTime;
        return gmdate("Y-m-d\TH:i:s\Z", $resTime);
    }

    public function createHeaderSignature($method, $ContentMD5='', $ContentType='', $gmtdate, $object)
    {
        $string_to_sign = $method . "\n" . $ContentMD5 . "\n" . $ContentType . "\n" . $gmtdate . "\n" . '/' . $this->bucket . $object;
        
        //$string_to_sign = "DELETE\n\n\nMon, 27 Aug 2018 03:50:33 GMT\n/cn-admin/upload/1535333108.jpg";
        $signature = base64_encode(hash_hmac('sha1', $string_to_sign, $this->accessKeySecret, true));
        return $signature;
    }


     /**
     * 获取mimetype类型
     *
     * @param string $object
     * @return string
     */
    private function getMimeType($file = null)
    {
        if (!is_null($file)) {
            $type = MimeTypes::getMimetype($file);
            if (!is_null($type)) {
                return $type;
            }
        }

        return 'application/octet-stream';
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