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
		$channel->queue_declare ('dns_zone_slave_add', false, true, false, false);
		
		$msg = new AMQPMessage ($managed->id, array('delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT));
		
		if ($managed->maestra) {
			// Si la zona dns es de tipo maestra, primero, solo notificar al maestro.
			// El maestro a su vez, notificará a sus esclavos cuando la zona esté lista
			$channel->basic_publish ($msg, '', 'dns_zone_add');
		} else {
			// Si la zona es totalmente esclava, primero validar que pueda hacer el AXFR
			// Si el axfr funciona, luego se notificará a todos los esclavos para que agreguen la zona
			$channel->basic_publish ($msg, '', 'dns_zone_slave_add');
		}
		
		$channel->close();
		$connection->close();
		
		return true;
	}
	
	public static function send_notify_slaves_from_master ($managed) {
		$server = Gatuf::config ('amqp_dns_server', 'localhost');
		$user = Gatuf::config ('amqp_dns_user', 'guest');
		$pass = Gatuf::config ('amqp_dns_password', 'guest');
		$port = Gatuf::config ('amqp_dns_port', 5672);
		$vhost = Gatuf::config ('amqp_dns_vhost', '/');
		
		$connection = new AMQPStreamConnection($server, $port, $user, $pass, $vhost);
		
		$channel = $connection->channel();
		
		$channel->exchange_declare('dns_zone_master_slave_add', 'fanout', false, false, false);
		
		$msg = new AMQPMessage ($managed->id, array('delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT));
		
		$channel->basic_publish ($msg, 'dns_zone_master_slave_add');
		
		$channel->close();
		$connection->close();
		
		return true;
	}
	
	public static function send_delete_domain ($managed) {
		$server = Gatuf::config ('amqp_dns_server', 'localhost');
		$user = Gatuf::config ('amqp_dns_user', 'guest');
		$pass = Gatuf::config ('amqp_dns_password', 'guest');
		$port = Gatuf::config ('amqp_dns_port', 5672);
		$vhost = Gatuf::config ('amqp_dns_vhost', '/');
		
		$connection = new AMQPStreamConnection($server, $port, $user, $pass, $vhost);
		
		$channel = $connection->channel();
		
		$channel->exchange_declare('dns_zone_del', 'fanout', false, false, false);
		//$channel->queue_declare ('dns_zone_del', false, true, false, false);
		
		$msg = new AMQPMessage ($managed->dominio, array('delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT));
		
		$channel->basic_publish ($msg, 'dns_zone_del');
		
		$channel->close();
		$connection->close();
		
		return true;
	}
	
	public static function send_add_record ($record) {
		$server = Gatuf::config ('amqp_dns_server', 'localhost');
		$user = Gatuf::config ('amqp_dns_user', 'guest');
		$pass = Gatuf::config ('amqp_dns_password', 'guest');
		$port = Gatuf::config ('amqp_dns_port', 5672);
		$vhost = Gatuf::config ('amqp_dns_vhost', '/');
		
		$connection = new AMQPStreamConnection($server, $port, $user, $pass, $vhost);
		
		$channel = $connection->channel();
		
		$channel->queue_declare ('dns_record_add', false, true, false, false);
		
		$msg = new AMQPMessage ($record->id, array('delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT));
		
		$channel->basic_publish ($msg, '', 'dns_record_add');
		
		$channel->close();
		$connection->close();
		
		return true;
	}
	
	public static function send_del_record ($record) {
		$server = Gatuf::config ('amqp_dns_server', 'localhost');
		$user = Gatuf::config ('amqp_dns_user', 'guest');
		$pass = Gatuf::config ('amqp_dns_password', 'guest');
		$port = Gatuf::config ('amqp_dns_port', 5672);
		$vhost = Gatuf::config ('amqp_dns_vhost', '/');
		
		$connection = new AMQPStreamConnection($server, $port, $user, $pass, $vhost);
		
		$channel = $connection->channel();
		
		$channel->queue_declare ('dns_record_del', false, true, false, false);
		
		$body = sprintf ("%s %s %s %s", $record->dominio, $record->name, $record->type, $record->rdata);
		$msg = new AMQPMessage ($body, array('delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT));
		
		$channel->basic_publish ($msg, '', 'dns_record_del');
		
		$channel->close();
		$connection->close();
		
		return true;
	}
}
