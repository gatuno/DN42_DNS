#!/usr/bin/php
<?php

require dirname(__FILE__).'/../src/DNS42/conf/path.php';
require 'Gatuf.php';
Gatuf::start(dirname(__FILE__).'/../src/DNS42/conf/dns42.php');
Gatuf_Despachador::loadControllers(Gatuf::config('dns42_views'));

restore_error_handler ();

require 'process_dns_common.php';

function record_add ($record_id) {
	$record = new DNS42_Record ();
	
	if (false === $record->get ($record_id)) {
		// Cerrar la base de datos para evitar desconexiones por timeout
		return false;
	}
	
	if ($record->locked == true) {
		// Cerrar la base de datos para evitar desconexiones por timeout
		return false;
	}
	
	$managed = $record->get_dominio ();
	$key = $managed->get_key ();
	
	$server = Gatuf::config ('rndc_update_server');
	
	$opts = array ('nameservers' => array ($server));
	$updater = new Net_DNS2_Updater ($managed->dominio, $opts);
	
	switch ($record->type) {
		case 'A':
		case 'AAAA':
		case 'CNAME':
		case 'MX':
		case 'NS':
		case 'TXT':
		case 'PTR':
		case 'SRV':
			$line = sprintf ("%s %s IN %s %s", $record->name, $record->ttl, $record->type, $record->rdata);
			break;
		default:
			// Cerrar la base de datos para evitar desconexiones por timeout
			var_dump ("Unsupported record type '".$record->type."' and data: '".$record->rdata."'");
			
			return false;
			break;
	}
	$rr = Net_DNS2_RR::fromString ($line);
	var_dump ("Going to update");
	var_dump ($rr);
	$updater->add ($rr);
	
	$updater->signTSIG ($key->nombre, $key->secret, $key->algo);
	
	$response = $updater->update();
	var_dump ("Update Response");
	var_dump ($response);
	
	// Cerrar la base de datos para evitar desconexiones por timeout
	return true;
}

if (!isset ($argv[1])) {
	printf ("Argument record_id required\n");
	
	return 1;
}

$res = record_add ($argv[1]);

return 0;
