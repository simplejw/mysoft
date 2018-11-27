<?php

namespace queue\amqp;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

use Yii;
use yii\base\Component;
use yii\base\NotSupportedException;
/**
 * Amqp Queue.
 */
class Tasker extends Component
{
    public $host = 'localhost';
    public $port = 5672;
    public $user = 'guest';
    public $password = 'guest';
    public $queueName = 'queue';
    public $exchangeName = 'exchange';
    public $vhost = '/';
    public $routing_key = 'key';
    /**
     * @var AMQPStreamConnection
     */
    protected $connection;
    /**
     * @var AMQPChannel
     */
    protected $channel;


    /**
     * @inheritdoc
     */
    public function init()
    {

    }

    /**
     * Pushes job into queue.
     */
    public function push($jobMessage)
    {
        //$jobMessage = serialize($jobMessage);

        return $this->pushMessage($jobMessage);
    }

    /**
     * @inheritdoc
     */
    protected function pushMessage($message)
    {
        $this->open();
        $id = uniqid('', true);
        $this->channel->basic_publish(
            new AMQPMessage($message, [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'message_id' => $id,
            ]),
            $this->exchangeName,
            $this->routing_key
        );

        $this->close();
        return $id;
    }

    /**
     * @inheritdoc
     */
    public function status($id)
    {
        throw new NotSupportedException('Status is not supported in the driver.');
    }

    /**
     * Opens connection and channel.
     */
    protected function open()
    {
        if ($this->channel) {
            return;
        }
        $this->connection = new AMQPStreamConnection($this->host, $this->port, $this->user, $this->password, $this->vhost);
        $this->channel = $this->connection->channel();
        $this->channel->queue_declare($this->queueName, false, true, false, false);
        $this->channel->exchange_declare($this->exchangeName, 'direct', false, true, false);
        $this->channel->queue_bind($this->queueName, $this->exchangeName, $this->routing_key);
    }

    /**
     * Closes connection and channel.
     */
    protected function close()
    {
        if (!$this->channel) {
            return;
        }
        $this->channel->close();
        $this->connection->close();
    }
}
