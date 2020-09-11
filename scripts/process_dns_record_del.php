#!/usr/bin/php
<?php

require dirname(__FILE__).'/../src/DNS42/conf/path.php';
require 'Gatuf.php';
Gatuf::start(dirname(__FILE__).'/../src/DNS42/conf/dns42.php');
Gatuf_Despachador::loadControllers(Gatuf::config('dns42_views'));

restore_error_handler ();

require 'process_dns_common.php';

function record_del ($managed_name, $record_name, $record_type, $record_value) {
	$managed = new DNS42_ManagedDomain ();
	
	if (false === $managed->get ($managed_name)) {
		// Cerrar la base de datos para evitar desconexiones por timeout
		return false;
	}
	
	$key = $managed->get_key ();
	
	$server = Gatuf::config ('rndc_update_server');
	
	$opts = array ('nameservers' => array ($server));
	$updater = new Net_DNS2_Updater ($managed->dominio, $opts);
	
	switch ($record_type) {
		case 'A':
		case 'AAAA':
		case 'CNAME':
		case 'MX':
		case 'NS':
		case 'TXT':
		case 'PTR':
			$line = sprintf ("%s 300 IN %s %s", $record_name, $record_type, $record_value);
			break;
		default:
			// Cerrar la base de datos para evitar desconexiones por timeout
			var_dump ("Unsupported record type '".$record->type."' and data: '".$record->rdata."'");
			
			return false;
			break;
	}
	
	$rr = Net_DNS2_RR::fromString ($line);
	$updater->delete ($rr);
	
	$updater->signTSIG ($key->nombre, $key->secret, $key->algo);
	
	$response = $updater->update();
	var_dump ("Update Response");
	var_dump ($response);
	
	// Cerrar la base de datos para evitar desconexiones por timeout
	return true;
}

if (!isset ($argv[1]) || !isset ($argv[2]) || !isset ($argv[3]) || !isset ($argv[4])) {
	printf ("Argument domain record type value required\n");
	
	return 1;
}

$res = record_del ($argv[1], $argv[2], $argv[3], $argv[4]);

return 0;
