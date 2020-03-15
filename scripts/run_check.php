#!/usr/bin/php
<?php

require dirname(__FILE__).'/../src/DNS42/conf/path.php';
require 'Gatuf.php';
Gatuf::start(dirname(__FILE__).'/../src/DNS42/conf/dns42.php');
Gatuf_Despachador::loadControllers(Gatuf::config('dns42_views'));

$checks = Gatuf::factory ('DNS42_PingCheck')->getList (array ('order' => 'prioridad ASC', 'filter' => 'estado = 0'));

$cont = 0;

foreach ($checks as $check) {
	$ret = $check->block_for_check ();
	
	if ($ret === false) continue;
	
	$ns = $check->get_server ();
	
	if ($ns->ipv4 == '' || $ns->ipv6 == '') {
		$check->delete ();
		continue;
	}
	
	$ping4 = '';
	if ($ns->ipv4 != '') {
		$ping = new DNS42_Ping ($ns->ipv4, 255, 3, 4);
		
		$lat = $ping->ping ('socket');
		
		if ($lat === false) {
			$ping4 = 'failed';
		} else {
			$ping4 = $lat;
		}
	}
	
	$ping6 = '';
	if ($ns->ipv6 != '') {
		$ping = new DNS42_Ping ($ns->ipv6, 255, 3, 6);
		
		$lat = $ping->ping ('socket');
		
		if ($lat === false) {
			$ping6 = 'failed';
		} else {
			$ping6 = $lat;
		}
	}
	
	$ns->ping4 = $ping4;
	$ns->ping6 = $ping6;
	if ($ns->ipv4 != '' && $ns->ipv6 != '') {
		/* El color verde se logra con los dos pings en verde */
		if ($ping4 == 'failed' && $ping6 == 'failed') {
			$ns->estado = 1; /* Rojo */
		} else if ($ping4 == 'failed') {
			$ns->estado = 2; /* Advertencia */
		} else if ($ping6 == 'failed') {
			$ns->estado = 2; /* Advertencia */
		} else {
			$ns->estado = 3;
		}
	} else if ($ns->ipv4 != '') {
		/* El verde se logra solo con el ping de ipv4 */
		if ($ping4 == 'failed') {
			$ns->estado = 1;
		} else {
			$ns->estado = 3;
		}
	} else if ($ns->ipv6 != '') {
		/* El verde se logra solo con el ping de ipv6 */
		if ($ping6 == 'failed') {
			$ns->estado = 1;
		} else {
			$ns->estado = 3;
		}
	}
	
	$ns->update ();
	
	$check->delete ();
	
	$cont++;
	
	if ($cont > 10) {
		break;
	}
}
