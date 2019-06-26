<?php
namespace web\tool;

use Yii;
use yii\base\Component;
use yii\base\Exception;
use app\components\Auth;

class BearerAuth extends Component
{
    private $token = '';

    public function init()
	{
        parent::init();
        \Yii::$app->user->enableSession = false;
    }

    public function getToken()
    {
        $result = ['code'=>200, 'msg'=>'', 'data'=>[]];

        $token = '';

        try {
            $headers = Yii::$app->request->headers;

            if ($headers->has('Token'))
            {
                $token = $headers->get('Token');

                if (!empty($token)) {
                    $result['data']['token'] = $token;
                }else {
                    throw new ErrorException("Token Error");
                }

            }else {
                throw new ErrorException("No Token");
            }

        } catch (\Throwable $th) {
            $result['code'] = 400;
            $result['msg'] = $th->getMessage();
        }

        return $token;
    }


    public function getUserByToken()
    {
        $token = $this->getToken();

        $sql = "SELECT u.username, u.realname, u.user_id FROM {{%user_oauth}} AS uo LEFT JOIN {{%user}} AS u ON uo.user_id = u.user_id WHERE u.is_validated = :is_validated";
        $res = Yii::$app->db->createCommand($sql)->bindValues([':is_validated' => 1])->queryOne();

        if (!empty($res['user_id'])) {
            return $res['user_id'];
        } else {
            $new = new User();
            $new->reg_time = time();
            if (!$new->save()) {
                throw new ErrorException("SYS Error！");
            }
            $user_id = $new->user_id;
            return $user_id;
        }
    }

}
?>
