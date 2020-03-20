#!/usr/bin/php
<?php

require dirname(__FILE__).'/../src/DNS42/conf/path.php';
require 'Gatuf.php';
Gatuf::start(dirname(__FILE__).'/../src/DNS42/conf/dns42.php');
Gatuf_Despachador::loadControllers(Gatuf::config('dns42_views'));

restore_error_handler ();

use PhpAmqpLib\Connection\AMQPStreamConnection;

function create_empty_zone ($folder, $domain) {
	$file_name = sprintf ("%s/%s", $folder, $domain);
	$fp = fopen ($file_name, "w");
	
	if ($fp === false) {
		return false;
	}
	
	$serial = date ('Ymd').'00';
	$zone = sprintf (
		"@ 86400 IN SOA ns1.gatuno.dn42. hostmaster.gatuno.dn42. (%s 10800 1800 604800 86400 )\n".
		"@ 86400 IN NS ns1.gatuno.dn42.\n", $serial);
	
	fwrite ($fp, $zone);
	
	fclose ($fp);
	
	return true;
}

$server = Gatuf::config ('amqp_dns_server', 'localhost');
$user = Gatuf::config ('amqp_dns_user', 'guest');
$pass = Gatuf::config ('amqp_dns_password', 'guest');
$port = Gatuf::config ('amqp_dns_port', 5672);
$vhost = Gatuf::config ('amqp_dns_vhost', '/');

$connection = new AMQPStreamConnection($server, $port, $user, $pass, $vhost);
$channel = $connection->channel();

$channel->queue_declare ('dns_zone_add', false, true, false, false);
$channel->queue_declare ('dns_zone_del', false, true, false, false);

$callback_zone_add = function ($msg) {
	$managed = new DNS42_ManagedDomain ();
	
	if (false === $managed->get ($msg->body)) {
		/* ¿El add de un dominio que no existe? Posiblemente lo eliminaron */
		$msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
		return;
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
			
			/* Si no puedo crear un archivo, no intentar crear la zona */
			$msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
			return;
		}
		
		/* Crear la zona dinámica en el master */
		$key = Gatuf::config ('rndc_update_key');
		$server = Gatuf::config ('rndc_update_server');
		$port = Gatuf::config ('rndc_update_port');
		
		$update_key = $managed->get_key ();
		
		$full_exec = sprintf ("/usr/sbin/rndc -k \"%s\" -s \"%s\" -p \"%s\" addzone \"%s\" '{type master; file \"/etc/bind/dynamic_zones/%s\"; allow-update { key %s; }; };' 2>&1", $key, $server, $port, $managed->dominio, $managed->dominio, $update_key->nombre);
		
		exec ($full_exec, $output, $return_code);
		
		if ($return_code == 0) {
			/* Crear todos los records correspondientes */
			
			/* Crear el SOA */
			$record = new DNS42_Record ();
			$record->ttl = 86400;
			$record->dominio = $managed;
			$record->name = $managed->dominio;
			$record->type = 'SOA';
			$serial = date ('Ymd').'00';
			$record->rdata = sprintf ('ns1.gatuno.dn42. hostmaster.gatuno.dn42. %s 10800 1800 604800 86400', $serial);
			$record->locked = TRUE;
			$record->create ();
			
			/* Crear al menos el primer NS */
			$record = new DNS42_Record ();
			$record->ttl = 86400;
			$record->dominio = $managed;
			$record->name = $managed->dominio;
			$record->type = 'NS';
			$record->rdata = 'ns1.gatuno.dn42.';
			$record->locked = TRUE;
			$record->create ();
			
			$managed->delegacion = 2;
			$managed->update ();
		} else {
			$managed->delegacion = 4;
			$managed->update ();
		}
	} else {
		$managed->delegacion = 1;
		$managed->update ();
	}
	
	$msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
};

$callback_zone_del = function ($msg) {
	$old_domain = $msg->body;
	
	/* Crear la zona dinámica en el master */
	$key = Gatuf::config ('rndc_update_key');
	$server = Gatuf::config ('rndc_update_server');
	$port = Gatuf::config ('rndc_update_port');
	
	$full_exec = sprintf ("/usr/sbin/rndc -k \"%s\" -s \"%s\" -p \"%s\" delzone \"%s\" 2>&1", $key, $server, $port, $old_domain);
	
	exec ($full_exec, $output, $return_code);
	
	if ($return_code != 0) {
		/* FIXME: Tenemos una zona pendiente. Revisar que hacer en este caso */
	}
	
	/* Borrar el archivo correspondiente */
	$folder = "/etc/bind/dynamic_zones";
	$file_name = sprintf ("%s/%s", $folder, $old_domain);
	
	$deleted = unlink ($file_name);
	
	if ($deleted === false) {
		/* FIXME: Otro problema, no pude eliminar el archivo */
	}
	
	$msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
};

$channel->basic_qos (null, 1, null);
$channel->basic_consume ('dns_zone_add', '', false, false, false, false, $callback_zone_add);
$channel->basic_consume ('dns_zone_del', '', false, false, false, false, $callback_zone_del);

while (1) {
    $channel->wait();
}

$channel->close();
$connection->close();
