<?php

require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;

class Customer
{
	public $host = 'localhost';
    public $port = 5672;
    public $user = 'guest';
    public $password = 'guest';

    public $vhost = '/';
    public $exchangeName = 'direct_logs';
    public $exchangeType = 'direct';
    public $routing_key = 'key';

    public $queueName = 'queuexxx';

    /**
     * @var AMQPStreamConnection
     */
    protected $connection;
    /**
     * @var AMQPChannel
     */
    protected $channel;
    
	
	function __construct()
	{
		if ($this->channel) {
            return;
        }
		$this->connection = new AMQPStreamConnection($this->host, $this->port, $this->user, $this->password);
		$this->channel = $this->connection->channel();

		$this->channel->queue_declare($this->queueName, false, true, false, false);
		$this->channel->exchange_declare($this->exchangeName, $this->exchangeType, false, true, false);
		$this->channel->queue_bind($this->queueName, $this->exchangeName, $this->routing_key);
	}

	function work($msg = '')
	{
		echo 'okok' . $msg . "\n";
	}

	function execute()
	{
		echo " [*] Waiting for logs. To exit press CTRL+C\n";

		$callback = function ($msg) {
		    echo ' [x] ', $msg->delivery_info['routing_key'], ':', $msg->body, "\n";
		    $this->work($msg->body);
		    $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
		};

		$this->channel->basic_qos(null, 1, null);

		$this->channel->basic_consume($this->queueName, '', false, false, false, false, $callback);

		while (count($this->channel->callbacks)) {
		    $this->channel->wait();
		}

		$this->channel->close();
		$this->connection->close();

		return true;
	}
}

$mo = new Customer();
$res = $mo->execute();