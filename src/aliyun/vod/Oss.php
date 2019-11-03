<?php

/**
 * 阿里云对象存储服务（Object Storage Service，简称OSS）STS授权模式
 */

namespace aliyun\vod;


class Oss
{
    public $accessKeyId;
    public $accessKeySecret;
    public $securityToken;
    public $expiration;

    public $endpoint;
    public $bucket;
    public $objectName;


    public function __construct(
        $accessKeyId = '',
        $accessKeySecret = '',
        $securityToken = '',
        $bucket = '',
        $endpoint = '',
        $objectName = '',
        $expiration = ''
    )
    {
        $this->accessKeyId     = $accessKeyId;
        $this->accessKeySecret = $accessKeySecret;
        $this->securityToken   = $securityToken;
        $this->expiration      = $expiration;

        $this->endpoint        = $endpoint;
        $this->bucket          = $bucket;
        $this->objectName      = $objectName;
    }


    /**
     * PutObject接口用于上传文件（Object必须以正斜线/开头）
     * @param string $objectName
     * @return array
     */
    public function putObject($filePath = '')
    {
        $authHeader = $this->createHeaderAuth('PUT', '/' . $this->objectName);
        $authHeader['Cache-Control'] = 'Cache-Control: ' . 'no-cache';
        $authHeader['securityToken'] = 'x-oss-security-token:' . $this->securityToken;

        $hostPath = $this->bucketHost() . '/' .  $this->objectName;

        $response = $this->curlContents($hostPath, 'PUT', [$filePath], $authHeader);

        return $response;
    }

    /**
     * PostObject使用HTML表单上传Object到指定Bucket(表单域中file必须是最后一个, Object不能以正斜线（/）或者反斜线（\）开头)
     * @param string $objectName
     * @param string $redirect 上传成功后客户端跳转到的URL,如果未指定该表单域，返回结果由success_action_status表单域指定,并不进行跳转。
     * @return mixed
     */
    public function postObjectParam($objectName = '', $redirect = '')
    {
        $policy = $this->createPolicy($objectName);
        $signature = $this->createSignature($policy);

        $data['apiHost'] = $this->bucketHost();
        $data['OSSAccessKeyId'] = $this->accessKeyId;
        $data['policy'] = $policy;
        $data['Signature'] = $signature;
        $data['key'] = $objectName;
        $data['success_action_status'] = '200';
        if ($redirect !== '') {
            $data['success_action_redirect'] = $redirect;
        }

        return $data;
    }

    /**
     * DeleteObject用于删除某个文件（Object,无论要删除的Object是否存在,删除成功后均会返回204状态码）
     * @param string $objectName
     * @return array
     */
    public function deleteObject($objectName = '')
    {
        $authHeader = $this->createHeaderAuth('DELETE', $objectName);

        $authHeader['hostPath'] = $this->bucketHost() . $objectName;

        return $authHeader;
    }

    /**
     * Post Policy(HTML表单上传Object)
     * @return string
     */
    public function createPolicy($object = '')
    {
        $expiration = 3600;     //有效期一小时
        $maxSize    = 209715200 * 2.5; //文件最大500M

        $policy['expiration']   = $this->getGMT($expiration + time());
        $policy['conditions'][] = ["eq", "\$bucket", $this->bucket];
        $policy['conditions'][] = ["eq", "\$key", $object];
        $policy['conditions'][] = ['content-length-range', 0, $maxSize];

        $j_res = json_encode($policy);
        $base64policy  = base64_encode($j_res);

        return $base64policy;
    }

    /**
     * Post Signature(HTML表单上传Object)
     * @return string
     */
    public function createSignature($base64policy)
    {
        return base64_encode(hash_hmac('sha1', $base64policy, $this->accessKeySecret, true));
    }

    /**
     * Bucket Host
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
     * 计算签名头字符串
     * @param $method
     * @param $gmtdate
     * @param $object
     * @param string $ContentMD5
     * @param string $ContentType
     * @return string
     */
    public function createHeaderSignature($method, $gmtdate, $object, $ContentMD5='', $ContentType='')
    {
        $signString = $method . "\n" .
            $ContentMD5  . "\n" .
            $ContentType . "\n" .
            $gmtdate     . "\n" .
            'x-oss-security-token:' . $this->securityToken . "\n" .
            '/' . $this->bucket . $object;

        //$string_to_sign = "DELETE\n\n\nMon, 27 Aug 2018 03:50:33 GMT\n/cn-admin/upload/1535333108.jpg";
        return base64_encode(hash_hmac('sha1', $signString, $this->accessKeySecret, true));
    }

    /**
     * 获取授权头
     * @param $method HTTP请求的Method
     * @param $object
     * @return array
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
    public function curlContents($url, $method = 'GET', $args = [], $header = [], $timeout = 30)
    {
        $response = '';
        $responseHttpCode = 500;

        if (filter_var($url, FILTER_VALIDATE_URL))
        {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLINFO_HEADER_OUT, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.115 Safari/537.36');

            if ($method == 'POST')
            {
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $args);
            }

            if ($method == 'PUT')
            {
                $handle = fopen($args[0], 'rb');
                curl_setopt($ch, CURLOPT_PUT, true); //设置为PUT请求
                curl_setopt($ch, CURLOPT_INFILE, $handle);  //设置资源句柄
            }

            $response = curl_exec($ch);
            $responseHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); //HTTP响应码

            curl_close($ch);
        }

        return $responseHttpCode;
    }




}