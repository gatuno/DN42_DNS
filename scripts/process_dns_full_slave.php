#!/usr/bin/php
<?php

require dirname(__FILE__).'/../src/DNS42/conf/path.php';
require 'Gatuf.php';
Gatuf::start(dirname(__FILE__).'/../src/DNS42/conf/dns42.php');
Gatuf_Despachador::loadControllers(Gatuf::config('dns42_views'));

restore_error_handler ();

require 'process_dns_common.php';

function zone_slave_check_delegation ($managed_domain_id) {
	$managed = new DNS42_ManagedDomain ();
	
	if (false === $managed->get ($managed_domain_id)) {
		// Cerrar la base de datos para evitar desconexiones por timeout
		return false;
	}
	
	if ($managed->maestra) {
		// Cerrar la base de datos para evitar desconexiones por timeout
		return false;
	}
	/* Ir a cada nivel padre, preguntando si tengo la delegación, hasta que quede solo 1 elemento */
	$parent_domain = $managed->dominio;
	$good_delegation = false;
	
	/* TODO: Decidir cómo probar la transferencia AXFR */
	
	// Cerrar la base de datos para evitar desconexiones por timeout
	return true;
}

if (!isset ($argv[1])) {
	printf ("Argument managed_domain_id required\n");
	
	return 1;
}

$res = zone_slave_check_delegation ($argv[1]);

return 0;
