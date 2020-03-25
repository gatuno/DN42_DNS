#!/usr/bin/php
<?php

require dirname(__FILE__).'/../src/DNS42/conf/path.php';
require 'Gatuf.php';
Gatuf::start(dirname(__FILE__).'/../src/DNS42/conf/dns42.php');
Gatuf_Despachador::loadControllers(Gatuf::config('dns42_views'));

restore_error_handler ();

function run_test ($host, $dominio) {
	$opts = array ('nameservers' => array ($host));
	$resolver = new Net_DNS2_Resolver ($opts);
	
	/* Valores por defecto */
	$response = false;
	$full_axfr = 'skipped';
	$soa = '';
	$auth = 'skipped';
	$ns_list = array ();
	$parent_match = 'skipped';
	
	/* Buscar el registro SOA del dominio */
	try {
		$result = $resolver->query ($dominio->dominio, 'SOA');
	} catch (Net_DNS2_Exception $err) {
		$result = false;
	}
	
	if ($result !== false) {
		if ($result->header->aa == 1) {
			$auth = 'ok';
		} else {
			$auth = 'no';
		}
		
		if (count ($result->answer) != 0) {
			$soa = ((string) $result->answer[0]);
			$response = true;
		}
	}
	
	if ($auth == 'ok') {
		/* Intentar una transferencia full solo si es la autoridad de la zona */
		try {
			$result = $resolver->query ($dominio->dominio, 'AXFR');
		
			$full_axfr = 'allowed';
		} catch (Net_DNS2_Exception $err) {
			$full_axfr = 'ok';
		}
		
		/* Intentar descargar la lista de NS solo si hubo una respuesta autoritativa desde el servidor */
		try {
			$result = $resolver->query ($dominio->dominio, 'NS');
		} catch (Net_DNS2_Exception $err) {
			$result = false;
		}
	
		if ($result !== false) {
			foreach ($result->answer as $ns) {
				if (get_class ($ns) == 'Net_DNS2_RR_NS') {
					$ns_list[] = $ns->nsdname;
				}
			}
		
			sort ($ns_list);
		
			$ns_in_parent = array ();
			$ns_parent = $dominio->get_ns_list ();
			foreach ($ns_parent as $ns) {
				$ns_in_parent[] = $ns->get_server ()->nombre;
			}
		
			sort ($ns_in_parent);
		
			if ($ns_list === $ns_in_parent) {
				$parent_match = 'ok';
			} else {
				$parent_match = 'no';
			}
		}
	}
	return array ('axfr' => $full_axfr, 'auth' => $auth, 'parent_match' => $parent_match, 'ns_list' => $ns_list, 'soa' => $soa, 'response' => $response);
}

$checks = Gatuf::factory ('DNS42_NSCheck')->getList (array ('order' => 'prioridad ASC', 'filter' => 'estado = 0', 'nb' => 1));

$cont = 0;

while (count ($checks) > 0) {
	$check = $checks[0];
	$ret = $check->block_for_check ();
	
	if ($ret === false) {
		$checks = Gatuf::factory ('DNS42_NSCheck')->getList (array ('order' => 'prioridad ASC', 'filter' => 'estado = 0', 'nb' => 1));
		continue;
	}
	
	$ns = $check->get_ns ();
	$server = $ns->get_server ();
	$dominio = $ns->get_dominio ();
	
	if ($server->ipv4 != '') {
		$results = run_test ($server->ipv4, $dominio);
		if ($results['response'] == true) {
			$ns->response4 = 2;
		} else {
			$ns->response4 = 1;
		}
		
		if ($results['axfr'] == 'ok') {
			$ns->open_transfer4 = 2;
		} else if ($results['axfr'] == 'allowed') {
			$ns->open_transfer4 = 1;
		} else if ($results['axfr'] == 'skipped') {
			$ns->open_transfer4 = 3;
		}
		
		if ($results['auth'] == 'ok') {
			$ns->autoritative4 = 2;
		} else if ($results['auth'] == 'no') {
			$ns->autoritative4 = 1;
		} else if ($results['auth'] == 'skipped') {
			$ns->autoritative4 = 3;
		}
		
		if ($results['parent_match'] == 'ok') {
			$ns->parent_match4 = 2;
		} else if ($results['parent_match'] == 'no') {
			$ns->parent_match4 = 1;
		} else if ($results['parent_match'] == 'skipped') {
			$ns->parent_match4 = 3;
		}
		
		$ns->soa4 = $results['soa'];
		$ns->ns_list4 = implode (",", $results['ns_list']);
	}
	
	if ($server->ipv6 != '') {
		$results = run_test ($server->ipv6, $dominio);
		if ($results['response'] == true) {
			$ns->response6 = 2;
		} else {
			$ns->response6 = 1;
		}
		
		if ($results['axfr'] == 'ok') {
			$ns->open_transfer6 = 2;
		} else if ($results['axfr'] == 'allowed') {
			$ns->open_transfer6 = 1;
		} else if ($results['axfr'] == 'skipped') {
			$ns->open_transfer6 = 3;
		}
		
		if ($results['auth'] == 'ok') {
			$ns->autoritative6 = 2;
		} else if ($results['auth'] == 'no') {
			$ns->autoritative6 = 1;
		} else if ($results['auth'] == 'skipped') {
			$ns->autoritative6 = 3;
		}
		
		if ($results['parent_match'] == 'ok') {
			$ns->parent_match6 = 2;
		} else if ($results['parent_match'] == 'no') {
			$ns->parent_match6 = 1;
		} else if ($results['parent_match'] == 'skipped') {
			$ns->parent_match6 = 3;
		}
		
		$ns->soa6 = $results['soa'];
		$ns->ns_list6 = implode (",", $results['ns_list']);
	}
	
	$ns->update ();
	
	$check->delete ();
	
	$checks = Gatuf::factory ('DNS42_NSCheck')->getList (array ('order' => 'prioridad ASC', 'filter' => 'estado = 0', 'nb' => 1));
}
