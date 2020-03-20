<?php

$a = array ();

// Relaciones entre modelos aquí
$a['DNS42_NameServer'] = array ('relate_to' => array ('DNS42_Server', 'DNS42_Domain'));
$a['DNS42_PingCheck'] = array ('relate_to' => array ('DNS42_Server'));
$a['DNS42_NSCheck'] = array ('relate_to' => array ('DNS42_NameServer'));
$a['DNS42_ManagedDomain'] = array ('relate_to' => array ('Gatuf_User', 'DNS42_UpdateKey'));
$a['DNS42_Record'] = array ('relate_to' => array ('DNS42_ManagedDomain'));

return $a;
