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
