#!/usr/bin/php
<?php

require dirname(__FILE__).'/../src/DNS42/conf/path.php';
require 'Gatuf.php';
Gatuf::start(dirname(__FILE__).'/../src/DNS42/conf/dns42.php');
Gatuf_Despachador::loadControllers(Gatuf::config('dns42_views'));

restore_error_handler ();

require 'process_dns_common.php';

function record_update ($record_id, $old_record_name, $old_record_rdata) {
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
		case 'PTR':
		case 'SRV':
			$line = sprintf ("%s %s IN %s %s", $record->name, $record->ttl, $record->type, $record->rdata);
			$old_record = sprintf ("%s 0 IN %s %s", $old_record_name, $record->type, $old_record_rdata);
			break;
		case 'TXT':
			$line = sprintf ("%s %s IN %s %s", $record->name, $record->ttl, $record->type, $record->rdata);
			$old_record = sprintf ("%s 0 IN %s \"%s\"", $old_record_name, $record->type, $old_record_rdata);
			break;
		default:
			// Cerrar la base de datos para evitar desconexiones por timeout
			printf ("Unsupported record type '%s' and data: '%s'\n", $record->type, $record->rdata);
			
			return false;
			break;
	}
	
	$old_rr = Net_DNS2_RR::fromString ($old_record);
	
	printf ("Going to delete a record (to do an update)\n");
	$updater->delete ($old_rr);
	
	$updater->signTSIG ($key->nombre, $key->secret, $key->algo);
	
	$response = $updater->update();
	if ($response == true) {
		printf ("Successful record deletion (to do an update)\n");
	} else {
		printf ("Failed to delete record (to do an update)\n");
		
		return false;
	}
	
	$updater = new Net_DNS2_Updater ($managed->dominio, $opts);
	$rr = Net_DNS2_RR::fromString ($line);
	
	printf ("Going to re-create a record (for an update)\n");
	$updater->add ($rr);
	
	$updater->signTSIG ($key->nombre, $key->secret, $key->algo);
	
	$response |= $updater->update();
	if ($response == true) {
		printf ("Successful record added (for an update)\n");
	} else {
		printf ("Failed to add record (for an update)\n");
	}
	
	if ($response == true) {
		/* Actualizar el registro SOA del dominio, porque ya cambió el serial */
		update_soa_from_master ($managed);
	}
	
	// Cerrar la base de datos para evitar desconexiones por timeout
	return true;
}

if (!isset ($argv[1]) || !isset ($argv[2]) || !isset ($argv[3])) {
	printf ("Argument domain record type value required\n");
	
	return 1;
}

$res = record_update ($argv[1], $argv[2], $argv[3]);

return 0;
