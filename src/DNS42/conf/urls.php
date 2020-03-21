<?php
$base = Gatuf::config('dns42_base');
$ctl = array ();

/* Bloque base:
$ctl[] = array (
	'regex' => '#^/ /$#',
	'base' => $base,
	'model' => 'DNS42_Views',
	'method' => '',
);
*/

/* Sistema de login, y vistas base */
$ctl[] = array (
	'regex' => '#^/$#',
	'base' => $base,
	'model' => 'DNS42_Views_Index',
	'method' => 'index',
);

$ctl[] = array (
	'regex' => '#^/login/$#',
	'base' => $base,
	'model' => 'DNS42_Views_Login',
	'method' => 'login',
	'name' => 'login_view'
);

$ctl[] = array (
	'regex' => '#^/logout/$#',
	'base' => $base,
	'model' => 'DNS42_Views_Login',
	'method' => 'logout',
);

/* Recuperar contraseña */
$ctl[] = array (
	'regex' => '#^/password/recovery/$#',
	'base' => $base,
	'model' => 'DNS42_Views_Login',
	'method' => 'passwordRecoveryAsk',
);

$ctl[] = array (
	'regex' => '#^/password/recovery/ik/$#',
	'base' => $base,
	'model' => 'DNS42_Views_Login',
	'method' => 'passwordRecoveryInputCode',
);

$ctl[] = array (
	'regex' => '#^/password/recovery/k/(.*)/$#',
	'base' => $base,
	'model' => 'DNS42_Views_Login',
	'method' => 'passwordRecovery',
);

/* Gestión de usuarios */
$ctl[] = array (
	'regex' => '#^/users/$#',
	'base' => $base,
	'model' => 'DNS42_Views_Users',
	'method' => 'index',
);

$ctl[] = array (
	'regex' => '#^/users/add/$#',
	'base' => $base,
	'model' => 'DNS42_Views_Users',
	'method' => 'agregar',
);

$ctl[] = array (
	'regex' => '#^/users/(\d+)/$#',
	'base' => $base,
	'model' => 'DNS42_Views_Users',
	'method' => 'ver',
);

$ctl[] = array (
	'regex' => '#^/users/(\d+)/update/$#',
	'base' => $base,
	'model' => 'DNS42_Views_Users',
	'method' => 'actualizar',
);

$ctl[] = array (
	'regex' => '#^/users/(\d+)/reset/password/$#',
	'base' => $base,
	'model' => 'DNS42_Views_Users',
	'method' => 'reset_pass',
);

$ctl[] = array (
	'regex' => '#^/password/change/$#',
	'base' => $base,
	'model' => 'DNS42_Views_Users',
	'method' => 'cambiar_pass',
);

$ctl[] = array (
	'regex' => '#^/dns/$#',
	'base' => $base,
	'model' => 'DNS42_Views_Domains',
	'method' => 'index',
);

$ctl[] = array (
	'regex' => '#^/server/(.*)/$#',
	'base' => $base,
	'model' => 'DNS42_Views_Domains',
	'method' => 'ver_server',
);

$ctl[] = array (
	'regex' => '#^/domain/(.*)/$#',
	'base' => $base,
	'model' => 'DNS42_Views_Domains',
	'method' => 'ver',
);

$ctl[] = array (
	'regex' => '#^/check/ns/(\d+)/$#',
	'base' => $base,
	'model' => 'DNS42_Views_Domains',
	'method' => 'programar_check_ns',
);

$ctl[] = array (
	'regex' => '#^/check/ping/(.*)/$#',
	'base' => $base,
	'model' => 'DNS42_Views_Domains',
	'method' => 'programar_check_ping',
);

$ctl[] = array (
	'regex' => '#^/check/domain/(.*)/$#',
	'base' => $base,
	'model' => 'DNS42_Views_Domains',
	'method' => 'programar_check_domain',
);

$ctl[] = array (
	'regex' => '#^/managed/$#',
	'base' => $base,
	'model' => 'DNS42_Views_Managed',
	'method' => 'index',
);

$ctl[] = array (
	'regex' => '#^/managed/add/master/$#',
	'base' => $base,
	'model' => 'DNS42_Views_Managed',
	'method' => 'agregar_master',
);

$ctl[] = array (
	'regex' => '#^/managed/([a-z0-9-\.]+)/$#',
	'base' => $base,
	'model' => 'DNS42_Views_Managed',
	'method' => 'administrar',
);

$ctl[] = array (
	'regex' => '#^/managed/([a-z0-9-\.]+)/check/delegation/$#',
	'base' => $base,
	'model' => 'DNS42_Views_Managed',
	'method' => 'revisar_delegacion',
);

$ctl[] = array (
	'regex' => '#^/managed/([a-z0-9-\.]+)/delete/master/$#',
	'base' => $base,
	'model' => 'DNS42_Views_Managed',
	'method' => 'eliminar_master',
);

$ctl[] = array (
	'regex' => '#^/managed/([a-z0-9-\.]+)/add/record/([A-Z0-9]+)/$#',
	'base' => $base,
	'model' => 'DNS42_Views_Managed',
	'method' => 'agregar_registro',
);

return $ctl;
