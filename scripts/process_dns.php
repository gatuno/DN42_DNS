#!/usr/bin/php
<?php

require 'config.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;

$server = Gatuf::config ('amqp_dns_server', 'localhost');
$user = Gatuf::config ('amqp_dns_user', 'guest');
$pass = Gatuf::config ('amqp_dns_password', 'guest');
$port = Gatuf::config ('amqp_dns_port', 5672);
$vhost = Gatuf::config ('amqp_dns_vhost', '/');

$connection = new AMQPStreamConnection($server, $port, $user, $pass, $vhost);
$channel = $connection->channel();

# Usamos un montón de exec para separar el proceso de php de aquí con el otro. Esto provoca que la base de datos se cierre y se abra conforme se necesite.

$callback_zone_add_master = function ($msg) {
	$full_exec = sprintf ("php %s/process_dns_add_master.php %s 2>&1", dirname (__FILE__), $msg->body);
	
	exec ($full_exec, $output, $return_code);
	foreach ($output as $line) printf ("%s\n", $line);
	
	$msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
};

$callback_zone_slave_check_delegation = function ($msg) {
	$full_exec = sprintf ("php %s/process_dns_full_slave.php %s 2>&1", dirname (__FILE__), $msg->body);
	
	exec ($full_exec, $output, $return_code);
	foreach ($output as $line) printf ("%s\n", $line);
	
	$msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
};

$callback_zone_master_slave_add = function ($msg) {
	$full_exec = sprintf ("php %s/process_dns_add_master_slave.php %s 2>&1", dirname (__FILE__), $msg->body);
	
	exec ($full_exec, $output, $return_code);
	foreach ($output as $line) printf ("%s\n", $line);
	
	$msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
};

$callback_zone_del = function ($msg) {
	$full_exec = sprintf ("php %s/process_dns_del_zone.php %s 2>&1", dirname (__FILE__), $msg->body);
	
	exec ($full_exec, $output, $return_code);
	foreach ($output as $line) printf ("%s\n", $line);
	
	$msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
};

$callback_record_add = function ($msg) {
	$full_exec = sprintf ("php %s/process_dns_record_add.php %s 2>&1", dirname (__FILE__), $msg->body);
	
	exec ($full_exec, $output, $return_code);
	foreach ($output as $line) printf ("%s\n", $line);
	
	$msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
};

$callback_record_del = function ($msg) {
	$full_exec = sprintf ("php %s/process_dns_record_del.php %s 2>&1", dirname (__FILE__), $msg->body);
	
	exec ($full_exec, $output, $return_code);
	foreach ($output as $line) printf ("%s\n", $line);
	
	$msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
};

$callback_record_update = function ($msg) {
	$full_exec = sprintf ("php %s/process_dns_record_update.php %s 2>&1", dirname (__FILE__), $msg->body);
	
	exec ($full_exec, $output, $return_code);
	foreach ($output as $line) printf ("%s\n", $line);
	
	$msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
};

$callback_zone_check_delegation = function ($msg) {
	$full_exec = sprintf ("php %s/process_dns_check_delegation.php %s 2>&1", dirname (__FILE__), $msg->body);
	
	exec ($full_exec, $output, $return_code);
	foreach ($output as $line) printf ("%s\n", $line);
	
	$msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
};

$channel->queue_declare ('dns_zone_add', false, true, false, false);
$channel->queue_declare ('dns_zone_slave_add', false, true, false, false);
$channel->queue_declare ('dns_record_add', false, true, false, false);
$channel->queue_declare ('dns_record_update', false, true, false, false);
$channel->queue_declare ('dns_record_del', false, true, false, false);
$channel->queue_declare ('dns_zone_check_delegation', false, true, false, false);
$channel->exchange_declare('dns_zone_del', 'fanout', false, false, false);
$channel->exchange_declare('dns_zone_master_slave_add', 'fanout', false, false, false);

$channel->basic_qos (null, 1, null);
/* El DNS Maestro procesa, las zonas agregadas maestras, mas todas las zonas esclavas, y agregar, quitar y actualizar registros */

$queue_name = Gatuf::config ('rndc_name', null);

if ($queue_name === null || $queue_name == '') {
	throw new Exception ('rndc_name undefined');
	
	return 1;
}

$type = Gatuf::config ('rndc_type', null);

if ($type != 'master' && $type != 'slave') {
	throw new Exception ('rndc_type not defined. Must be either master or slave');
	
	return 1;
}

/* Esta queue está conectada al exchange de quitar zonas
 * Tanto los maestros como los esclavos tienen que borrar sus zonas */
$channel->queue_declare ($queue_name.'_zone_del', false, true, false, false);
$channel->queue_bind ($queue_name.'_zone_del', 'dns_zone_del');
$channel->basic_consume ($queue_name.'_zone_del', '', false, false, false, false, $callback_zone_del);

if ($type == 'master') {
	/* Los DNS de tipo maestro solo manejan los mensajes principales */
	$channel->basic_consume ('dns_zone_add', '', false, false, false, false, $callback_zone_add_master);
	$channel->basic_consume ('dns_zone_slave_add', '', false, false, false, false, $callback_zone_slave_check_delegation);
	$channel->basic_consume ('dns_record_add', '', false, false, false, false, $callback_record_add);
	$channel->basic_consume ('dns_record_update', '', false, false, false, false, $callback_record_update);
	$channel->basic_consume ('dns_record_del', '', false, false, false, false, $callback_record_del);
	$channel->basic_consume ('dns_zone_check_delegation', '', false, false, false, false, $callback_zone_check_delegation);
} else {
	/* Los DNS esclavo solo manejan agregar zonas esclavas */
	$channel->queue_declare ($queue_name.'_slave_add', false, true, false, false);
	$channel->queue_bind ($queue_name.'_slave_add', 'dns_zone_master_slave_add');
	$channel->basic_consume ($queue_name.'_slave_add', '', false, false, false, false, $callback_zone_master_slave_add);
}

while (1) {
    $channel->wait();
}

$channel->close();
$connection->close();
