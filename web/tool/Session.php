<?php
namespace web\tool;

use Yii;
use yii\base\Component;
use yii\base\Exception;
use app\components\Auth;

/**
*
*/
class SessionHelper extends Component
{
	private $ip = '127.0.0.1';
	private $sessid = '';

	public function init()
	{

	}

	public function get()
	{
		echo 987;exit;
	}

	public function getSessId()
	{
		$this->sessid = $this->initSession();
		//$this->loadSession($this->sessid);

		return $this->sessid;
	}

	/**
	 * 设置过期
	 */
	protected function loadSession($sessionid)
	{
		$key = 'sess_' . $sessionid;

		Yii::$app->redis->set($key, 1, SESSION_EXP);
	}

	/**
	 * 写cookie
	 * @access public
	 * @param  string $key
	 * @param  string $value
	 * @param  bool  $encode
	 * @param  bool  $httpOnly
	 * @param  integer $expire
	 * @return void
	 */
	public static function writeCookie($key, $value, $encode = false, $httpOnly = true, $expire = 0)
	{
		if ($encode)
		{
			$value = Auth::authcode($value, \Yii::$app->params['hashkey']);
		}

		$cookie = new \yii\web\Cookie();
		$cookie->name = $key;
		$cookie->value = $value;
		$cookie->httpOnly = $httpOnly;
		$cookie->domain = self::domain();

		if ($expire > 0) $cookie->expire = time() + $expire;

		Yii::$app->response->cookies->add($cookie);
    }

    /**
	 * 读cookie
	 * @access public
	 * @param  string $key
	 * @param  bool  $encode
	 * @return void
	 */
	public function readCookie($key, $encode = false)
	{
		$value = '';

		if (Yii::$app->request->cookies->has($key))
		{
			$value = Yii::$app->request->cookies->get($key);
			if ($encode)
			{
				$value = Auth::authcode($value, \Yii::$app->params['hashkey'], false);
			}
		}

		return $value;
	}

	/**
	 * 初始化会话信息
	 */
	public function initSession()
	{
		$_sessionid = $this->readCookie('SXSESSIONID');

		/* 判断会话是否合法 */
		if (!empty($_sessionid))
		{
			$tmp_session_id = substr($_sessionid, 0, 32);
			if ($this->genSessionKey($tmp_session_id) == substr($_sessionid, 32))
            {
            	return $tmp_session_id;
            }
		}

		$_sessionid = md5(uniqid(mt_rand(), true));

		$this->writeCookie('SXSESSIONID', $_sessionid . $this->genSessionKey($_sessionid));

		return $_sessionid;
	}

	/**
	 * 根据ip和sessionid生成奇偶数
	 *
	 * @params string $sessionid
	 * @access protected
	 *
	 * @return string $sessionid
	 */
	protected function genSessionKey($sessionid)
	{
		$ip = $this->getIp();
		$ip = substr($ip, 0, strrpos($ip, '.'));
		return sprintf('%08x', crc32($ip . $sessionid));
    }

    private function domain()
	{
		$hostinfo = parse_url(Yii::$app->request->getHostInfo());
		$host = $hostinfo['host'];

		$_fdot = strpos($host, '.');
		if ($_fdot)
		{
			$host = substr($host, $_fdot + 1);
		}

		return $host;
    }

	/**
	 * 获得用户的真实IP地址
	 *
	 * @access  public
	 * @return  string
	 */
	public function getIp()
	{
	    if (isset($_SERVER))
	    {
	        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
	        {
	            $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);

	            /* 取X-Forwarded-For中第一个非unknown的有效IP字符串 */
	            foreach ($arr AS $ip)
	            {
	                $ip = trim($ip);

	                if ($ip != 'unknown')
	                {
	                    $this->ip = $ip;
	                    break;
	                }
	            }
	        }
	        elseif (isset($_SERVER['HTTP_CLIENT_IP']))
	        {
	            $this->ip = $_SERVER['HTTP_CLIENT_IP'];
	        }
	        else
	        {
	            if (isset($_SERVER['REMOTE_ADDR']))
	            {
	                $this->ip = $_SERVER['REMOTE_ADDR'];
	            }
	            else
	            {
	                $this->ip = '0.0.0.0';
	            }
	        }
	    }
	    else
	    {
	        if (getenv('HTTP_X_FORWARDED_FOR'))
	        {
	            $this->ip = getenv('HTTP_X_FORWARDED_FOR');
	        }
	        elseif (getenv('HTTP_CLIENT_IP'))
	        {
	            $this->ip = getenv('HTTP_CLIENT_IP');
	        }
	        else
	        {
	            $this->ip = getenv('REMOTE_ADDR');
	        }
	    }

	    preg_match("/[\d\.]{7,15}/", $this->ip, $onlineip);
	    $this->ip = !empty($onlineip[0]) ? $onlineip[0] : '0.0.0.0';

	    return $this->ip;
	}
}
?>
