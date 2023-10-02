#!/usr/bin/php
<?php

require 'config.php';
require 'process_dns_common.php';

function zone_del ($old_managed_domain_name) {
	/* Crear la zona dinámica en el master */
	$key = Gatuf::config ('rndc_update_key');
	$server = Gatuf::config ('rndc_update_server');
	$port = Gatuf::config ('rndc_update_port');
	
	$full_exec = sprintf ("/usr/sbin/rndc -k \"%s\" -s \"%s\" -p \"%s\" delzone '%s' 2>&1", $key, $server, $port, $old_managed_domain_name);
	
	exec ($full_exec, $output, $return_code);
	
	if ($return_code != 0) {
		/* FIXME: Tenemos una zona pendiente. Revisar que hacer en este caso */
		printf ("Could not delete zone %s by using rndc.\n", $old_managed_domain_name);
		printf ("Exec: %s\n", $full_exec);
		printf ("Output:\n");
		var_dump ($output);
		printf ("Return code: %s\n", $return_code);
	}
	
	/* Checar si esta es una zona inversa, si lo es, el nombre de archivo cambia un poco */
	if (strpos ($old_managed_domain_name, 'arpa') !== false) {
		$basename = str_replace ('/', '_', $old_managed_domain_name);
	} else {
		$basename = $old_managed_domain_name;
	}
	
	/* Borrar el archivo correspondiente */
	$folder = "/etc/bind/dynamic_zones";
	$file_name = sprintf ("%s/%s", $folder, $basename);
	
	$deleted = @unlink ($file_name);
	
	if ($deleted === false) {
		/* FIXME: Otro problema, no pude eliminar el archivo */
		var_dump ("Could not delete $file_name");
	}
	
	$file_name = sprintf ("%s/%s.jnl", $folder, $basename);
	
	$deleted = @unlink ($file_name);
	
	if ($deleted === false) {
		/* FIXME: Otro problema, no pude eliminar el archivo */
		var_dump ("Could not delete $file_name");
	}
	
	return true;
}

if (!isset ($argv[1])) {
	printf ("Argument managed_domain_name required\n");
	
	return 1;
}

$res = zone_del ($argv[1]);

return 0;
