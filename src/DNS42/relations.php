<?php

$a = array ();

// Relaciones entre modelos aquí
$a['DNS42_ManagedDomain'] = array ('relate_to' => array ('Gatuf_User', 'DNS42_UpdateKey'), 'relate_to_many' => array ('Gatuf_User'));
$a['DNS42_Record'] = array ('relate_to' => array ('DNS42_ManagedDomain'));

return $a;
