#!/usr/bin/php
<?php

require dirname(__FILE__).'/../src/DNS42/conf/path.php';
require 'Gatuf.php';
Gatuf::start(dirname(__FILE__).'/../src/DNS42/conf/dns42.php');
Gatuf_Despachador::loadControllers(Gatuf::config('dns42_views'));

$servers = Gatuf::factory ('DNS42_Server')->getList ();

foreach ($servers as $server) {
	$check = $server->get_ping_checks_list ();
	
	if (count ($check) > 0) continue;
	
	if ($server->ipv4 == '' && $server->ipv6 == '') continue;
	
	$prio = 100;
	
	if ($server->ping4 == '' || $server->ping6 == '') {
		/* Nuevo servidor, dar prioridad al ping */
		$prio = 80;
	}
	
	if ($server->ping4 == 'failed' || $server->ping6 == 'failed') {
		$prio = 120;
	}
	
	$check = new DNS42_PingCheck ();
	
	$check->server = $server;
	$check->prioridad = $prio;
	$check->estado = 0;
	$check->create ();
}
