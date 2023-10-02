#!/usr/bin/php
<?php

require 'config.php';
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
			printf ("Unsupported record type '%s' and data: '%s'\n", $record->type, $record->rdata);
			
			return false;
			break;
	}
	$rr = Net_DNS2_RR::fromString ($line);
	printf ("Going to create a new record\n");
	//var_dump ($rr);
	$updater->add ($rr);
	
	$updater->signTSIG ($key->nombre, $key->secret, $key->algo);
	
	$response = $updater->update();
	if ($response == true) {
		printf ("Successful record added\n");
	} else {
		printf ("Failed to add record\n");
	}
	
	if ($response == true) {
		/* Actualizar el registro SOA del dominio, porque ya cambió el serial */
		update_soa_from_master ($managed);
	}
	// Cerrar la base de datos para evitar desconexiones por timeout
	return true;
}

if (!isset ($argv[1])) {
	printf ("Argument record_id required\n");
	
	return 1;
}

$res = record_add ($argv[1]);

return 0;
