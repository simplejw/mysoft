Requirements
        "php": ">=5.0",
        "yiisoft/yii2": "~2.0.14"

Installation
The preferred way to install this extension is through composer.
Either run

    composer require "wangjw/mysoft:dev-master"

or add

    "wangjw/mysoft": "dev-master"

to the require section of your composer.json.


Configuration

    return [
        //....
        'components' => [
            'wechat' => [
                'class' => 'oauth\wechat\WeChat',
                'appID' => 'xxx',
                'appSecret' => 'xxxyyy',
            ],
            'sessionhelper' => [
                'class' => 'web\tool\SessionHelper',
            ],
            'redisMQ' => [
                'class' => 'queue\redis\Tasker',
                'redis' => 'redis', // Redis connection component or its config
                'channel' => 'queue', // Queue channel key
            ],
            'amqp' => [
                'class' => 'queue\amqp\Tasker',
                'host' => 'localhost',
                'port' => 5672,
                'user' => 'guest',
                'password' => 'guest',
                'exchangeName' => 'direct_logs',
                'vhost' => '/',
                'routing_key' => 'key',
            ],
        ]
    ];

Usage

    OSS
    // POST表单上传参数
    Yii::$app->oss->postObjectParam('ObjectName');
    // Header Auth of PUT/DELETE..
    Yii::$app->oss->createHeaderAuth('PUT/DELETE/..', 'ObjectName');

    Sms
    // 发送验证码短信
    Yii::$app->sms->send($mobile, $template_code, ['code' => $content]);