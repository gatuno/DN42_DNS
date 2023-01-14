<?php

function create_empty_zone ($folder, $domain, $master_name) {
	$basename = str_replace ('/', '_', $domain);
	$file_name = sprintf ("%s/%s", $folder, $basename);
	$fp = fopen ($file_name, "w");
	
	if ($fp === false) {
		return false;
	}
	
	if (substr ($master_name, -1) != '.') {
		$master_name .= '.';
	}
	$serial = date ('Ymd').'00';
	$zone = sprintf (
		"@ 86400 IN SOA %s hostmaster.gatuno.dn42. (%s 10800 1800 604800 86400 )\n".
		"@ 86400 IN NS %s\n", $master_name, $serial, $master_name);
	
	fwrite ($fp, $zone);
	
	fclose ($fp);
	
	return true;
}

function update_soa_from_master ($managed) {
	$masters = Gatuf::config ('rndc_master', array ());
	if (count ($masters) == 0) {
		throw new Exception (__('Configuration Error. There should be only 1 master'));
	}
	
	$master_name = array_key_first ($masters);
	$master_ip = $masters[$master_name];
	
	$opts = array ('nameservers' => array ($master_ip));
	$resolver = new Net_DNS2_Resolver ($opts);
	
	try {
		$result = $resolver->query ($managed->dominio, 'SOA');
	} catch (Net_DNS2_Exception $err) {
		$result = false;
	}
	
	if ($result !== false) {
		printf ("After the update, the domain '%s' returned SOA: '%s'\n", $managed->dominio, (string) $result->answer[0]);
		
		$records = $managed->get_records_list (array ('filter' => 'type="SOA"'));
		
		if (count ($records) != 1) {
			printf ("Wrong amount of SOA records for domain '%s'\n", $managed->dominio);
		} else {
			$records[0]->rdata = $result->answer[0]->getRRData ();
			$records[0]->update ();
		}
	}
}
