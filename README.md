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