#!/usr/bin/php
<?php

require dirname(__FILE__).'/../src/DNS42/conf/path.php';
require 'Gatuf.php';
Gatuf::start(dirname(__FILE__).'/../src/DNS42/conf/dns42.php');
Gatuf_Despachador::loadControllers(Gatuf::config('dns42_views'));

$nss = Gatuf::factory ('DNS42_NameServer')->getList ();

foreach ($nss as $ns) {
	$check = $ns->get_ping_checks_list ();
	
	if (count ($check) > 0) continue;
	
	if ($ns->ipv4 != '' || $ns->ipv6 != '') {
		$check = new DNS42_PingCheck ();
		
		$check->server = $ns;
		$check->prioridad = 110;
		$check->estado = 0;
		$check->create ();
	}
}
