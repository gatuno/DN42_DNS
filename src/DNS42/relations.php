<?php

$a = array ();

// Relaciones entre modelos aquí
$a['DNS42_NameServer'] = array ('relate_to_many' => array ('DNS42_TopLevelDomain'));
$a['DNS42_PingCheck'] = array ('relate_to' => array ('DNS42_NameServer'));

return $a;
