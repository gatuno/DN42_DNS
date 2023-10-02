#!/usr/bin/php
<?php

require 'config.php';
require 'process_dns_common.php';

function check_reverse ($managed) {
	$parent_prefix = $managed->prefix;
	$parent_domain = $managed->dominio;
	$prefixes = array (
		array (
			'type' => 4,
			'network' => '172.20.0.0/14',
			'nserver' => array ('172.20.129.1', 'fd42:4242:2601:ac53::1', 'fda6:2474:15a4::54', '172.20.1.254', 'fd42:5d71:219:1:216:3eff:fe1e:22d6', '172.20.14.34', 'fdcf:8538:9ad5:1111::2'),
		),
		array (
			'type' => 6,
			'network' => 'fd00::/8',
			'nserver' => array ('172.20.129.1', 'fd42:4242:2601:ac53::1', 'fda6:2474:15a4::54', '172.20.1.254', 'fd42:5d71:219:1:216:3eff:fe1e:22d6', '172.20.14.34', 'fdcf:8538:9ad5:1111::2'),
		),
	);
	
	$toks = explode ('_', $parent_prefix);
	
	if (count ($toks) != 2) {
		return false;
	}
	
	$network = $toks[0];
	$mask = $toks[1];
	
	if (filter_var ($network, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) == true) {
		$type = 4;
	} else if (filter_var ($network, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) == true) {
		$type = 6;
	}
	
	$found = false;
	$ns_list = null;
	foreach ($prefixes as $block) {
		if ($block['type'] != $type) continue;
		
		if ($type == 4) {
			$found = DNS42_Utils::checkIp4 ($network, $block['network']);
		} else if ($type == 6) {
			$found = DNS42_Utils::checkIp6 ($network, $block['network']);
		}
		
		if ($found) {
			$ns_list = $block['nserver'];
			break;
		}
	}
	
	if ($found == false) {
		return false;
	}
	
	
	/* Recorrer cada nameserver, preguntando por la delegación */
	foreach ($ns_list as $ns) {
		/* Conseguir los NS de esta zona inversa */
		$resolver = new Net_DNS2_Resolver (array ('nameservers' => array ($ns)));
		
		try {
			$result = $resolver->query ($parent_domain, 'NS');
		} catch (Net_DNS2_Exception $err) {
			$result = false;
		}
		
		if (false === $result) {
			/* ¿No pude conseguir los NS del padre? Raro */
			continue;
		}
		
		foreach ($result->answer as $ns) {
			if (get_class ($ns) != 'Net_DNS2_RR_NS') continue;
			if ($ns->nsdname == 'ns1.gatuno.dn42') {
				return true;
			}
		}
	
		foreach ($result->authority as $ns) {
			if (get_class ($ns) != 'Net_DNS2_RR_NS') continue;
			if ($ns->nsdname == 'ns1.gatuno.dn42') {
				return true;
			}
		}
	}
	
	return false;
}

function check_master ($managed) {
	$parent_domain = $managed->dominio;
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
					return true;
				}
			}
		
			foreach ($result2->authority as $ns) {
				if (get_class ($ns) != 'Net_DNS2_RR_NS') continue;
				if ($ns->nsdname == 'ns1.gatuno.dn42') {
					return true;
				}
			}
		
		}
	}
	
	return false;
}

function zone_add_master ($managed_domain_id) {
	$managed = new DNS42_ManagedDomain ();
	$is_dn42_zone = false;
	
	if (false === $managed->get ($managed_domain_id)) {
		return false;
	}
	
	if (!$managed->maestra) {
		/* Si no es zona maestra, ignorar */
		return false;
	}
	
	/* Ir a cada nivel padre, preguntando si tengo la delegación, hasta que quede solo 1 elemento */
	$good_delegation = false;
	
	if ($managed->reversa) {
		$good_delegation = check_reverse ($managed);
		
		/* TODO: Poner aquí la excepcion para la red de DN42 */
		$is_dn42_zone = true;
	} else {
		$good_delegation = check_master ($managed);
		
		/* Si el dominio termina en .dn42 y la delegacion fué fallida, omitir y permitir que la zona sea creada */
		if ($good_delegation == false) {
			$dn42_domain = ".dn42";
			$length = strlen ($dn42_domain);
			if (substr ($managed->dominio, -$length) == $dn42_domain) {
				$is_dn42_zone = true;
			}
		}
	}
	
	if ($good_delegation || $is_dn42_zone) {
		/* Recuperar la IP del master */
		$masters = Gatuf::config ('rndc_master', array ());
		if (count ($masters) == 0) {
			throw new Exception (__('Configuration Error. There should be only 1 master'));
		}
		
		$master_name = array_key_first ($masters);
		
		/* Crear el archivo vacio */
		$folder = "/etc/bind/dynamic_zones";
		$created = create_empty_zone ($folder, $managed->dominio, $master_name);
		
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
		
		if ($managed->reversa) {
			$filename = str_replace ('/', '_', $managed->dominio);
		} else {
			$filename = $managed->dominio;
		}
		
		$slaves = Gatuf::config ('rndc_slaves', array ());
		$notify_ips = '';
		foreach ($slaves as $slave_name => $slave_ip) {
			$notify_ips .= $slave_ip.'; ';
		}
		
		if (strpos ($managed->dominio, '/') !== false) {
			$full_exec = sprintf ("/usr/sbin/rndc -k \"%s\" -s \"%s\" -p \"%s\" addzone '\"%s\"' '{type master; file \"%s/%s\"; allow-update { key %s; }; notify explicit; also-notify { %s }; };' 2>&1", $key, $server, $port, $managed->dominio, $folder, $filename, $update_key->nombre, $notify_ips);
		} else {
			$full_exec = sprintf ("/usr/sbin/rndc -k \"%s\" -s \"%s\" -p \"%s\" addzone \"%s\" '{type master; file \"%s/%s\"; allow-update { key %s; }; notify explicit; also-notify { %s }; };' 2>&1", $key, $server, $port, $managed->dominio, $folder, $filename, $update_key->nombre, $notify_ips);
		}
		
		exec ($full_exec, $output, $return_code);
		
		if ($return_code == 0) {
			$records = $managed->get_records_list ();
			
			/* Crear todos los records correspondientes */
			if ($good_delegation == false) {
				$managed->delegacion = 6;
			} else {
				$managed->delegacion = 2;
			}
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
