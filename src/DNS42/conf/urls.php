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

/*$ctl[] = array (
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
);*/

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

/* Registro de usuarios */
$ctl[] = array (
	'regex' => '#^/register/$#',
	'base' => $base,
	'model' => 'DNS42_Views_Register',
	'method' => 'register',
);

/* URL para cambiar el tema */
$ctl[] = array (
	'regex' => '#^/change/theme/(.*)/$#',
	'base' => $base,
	'model' => 'DNS42_Views_Index',
	'method' => 'cambiar_tema',
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
	'regex' => '#^/managed/add/reverse/$#',
	'base' => $base,
	'model' => 'DNS42_Views_Managed',
	'method' => 'agregar_reversa',
);

$ctl[] = array (
	'regex' => '#^/managed/([a-z0-9-\.:_]+)/$#',
	'base' => $base,
	'model' => 'DNS42_Views_Managed',
	'method' => 'administrar',
);

$ctl[] = array (
	'regex' => '#^/managed/([a-z0-9-\.:_]+)/check/delegation/$#',
	'base' => $base,
	'model' => 'DNS42_Views_Managed',
	'method' => 'revisar_delegacion',
);

$ctl[] = array (
	'regex' => '#^/managed/([a-z0-9-\.:_]+)/delete/master/$#',
	'base' => $base,
	'model' => 'DNS42_Views_Managed',
	'method' => 'eliminar_master',
);

$ctl[] = array (
	'regex' => '#^/managed/([a-z0-9-\.:_]+)/add/record/([A-Z0-9]+)/$#',
	'base' => $base,
	'model' => 'DNS42_Views_Managed',
	'method' => 'agregar_registro',
);

$ctl[] = array (
	'regex' => '#^/managed/delete/record/(\d+)/$#',
	'base' => $base,
	'model' => 'DNS42_Views_Managed',
	'method' => 'eliminar_registro',
);

$ctl[] = array (
	'regex' => '#^/managed/update/record/(\d+)/$#',
	'base' => $base,
	'model' => 'DNS42_Views_Managed',
	'method' => 'actualizar_registro',
);

return $ctl;
