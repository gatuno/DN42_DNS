#!/usr/bin/php
<?php

require dirname(__FILE__).'/../src/DNS42/conf/path.php';
require 'Gatuf.php';
Gatuf::start(dirname(__FILE__).'/../src/DNS42/conf/dns42.php');
Gatuf_Despachador::loadControllers(Gatuf::config('dns42_views'));

function getDirContents($dir, &$results = array()){
	$files = scandir($dir);

	foreach ($files as $key => $value) {
		if (is_dir ($value) || $value == '.' || $value == '..') continue;
		$path = realpath($dir.DIRECTORY_SEPARATOR.$value);
		$results[] = $path;
	}

	return $results;
}

$repo_folder = Gatuf::config ('dn42_registry_repo');

$files = getDirContents ($repo_folder.'/data/dns');

$all_active_ns = array ();
$all_active_domains = array ();

$pending_ns = array ();

foreach ($files as $file) {
	$fp = fopen ($file, "r+");
	
	if ($fp === false) continue;
	
	$data = array ('domain' => '', 'nserver' => array ());
	while ($line = stream_get_line($fp, 1024 * 1024, "\n")) {
		$toks = preg_split ("/[\s]+/", $line, -1, PREG_SPLIT_NO_EMPTY);
		
		if ($toks[0] == 'domain:') {
			$data['domain'] = $toks[1];
		} else if ($toks[0] == 'nserver:') {
			if (isset ($toks[2])) {
				$data['nserver'][] = $toks[1].' '.$toks[2];
			} else {
				$data['nserver'][] = $toks[1];
			}
		} else if ($toks[0] == 'source:') {
			$data['source'] = $toks[1];
		}
	}
	
	fclose($fp);
	
	if (!isset ($data['source']) || $data['source'] != 'DN42') {
		continue;
	}
	
	if ($data['domain'] == 'rzl') continue;
	if ($data['domain'] == 'hack') continue;
	
	/* Primero, crear los objetos servidor o revisar si ya existen */
	$all_ns_by_name = array ();
	foreach ($data['nserver'] as $ns) {
		$toks = explode (" ", $ns);
		
		$sql = new Gatuf_SQL ('nombre=%s', $toks[0]);
		$server = Gatuf::factory ('DNS42_Server')->getOne (array ('filter' => $sql->gen ()));
		
		if (count ($toks) > 1) {
			/* Tenemos nombre + IP, actualizar si es necesario */
			$ip = 0;
			if (filter_var ($toks[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
				$ip = 4;
			} else if (filter_var ($toks[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
				$ip = 6;
			}
			if ($server == null) {
				/* Crear */
				$server = new DNS42_Server ();
				$server->nombre = mb_strtolower ($toks[0]);
				
				if ($ip == 4) {
					$server->ipv4 = $toks[1];
				} else if ($ip == 6) {
					$server->ipv6 = $toks[1];
				}
				$server->estado = 0;
				
				$server->create ();
				
				$check = new DNS42_PingCheck ();
				$check->prioridad = 20;
				$check->server = $server;
				$check->create ();
			} else {
				/* Actualizar */
				$updated = false;
				if ($ip == 4) {
					if ($server->ipv4 != $toks[1]) $updated = true;
					$server->ipv4 = $toks[1];
				} else if ($ip == 6) {
					if ($server->ipv6 != $toks[1]) $updated = true;
					$server->ipv6 = $toks[1];
				}
				
				$server->update ();
				
				if ($updated) {
					$sql = new Gatuf_SQL ('server=%s', $server->id);
					$check = Gatuf::factory ('DNS42_PingCheck')->getOne ($sql->gen ());
				
					if (null === $check) {
						$check = new DNS42_PingCheck ();
						$check->prioridad = 40;
						$check->server = $server;
						$check->create ();
					} else {
						$check->prioridad = 40;
					
						$check->update ();
					}
				}
			}
		}
		/* En caso contrario, es solo el nombre sin IP, es una delegación fuera de zona, ignorar, solo asociar con el dominio */
		
		if (!in_array ($toks[0], $all_ns_by_name)) {
			$all_ns_by_name[] = $toks[0];
		}
	}
	
	/* Buscar y crear el dominio si es necesario */
	$sql = new Gatuf_SQL ('dominio=%s', $data['domain']);
	$dominio = Gatuf::factory ('DNS42_Domain')->getOne (array ('filter' => $sql->gen ()));
	
	if ($dominio === null) {
		$dominio = new DNS42_Domain ();
		$dominio->dominio = mb_strtolower ($data['domain']);
		
		$dominio->create ();
	}
	
	/* Quitar los ns no asociados */
	$ns_assoc = $dominio->get_ns_list ();
	foreach ($ns_assoc as $ns) {
		
		$server = $ns->get_server ();
		$key = array_search ($server->nombre, $all_ns_by_name);
		if ($key !== false) {
			if (!isset ($all_active_ns[$server->nombre])) $all_active_ns[$server->nombre] = 1;
			/* No es necesario asociar este ns, ya lo está */
			/* Quitar del arreglo */
			unset ($all_ns_by_name [$key]);
		} else {
			/* ¿Está asociado en BD y no existe?, desasociar */
			$ns->delete ();
		}
	}
	
	/* Recorrer los ns faltantes para asociarlos */
	foreach ($all_ns_by_name as $new_ns) {
		if (!isset ($all_active_ns[$new_ns])) $all_active_ns[$new_ns] = 1;
		$sql = new Gatuf_SQL ('nombre=%s', $new_ns);
		$server = Gatuf::factory ('DNS42_Server')->getOne (array ('filter' => $sql->gen ()));
		
		if ($server === null) {
			//echo "--- Error, $new_ns no existe ---\n";
			$pending_ns[$dominio->dominio] = $new_ns;
		} else {
			$ns = new DNS42_NameServer ();
			$ns->server = $server;
			$ns->dominio = $dominio;
			
			$ns->create ();
			
			$check = new DNS42_NSCheck ();
			$check->ns = $ns;
			$check->prioridad = 20;
			$check->create ();
		}
	}
	
	if (!isset ($all_active_domains [$dominio->dominio])) $all_active_domains[$dominio->dominio] = 1;
}

foreach ($pending_ns as $domain => $new_ns) {
	$sql = new Gatuf_SQL ('nombre=%s', $new_ns);
	$server = Gatuf::factory ('DNS42_Server')->getOne (array ('filter' => $sql->gen ()));
	
	$sql = new Gatuf_SQL ('dominio=%s', $domain);
	$dominio = Gatuf::factory ('DNS42_Domain')->getOne (array ('filter' => $sql->gen ()));
	
	if ($server === null) {
		echo "--- Error, NS=$new_ns no existe ---\n";
	} else if ($dominio === null) {
		echo "--- Error, DOMAIN=$domain no existe ---\n";
	} else {
		$ns = new DNS42_NameServer ();
		$ns->server = $server;
		$ns->dominio = $dominio;
		
		$ns->create ();
		
		$check = new DNS42_NSCheck ();
		$check->ns = $ns;
		$check->prioridad = 20;
		$check->create ();
	}
}
/* TODO: Recorrer todos los dominios y buscar cuáles no están activos */

$domains = Gatuf::factory ('DNS42_Domain')->getList ();

foreach ($domains as $domain) {
	if (isset ($all_active_domains [$domain->dominio]) && $all_active_domains [$domain->dominio] == 1) continue;
	
	$nss = $domain->get_ns_list ();
	
	foreach ($nss as $ns) {
		$ns->delete ();
	}
}

$servers = Gatuf::factory ('DNS42_Server')->getList ();
foreach ($servers as $server) {
	$ns_count = $server->get_domains_list (array ('count' => true));
	
	if ($ns_count == 0) {
		/* Este servidor ya no se usa, eliminar */
		$server->delete ();
	}
}
