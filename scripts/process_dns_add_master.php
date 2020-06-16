#!/usr/bin/php
<?php

require dirname(__FILE__).'/../src/DNS42/conf/path.php';
require 'Gatuf.php';
Gatuf::start(dirname(__FILE__).'/../src/DNS42/conf/dns42.php');
Gatuf_Despachador::loadControllers(Gatuf::config('dns42_views'));

restore_error_handler ();

require 'process_dns_common.php';

function zone_add_master ($managed_domain_id) {
	$managed = new DNS42_ManagedDomain ();
	
	if (false === $managed->get ($managed_domain_id)) {
		return false;
	}
	
	if (!$managed->maestra) {
		/* Si no es zona maestra, ignorar */
		return false;
	}
	
	/* Ir a cada nivel padre, preguntando si tengo la delegación, hasta que quede solo 1 elemento */
	$parent_domain = $managed->dominio;
	$good_delegation = false;
	
	while (1) {
		/* Primer tarea, revisar que los NS primarios del padre hayan sido delegados a mi */
		$toks = explode (".", $parent_domain);
		array_shift ($toks);
		if (count ($toks) == 0) break;
		$parent_domain = implode (".", $toks);
	
		/* Conseguir los NS de este dominio */
		$resolver = new Net_DNS2_Resolver ();
		
		try {
			$result = $resolver->query ($parent_domain.".", 'NS');
		} catch (Net_DNS2_Exception $err) {
			$result = false;
		}
	
		if (false === $result) {
			/* ¿No pude conseguir los NS del padre? Raro */
			continue;
		}
	
		$list_ips = array ();
		foreach ($result->answer as $parent_ns) {
			if (get_class ($parent_ns) != 'Net_DNS2_RR_NS') continue;
			try {
				$result2 = $resolver->query ($parent_ns->nsdname, 'A');
			} catch (Net_DNS2_Exception $err) {
				$result2 = false;
			}
		
			if ($result2 !== false) {
				foreach ($result2->answer as $a) {
					$list_ips[] = $a->address;
				}
			}
		
			try {
				$result2 = $resolver->query ($parent_ns->nsdname, 'AAAA');
			} catch (Net_DNS2_Exception $err) {
				$result2 = false;
			}
		
			if ($result2 !== false) {
				foreach ($result2->answer as $aaaa) {
					$list_ips[] = $aaaa->address;
				}
			}
		}
		
		foreach ($list_ips as $ip) {
			$opts = array ('nameservers' => array ($ip));
			$resolver2 = new Net_DNS2_Resolver ($opts);
		
			try {
				$result2 = $resolver2->query ($managed->dominio, 'NS');
			} catch (Net_DNS2_Exception $err) {
				$result2 = false;
			}
			if ($result2 === false) continue;
		
			foreach ($result2->answer as $ns) {
				if (get_class ($ns) != 'Net_DNS2_RR_NS') continue;
				if ($ns->nsdname == 'ns1.gatuno.dn42') {
					$good_delegation = true;
					break;
				}
			}
		
			foreach ($result2->authority as $ns) {
				if (get_class ($ns) != 'Net_DNS2_RR_NS') continue;
				if ($ns->nsdname == 'ns1.gatuno.dn42') {
					$good_delegation = true;
					break;
				}
			}
		
			if ($good_delegation) break;
		}
		if ($good_delegation) break;
	}
	
	var_dump ("Delegacion");
	var_dump ($good_delegation);
	if ($good_delegation) {
		/* Crear el archivo vacio */
		$folder = "/etc/bind/dynamic_zones";
		$created = create_empty_zone ($folder, $managed->dominio);
		
		if ($created == false) {
			$managed->delegacion = 3;
			$managed->update ();
			
			return false;
		}
		
		/* Crear la zona dinámica en el master */
		$key = Gatuf::config ('rndc_update_key');
		$server = Gatuf::config ('rndc_update_server');
		$port = Gatuf::config ('rndc_update_port');
		
		$update_key = $managed->get_key ();
		
		$full_exec = sprintf ("/usr/sbin/rndc -k \"%s\" -s \"%s\" -p \"%s\" addzone \"%s\" '{type master; file \"%s/%s\"; allow-update { key %s; }; notify yes; also-notify { slave_notifies; }; };' 2>&1", $key, $server, $port, $managed->dominio, $folder, $managed->dominio, $update_key->nombre);
		
		exec ($full_exec, $output, $return_code);
		
		if ($return_code == 0) {
			$records = $managed->get_records_list ();
			
			/* Crear todos los records correspondientes */
			$managed->delegacion = 2;
			$managed->update ();
			
			/* Ahora, crear todos los registros pendientes */
			foreach ($records as $r) {
				$delegar = DNS42_RMQ::send_add_record ($r);
			}
			
			/* Ahora, notificar a los esclavos que por favor creen la zona dns esclava */
			DNS42_RMQ::send_notify_slaves_from_master ($managed);
		} else {
			$managed->delegacion = 4;
			$managed->update ();
		}
	} else {
		$managed->delegacion = 1;
		$managed->update ();
	}
	
	return true;
}

if (!isset ($argv[1])) {
	printf ("Argument managed_domain_id required\n");
	
	return 1;
}

$res = zone_add_master ($argv[1]);

return 0;
