<?php
namespace queue\redis;

use Yii;
use yii\base\Component;
use yii\db\Exception;
use yii\base\InvalidArgumentException;

class Tasker extends Component
{
     /**
     * @var Connection|array|string
     */
    public $redis = 'redis';
    /**
     * @var string
     */
    public $channel = 'queue';
    /**
     * @var int default time to reserve a job
     */
    public $ttr = 300;
    /**
     * @var int default attempt count
     */
    public $attempts = 1;
    /**
     *  @var int Sets delay for later execute.
     */
    private $pushDelay;

    /**
     * @see Queue::isWaiting()
     */
    const STATUS_WAITING = 1;
    /**
     * @see Queue::isReserved()
     */
    const STATUS_RESERVED = 2;
    /**
     * @see Queue::isDone()
     */
    const STATUS_DONE = 3;


    public function init()
	{
		
    }

    /**
     * @inheritdoc
     */
    public function status($id)
    {
        if (!is_numeric($id) || $id <= 0) {
            throw new InvalidArgumentException("Unknown message ID: $id.");
        }

        if (Yii::$app->redis->hexists("$this->channel.attempts", $id)) {
            return self::STATUS_RESERVED;
        }

        if (Yii::$app->redis->hexists("$this->channel.messages", $id)) {
            return self::STATUS_WAITING;
        }

        return self::STATUS_DONE;
    }

    /**
     * Clears the queue.
     *
     * @since 2.0.1
     */
    public function clear()
    {
        while (!Yii::$app->redis->set("$this->channel.moving_lock", true, 'NX')) {
            usleep(10000);
        }
        Yii::$app->redis->executeCommand('DEL', Yii::$app->redis->keys("$this->channel.*"));
    }

    /**
     * Removes a job by ID.
     *
     * @param int $id of a job
     * @return bool
     * @since 2.0.1
     */
    public function remove($id)
    {
        while (!Yii::$app->redis->set("$this->channel.moving_lock", true, 'NX', 'EX', 1)) {
            usleep(10000);
        }
        if (Yii::$app->redis->hdel("$this->channel.messages", $id)) {
            Yii::$app->redis->zrem("$this->channel.delayed", $id);
            Yii::$app->redis->zrem("$this->channel.reserved", $id);
            Yii::$app->redis->lrem("$this->channel.waiting", 0, $id);
            Yii::$app->redis->hdel("$this->channel.attempts", $id);

            return true;
        }

        return false;
    }

    /**
     * Sets delay for later execute.
     *
     * @param int|mixed $value
     * @return $this
     */
    public function delay($value)
    {
        $this->pushDelay = $value > 0 ? $value : 0;
        return $this;
    }

    /**
     * Deletes message by ID.
     *
     * @param int $id of a message
     */
    protected function delete($id)
    {
        Yii::$app->redis->zrem("$this->channel.reserved", $id);
        Yii::$app->redis->hdel("$this->channel.attempts", $id);
        Yii::$app->redis->hdel("$this->channel.messages", $id);
    }
    
    /**
     * @inheritdoc
     */
    protected function pushMessage($message, $ttr, $delay)
    {
        $id = Yii::$app->redis->incr("$this->channel.message_id");
        Yii::$app->redis->hset("$this->channel.messages", $id, "$ttr;$message");
        if (!$delay) {
            Yii::$app->redis->lpush("$this->channel.waiting", $id);
        } else {
            Yii::$app->redis->zadd("$this->channel.delayed", time() + $delay, $id);
        }

        return $id;
    }

    /**
     * Pushes job into queue.
     *
     * @param JobInterface|mixed $job
     * @return string|null id of a job message
     */
    public function push($jobMessage)
    {
        $jobMessage = serialize($jobMessage);

        return $this->pushMessage($jobMessage, $this->ttr, $this->pushDelay);
    }
}
?>