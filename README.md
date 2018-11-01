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
            'tasker' => [
                'class' => 'queue\redis\Tasker',
            ],
        ]
    ];