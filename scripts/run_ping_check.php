#!/usr/bin/php
<?php

require dirname(__FILE__).'/../src/DNS42/conf/path.php';
require 'Gatuf.php';
Gatuf::start(dirname(__FILE__).'/../src/DNS42/conf/dns42.php');
Gatuf_Despachador::loadControllers(Gatuf::config('dns42_views'));

restore_error_handler ();

$checks = Gatuf::factory ('DNS42_PingCheck')->getList (array ('order' => 'prioridad ASC', 'filter' => 'estado = 0', 'nb' => 1));

$check_method = Gatuf::config ('dn42_ping_method');

while (count ($checks) > 0) {
	$check = $checks[0];
	$ret = $check->block_for_check ();
	
	if ($ret === false) {
		$checks = Gatuf::factory ('DNS42_PingCheck')->getList (array ('order' => 'prioridad ASC', 'filter' => 'estado = 0', 'nb' => 1));
		continue;
	}
	
	$server = $check->get_server ();
	
	if ($server->ipv4 == '' && $server->ipv6 == '') {
		$check->delete ();
		continue;
	}
	
	$ping4 = '';
	if ($server->ipv4 != '') {
		$ping = new DNS42_Ping ($server->ipv4, 255, 3, 4);
		
		$lat = $ping->ping ($check_method);
		
		if ($lat === false) {
			$ping4 = 'failed';
		} else {
			$ping4 = $lat;
		}
	}
	
	$ping6 = '';
	if ($server->ipv6 != '') {
		$ping = new DNS42_Ping ($server->ipv6, 255, 3, 6);
		
		$lat = $ping->ping ($check_method);
		
		if ($lat === false) {
			$ping6 = 'failed';
		} else {
			$ping6 = $lat;
		}
	}
	
	$server->ping4 = $ping4;
	$server->ping6 = $ping6;
	
	$server->update ();
	
	$check->delete ();
	
	$checks = Gatuf::factory ('DNS42_PingCheck')->getList (array ('order' => 'prioridad ASC', 'filter' => 'estado = 0', 'nb' => 1));
}
