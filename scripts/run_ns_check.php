#!/usr/bin/php
<?php

require dirname(__FILE__).'/../src/DNS42/conf/path.php';
require 'Gatuf.php';
Gatuf::start(dirname(__FILE__).'/../src/DNS42/conf/dns42.php');
Gatuf_Despachador::loadControllers(Gatuf::config('dns42_views'));

function run_test ($host, $dominio) {
	$opts = array ('nameservers' => array ($host));
	$resolver = new Net_DNS2_Resolver ($opts);
	
	/* Intentar una transferencia full */
	$full_axfr = false;
	try {
		$result = $resolver->query ($dominio->dominio, 'AXFR');
		
		$full_axfr = true;
	} catch (Net_DNS2_Exception $err) {
		$full_axfr = false;
	}
	
	try {
		$result = $resolver->query ($dominio->dominio, 'SOA');
	} catch (Net_DNS2_Exception $err) {
		$result = false;
	}
	
	$soa = '';
	$auth = false;
	if ($result !== false) {
		if ($result->header->aa == 1) {
			$auth = true;
		}
		
		if (count ($result->answer) != 0) {
			$soa = ((string) $result->answer[0]);
		}
	}
	
	try {
		$result = $resolver->query ($dominio->dominio, 'NS');
	} catch (Net_DNS2_Exception $err) {
		$result = false;
	}
	
	$ns_list = array ();
	$parent_match = false;
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
			$parent_match = true;
		}
	}
	
	var_dump ($soa);
	return array ('axfr' => $full_axfr, 'auth' => $auth, 'parent_match' => $parent_match, 'ns_list' => $ns_list, 'soa' => $soa);
}

$checks = Gatuf::factory ('DNS42_NSCheck')->getList (array ('order' => 'prioridad ASC', 'filter' => 'estado = 0', 'nb' => 1));

$cont = 0;

while (count ($checks) > 0) {
	$check = $checks[0];
	$ret = $check->block_for_check ();
	
	if ($ret === false) continue;
	
	$ns = $check->get_ns ();
	$server = $ns->get_server ();
	$dominio = $ns->get_dominio ();
	
	if ($server->ipv4 != '') {
		$results = run_test ($server->ipv4, $dominio);
		
		if ($results['axfr'] == true) {
			$ns->open_transfer4 = 1;
		} else {
			$ns->open_transfer4 = 2;
		}
		
		if ($results['auth'] == true) {
			$ns->autoritative4 = 2;
		} else {
			$ns->autoritative4 = 1;
		}
		
		if ($results['parent_match'] == true) {
			$ns->parent_match4 = 2;
		} else {
			$ns->parent_match4 = 1;
		}
		
		$ns->soa4 = $results['soa'];
	}
	
	if ($server->ipv6 != '') {
		$results = run_test ($server->ipv6, $dominio);
		
		if ($results['axfr'] == true) {
			$ns->open_transfer6 = 1;
		} else {
			$ns->open_transfer6 = 2;
		}
		
		if ($results['auth'] == true) {
			$ns->autoritative6 = 2;
		} else {
			$ns->autoritative6 = 1;
		}
		
		if ($results['parent_match'] == true) {
			$ns->parent_match6 = 2;
		} else {
			$ns->parent_match6 = 1;
		}
		
		$ns->soa6 = $results['soa'];
	}
	
	$ns->update ();
	
	$check->delete ();
	
	$checks = Gatuf::factory ('DNS42_NSCheck')->getList (array ('order' => 'prioridad ASC', 'filter' => 'estado = 0', 'nb' => 1));
}
