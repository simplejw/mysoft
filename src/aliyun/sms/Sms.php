<?php

namespace aliyun\sms;

use yii\base\Component;
use yii\helpers\Json;

class Sms extends Component
{
	public $accessKeyId;
	public $accessKeySecret;
	
	public $signName = '';
	
	public function init()
	{
		
	}
	
	public function send($to, $template_code, $params)
	{
		$data = array(
			'SignName' => $this->signName,
			'Format' => 'JSON',
			'Version' => '2017-05-25',
			'AccessKeyId' => $this->accessKeyId,
			'SignatureVersion' => '1.0',
			'SignatureMethod' => 'HMAC-SHA1',
			'SignatureNonce' => uniqid(),
			'Timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
			'Action' => 'SendSms',
			'PhoneNumbers' => $to,
			'TemplateCode' => $template_code,
			'TemplateParam' => Json::encode($params),
		);
		
		$data['Signature'] = $this->computeSignature($data, $this->accessKeySecret);
		$url = 'http://dysmsapi.aliyuncs.com/?' . http_build_query($data);
		
		$ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $result = curl_exec($ch);
        curl_close($ch);
        $result = Json::decode($result, true);
        
        return $result;
	}
	
	private function percentEncode($string)
	{
		$string = urlencode($string);
		$string = preg_replace('/\+/', '%20', $string);
        $string = preg_replace('/\*/', '%2A', $string);
        $string = preg_replace('/%7E/', '~', $string);
        return $string;
	}
	
	private function computeSignature($parameters, $accessKeySecret)
	{
		ksort ($parameters);
		$canonicalizedQueryString = '';
		foreach ($parameters as $key => $value)
		{
			$canonicalizedQueryString .= '&' . $this->percentEncode($key) . '=' . $this->percentEncode($value);
		}
		
		$stringToSign = 'GET&%2F&' . $this->percentencode(substr($canonicalizedQueryString, 1));
		$signature = base64_encode(hash_hmac('sha1', $stringToSign, $accessKeySecret . '&', true));
		return $signature;
	}
}