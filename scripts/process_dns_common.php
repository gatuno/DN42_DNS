<?php

function create_empty_zone ($folder, $domain) {
	$basename = str_replace ('/', '_', $domain);
	$file_name = sprintf ("%s/%s", $folder, $basename);
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
