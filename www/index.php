<?php

require dirname(__FILE__).'/../src/DNS42/conf/path.php';

# Cargar Gatuf
require 'Gatuf.php';

# Inicializar las configuraciones
Gatuf::start(dirname(__FILE__).'/../src/DNS42/conf/dns42.php');

Gatuf_Despachador::loadControllers(Gatuf::config('dns42_views'));

Gatuf_Despachador::despachar(Gatuf_HTTP_URL::getAction());
