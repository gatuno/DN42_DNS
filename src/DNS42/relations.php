<?php

$a = array ();

// Relaciones entre modelos aquí
$a['DNS42_NameServer'] = array ('relate_to' => array ('DNS42_Server', 'DNS42_Domain'));
$a['DNS42_PingCheck'] = array ('relate_to' => array ('DNS42_Server'));
$a['DNS42_NSCheck'] = array ('relate_to' => array ('DNS42_NameServer'));

return $a;
