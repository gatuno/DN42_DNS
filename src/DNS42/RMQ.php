<?php

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class DNS42_RMQ {
	public static function send_create_domain ($managed) {
		$server = Gatuf::config ('amqp_dns_server', 'localhost');
		$user = Gatuf::config ('amqp_dns_user', 'guest');
		$pass = Gatuf::config ('amqp_dns_password', 'guest');
		$port = Gatuf::config ('amqp_dns_port', 5672);
		$vhost = Gatuf::config ('amqp_dns_vhost', '/');
		
		$connection = new AMQPStreamConnection($server, $port, $user, $pass, $vhost);
		
		$channel = $connection->channel();
		
		$channel->queue_declare ('dns_zone_add', false, true, false, false);
		
		$msg = new AMQPMessage ($managed->id, array('delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT));
		
		$channel->basic_publish ($msg, '', 'dns_zone_add');
		
		$channel->close();
		$connection->close();
		
		return true;
	}
}
