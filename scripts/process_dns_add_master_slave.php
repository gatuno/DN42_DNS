#!/usr/bin/php
<?php

require dirname(__FILE__).'/../src/DNS42/conf/path.php';
require 'Gatuf.php';
Gatuf::start(dirname(__FILE__).'/../src/DNS42/conf/dns42.php');
Gatuf_Despachador::loadControllers(Gatuf::config('dns42_views'));

restore_error_handler ();

require 'process_dns_common.php';

function zone_add_master_slave ($managed_domain_id) {
	$managed = new DNS42_ManagedDomain ();
	
	if (false === $managed->get ($managed_domain_id)) {
		return false;
	}
	
	if ($managed->maestra) {
		/* Si es una zona maestra, ignorar */
		return false;
	}
	
	/* Crear el archivo vacio */
	$folder = "/etc/bind/dynamic_zones";
	$created = create_empty_zone ($folder, $managed->dominio);
	
	if ($created == false) {
		printf ("Error, no pude crear el archivo de zona vacio\n");
		return false;
	}
	
	/* Crear la zona dinámica en el master */
	$key = Gatuf::config ('rndc_update_key');
	$server = Gatuf::config ('rndc_update_server');
	$port = Gatuf::config ('rndc_update_port');
	
	$full_exec = sprintf ("/usr/sbin/rndc -k \"%s\" -s \"%s\" -p \"%s\" addzone \"%s\" '{type slave; file \"%s/%s\"; masters { 172.22.200.9; }; };' 2>&1", $key, $server, $port, $managed->dominio, $folder, $managed->dominio);
	
	exec ($full_exec, $output, $return_code);
	
	if ($return_code == 0) {
		return true;
	} else {
		return false;
	}
}

if (!isset ($argv[1])) {
	printf ("Argument managed_domain_id required\n");
	
	return 1;
}

$res = zone_add_master_slave ($argv[1]);

return 0;
