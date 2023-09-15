#!/usr/bin/php
<?php

require dirname(__FILE__).'/../src/DNS42/conf/path.php';
require 'Gatuf.php';
Gatuf::start(dirname(__FILE__).'/../src/DNS42/conf/dns42.php');
Gatuf_Despachador::loadControllers(Gatuf::config('dns42_views'));

restore_error_handler ();

require 'process_dns_common.php';

function zone_check_delegation ($managed_domain_id) {
	$managed = new DNS42_ManagedDomain ();
	
	if (false === $managed->get ($managed_domain_id)) {
		return false;
	}
	
	if ($managed->delegacion != 6) {
		/* Si no es una zona pendiente de revisar delegacion, ignorar */
		return false;
	}
	
	/* TODO: Ejecutar aquí la validación de la delegación */
	return false;
}

if (!isset ($argv[1])) {
	printf ("Argument managed_domain_id required\n");
	
	return 1;
}

$res = zone_check_delegation ($argv[1]);

return 0;
