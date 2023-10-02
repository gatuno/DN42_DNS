<?php

Gatuf::loadFunction('Gatuf_HTTP_URL_urlForView');
Gatuf::loadFunction('DNS42_Shortcuts_RenderToResponse');

class DNS42_Views_Managed {
	public static function recover_domain ($domain_name, $user) {
		$managed = new DNS42_ManagedDomain ();
		$sql = new Gatuf_SQL ('dominio=%s', array ($domain_name));
		
		if (null === ($managed->getOne($sql->gen ()))) {
			/* Prodría ser una zona inversa */
			$sql = new Gatuf_SQL ('prefix=%s', array ($domain_name));
			
			if (null === ($managed->getOne($sql->gen ()))) {
				throw new Gatuf_HTTP_Error404 ();
			}
		}
		/* Revisar si es el dueño del dominio */
		if ($managed->owner == $user->id) {
			return $managed;
		}
		
		/* En caso contrario, recuperar quién puede administrar este dominio */
		$allowed_users = $managed->get_users_list ();
		foreach ($allowed_users as $one) {
			if ($user->id == $one->id) {
				return $managed;
			}
		}
		
		throw new Gatuf_HTTP_Error404 ();
	}
	
	public $index_precond = array ('Gatuf_Precondition::loginRequired');
	public function index ($request, $match) {
		$all_domains = array ();
		
		$domains = $request->user->get_owned_domains_list ();
		foreach ($domains as $d) {
			$all_domains[$d->id] = $d;
		}
		$domains = $request->user->get_managed_domains_list ();
		foreach ($domains as $d) {
			$all_domains[$d->id] = $d;
		}
		
		$request->active_tab = 'free_dns';
		return DNS42_Shortcuts_RenderToResponse ('dns42/managed/index.html',
		                                         array('page_title' => __('Free DNS Management'),
		                                         'domains' => $all_domains),
		                                         $request);
	}
	
	public $agregar_master_precond = array ('Gatuf_Precondition::loginRequired');
	public function agregar_master ($request, $match) {
		$title = __('Add new domain');
		
		$key_name = Gatuf::config ('current_default_update_key', '');
		$sql = new Gatuf_SQL ('nombre=%s', $key_name);
		$key = Gatuf::factory ('DNS42_UpdateKey')->getOne ($sql->gen ());
		
		if ($key === null) {
			throw new Exception (__('Configuration Error. Missing default update key'));
		}
		
		$extra = array ('user' => $request->user, 'key' => $key);
		if ($request->method == 'POST') {
			$form = new DNS42_Form_Managed_Agregar ($request->POST, $extra);
			
			if ($form->isValid ()) {
				$managed = $form->save ();
				
				/* Antes de crear el SOA, revisar de mi configuración cuál es el master que tengo para agregarlo al SOA */
				$masters = Gatuf::config ('rndc_master', array ());
				if (count ($masters) != 1) {
					throw new Exception (__('Configuration Error. There should be only 1 master'));
				}
				
				$master_name = array_key_first ($masters);
				if (substr ($master_name, -1) != '.') {
					$master_name .= '.';
				}
				
				/* Crear el SOA */
				$record = new DNS42_Record ();
				$record->ttl = 86400;
				$record->dominio = $managed;
				$record->name = $managed->dominio;
				$record->type = 'SOA';
				$serial = date ('Ymd').'00';
				$record->rdata = sprintf ('%s hostmaster.gatuno.dn42. %s 10800 1800 604800 86400', $master_name, $serial);
				$record->locked = TRUE;
				$record->create ();
			
				/* Crear al menos el registro NS correspondiente al maestro */
				$record = new DNS42_Record ();
				$record->ttl = 86400;
				$record->dominio = $managed;
				$record->name = $managed->dominio;
				$record->type = 'NS';
				$record->rdata = $master_name;
				$record->locked = TRUE;
				$record->create ();
				
				/* Si existen esclavos, agregar los registros NS correspondientes */
				$slaves = Gatuf::config ('rndc_slaves', array ());
				foreach ($slaves as $slave_name => $slave_ip) {
					if (substr ($slave_name, -1) != '.') {
						$slave_name .= '.';
					}
					
					$record = new DNS42_Record ();
					$record->ttl = 86400;
					$record->dominio = $managed;
					$record->name = $managed->dominio;
					$record->type = 'NS';
					$record->rdata = $slave_name;
					$record->locked = FALSE;
					$record->create ();
				}
				
				$delegar = DNS42_RMQ::send_create_domain ($managed);
				
				/* TODO: Revisar delegar */
				$url = Gatuf_HTTP_URL_urlForView ('DNS42_Views_Managed::administrar', array ($managed->dominio));
				return new Gatuf_HTTP_Response_Redirect ($url);
			}
		} else {
			$form = new DNS42_Form_Managed_Agregar (null, $extra);
		}
		
		$masters = Gatuf::config ('rndc_master', array ());
		if (count ($masters) != 1) {
			throw new Exception (__('Configuration Error. There should be only 1 master'));
		}
		$slaves = Gatuf::config ('rndc_slaves', array ());
		$master_name = array_key_first ($masters);
		$slave_names = implode (', ', array_keys ($slaves));
		
		$request->active_tab = 'free_dns';
		return DNS42_Shortcuts_RenderToResponse ('dns42/managed/agregar.html',
		                                         array ('page_title' => $title,
		                                                'master_name' => $master_name,
		                                                'slave_names' => $slave_names,
		                                                'form' => $form),
		                                         $request);
	}
	
	public $eliminar_master_precond = array ('Gatuf_Precondition::loginRequired');
	public function eliminar_master ($request, $match) {
		$managed = new DNS42_ManagedDomain ();
		$sql = new Gatuf_SQL ('dominio=%s AND owner=%s', array ($match[1], $request->user->id));
		
		if (null === ($managed->getOne($sql->gen ()))) {
			/* Prodría ser una zona inversa */
			$sql = new Gatuf_SQL ('prefix=%s AND owner=%s', array ($match[1], $request->user->id));
			
			if (null === ($managed->getOne($sql->gen ()))) {
				throw new Gatuf_HTTP_Error404 ();
			}
		}
		
		if ($request->method == 'POST') {
			if ($managed->delegacion == 2 || $managed->delegacion == 6) {
				DNS42_RMQ::send_delete_domain ($managed);
			}
			
			$managed->delete ();
			
			$url = Gatuf_HTTP_URL_urlForView ('DNS42_Views_Managed::index');
			return new Gatuf_HTTP_Response_Redirect ($url);
		}
		
		$request->active_tab = 'free_dns';
		$title = $managed->reversa ? __('Delete reverse zone') : __('Delete domain');
		return DNS42_Shortcuts_RenderToResponse ('dns42/managed/eliminar.html',
		                                         array ('page_title' => $title,
		                                                'managed' => $managed),
		                                         $request);
	}
	
	public $administrar_precond = array ('Gatuf_Precondition::loginRequired');
	public function administrar ($request, $match) {
		$managed = self::recover_domain ($match[1], $request->user);
		
		$all_records = array ();
		
		/* Primero los registros bloqueados */
		$order_type = array ('SOA', 'NS', 'A', 'AAAA', 'SPF', 'MX', 'CNAME', 'CAA', 'PTR', 'SRV', 'TXT', 'LOC', 'SSHFP');
		foreach ($order_type as $type) {
			$sql = new Gatuf_SQL ('type=%s', array ($type));
			$records = $managed->get_records_list (array ('filter' => $sql->gen (), 'order' => 'locked DESC, name ASC, rdata ASC'));
			$all_records = array_merge ($all_records, $records->getArrayCopy ());
		}
		
		$where = 'type NOT IN ('.implode (', ', array_fill (0, count ($order_type), '%s')).')';
		$sql = new Gatuf_SQL ($where, $order_type);
		$records = $managed->get_records_list (array ('filter' => $sql->gen (), 'order' => 'locked DESC, name ASC, rdata ASC'));
		$all_records = array_merge ($all_records, $records->getArrayCopy ());
		
		$request->active_tab = 'free_dns';
		return DNS42_Shortcuts_RenderToResponse ('dns42/managed/ver.html',
		                                         array ('page_title' => __('Free DNS Management'),
		                                                'managed' => $managed,
		                                                'records' => $all_records),
		                                         $request);
	}
	
	public $administrar_simple_precond = array ('Gatuf_Precondition::loginRequired');
	public function administrar_simple ($request, $match, $params) {
		$managed = self::recover_domain ($match[1], $request->user);
		
		$all_records = array ();
		
		if (!$managed->reversa) {
			/* Solo las zonas DNS inversas pueden ser simples */
			$url = Gatuf_HTTP_URL_urlForView ('DNS42_Views_Managed::administrar', array ($managed->dominio));
			return new Gatuf_HTTP_Response_Redirect ($url);
		}
		
		/* Cuando administramos una zona inversa simple, vamos a recuperar TODOS los registros PTR y listarlos de forma "diferente" */
		$sql = new Gatuf_SQL ('type="PTR"');
		$records = $managed->get_records_list (array ('filter' => $sql->gen (), 'order' => 'locked DESC, name ASC, rdata ASC'));
		
		/* Conseguir el prefijo y la máscara */
		$toks = explode ('_', $managed->prefix);

		if (count ($toks) != 2) {
			throw new Exception (__('Undefined'));
		}

		$prefix = $managed->get_reverse_prefix ();
		
		/* Reorganizar cada registro, para que sea el prefijo + "terminacion IP" */
		$simples = array ();
		foreach ($records as $record) {
			$obj = new stdClass;
			
			$obj->record = $record;
			$obj->hostname = trim ($record->rdata, '.');
			if ($managed->tipo_reversa == 4) {
				/* Tomamos los primeros octetos de la IP, conforme a la máscara */
				$obj->prefix = $prefix;
				$toks = explode ('.', $record->name);
				
				$obj->ip = $toks[0];
			} else if ($managed->tipo_reversa == 6) {
				$obj->prefix = $prefix;
				$to_remove = strlen ('.ip6.arpa');
				$full_ip_parts = array_reverse (explode ('.', substr ($record->name, 0, -$to_remove)));
				$full_ip = '';
				for ($g = 0; $g < 32; $g++) {
					$full_ip .= $full_ip_parts[$g];
					if (($g % 4) == 3) {
						$full_ip .= ':';
					}
				}
				$full_ip = trim ($full_ip, ':');
				
				/* Convertir con inet_ntop y de regreso para sacar una IP con bloques comprimidos */
				$ip = inet_ntop (inet_pton ($full_ip));
				
				/* Ahora, restar el prefijo que usamos */
				$ip_part = substr ($ip, strlen ($prefix));
				$obj->ip = $ip_part;
			}
			$simples[] = $obj;
		}
		
		$extra = array ('dominio' => $managed);
		if (isset ($params['check_form_agregar'])) {
			$form_agregar = new DNS42_Form_Record_Add_PTRSimple ($request->POST, $extra);
			$form_agregar->isValid ();
		} else {
			$form_agregar = new DNS42_Form_Record_Add_PTRSimple (null, $extra);
		}
		
		$request->active_tab = 'free_dns';
		return DNS42_Shortcuts_RenderToResponse ('dns42/managed/reversa_simple.html',
		                                         array ('page_title' => __('Free DNS Management'),
		                                                'managed' => $managed,
		                                                'short_prefix' => $prefix,
		                                                'form_agregar' => $form_agregar,
		                                                'simples' => $simples),
		                                         $request);
	}
	
	public $revisar_delegacion_precond = array ('Gatuf_Precondition::loginRequired');
	public function revisar_delegacion ($request, $match) {
		$managed = new DNS42_ManagedDomain ();
		$sql = new Gatuf_SQL ('dominio=%s AND owner=%s', array ($match[1], $request->user->id));
		
		if (null === ($managed->getOne($sql->gen ()))) {
			/* Prodría ser una zona inversa */
			$sql = new Gatuf_SQL ('prefix=%s AND owner=%s', array ($match[1], $request->user->id));
			
			if (null === ($managed->getOne($sql->gen ()))) {
				throw new Gatuf_HTTP_Error404 ();
			}
		}
		
		if ($managed->delegacion == 2) {
			$request->user->setMessage (1, __("The delegation for this zone was found and is correct. The zone is now active"));
		} else if ($managed->delegacion == 6) {
			$delegar = DNS42_RMQ::send_check_delegation ($managed);
		} else {
			$delegar = DNS42_RMQ::send_create_domain ($managed);
			
			$request->user->setMessage (1, __("The delegation check for this zone was scheduled, please wait at least 1 minute and refresh the page to see the result"));
		}
		
		if ($managed->reversa) {
			$url = Gatuf_HTTP_URL_urlForView ('DNS42_Views_Managed::administrar', array ($managed->prefix));
		} else {
			$url = Gatuf_HTTP_URL_urlForView ('DNS42_Views_Managed::administrar', array ($managed->dominio));
		}
		return new Gatuf_HTTP_Response_Redirect ($url);
	}
	
	public $agregar_registro_precond = array ('Gatuf_Precondition::loginRequired');
	public function agregar_registro ($request, $match) {
		$managed = self::recover_domain ($match[1], $request->user);
		
		if ($managed->delegacion == 0 || $managed->delegacion == 1) {
			$request->user->setMessage (3, __('You can create records on the domain, but the zone will became active in the DNS until delegation works.'));
		} else if ($managed->delegacion == 6) {
			$request->user->setMessage (3, __('You can create records on the domain, but please check delegation.'));
		}
		
		$allowed_normal = array (
			'A' => 'DNS42_Form_Record_Add_A',
			'AAAA' => 'DNS42_Form_Record_Add_AAAA',
			'CNAME' => 'DNS42_Form_Record_Add_CNAME',
			'MX' => 'DNS42_Form_Record_Add_MX',
			'NS' => 'DNS42_Form_Record_Add_NS',
			'TXT' => 'DNS42_Form_Record_Add_TXT',
			'SRV' => 'DNS42_Form_Record_Add_SRV',
		);
		$allowed_reverse = array (
			'CNAME' => 'DNS42_Form_Record_Add_CNAME',
			'PTR' => 'DNS42_Form_Record_Add_PTR',
			'NS' => 'DNS42_Form_Record_Add_NS',
			'TXT' => 'DNS42_Form_Record_Add_TXT',
		);
		
		$type = mb_strtoupper ($match[2]);
		
		if ($type != $match[2]) {
			/* Redirigir a las URLs en mayúsculas */
			$url = Gatuf_HTTP_URL_urlForView ('DNS42_Views_Managed::agregar_registro', array ($managed->dominio, $type));
			return new Gatuf_HTTP_Response_Redirect ($url);
		}
		
		if ($managed->reversa == false) {
			if (!array_key_exists ($type, $allowed_normal)) {
				$url = Gatuf_HTTP_URL_urlForView ('DNS42_Views_Managed::administrar', array ($managed->dominio));
				return new Gatuf_HTTP_Response_Redirect ($url);
			}
			$form_type = $allowed_normal[$type];
		} else {
			if (!array_key_exists ($type, $allowed_reverse)) {
				$url = Gatuf_HTTP_URL_urlForView ('DNS42_Views_Managed::administrar', array ($managed->prefix));
				return new Gatuf_HTTP_Response_Redirect ($url);
			}
			$form_type = $allowed_reverse[$type];
		}
		
		$title = sprintf (__('Add record %s'), $type);
		$extra = array ('dominio' => $managed);
		if ($request->method == 'POST') {
			$form = new $form_type ($request->POST, $extra);
			
			if ($form->isValid ()) {
				$record = $form->save ();
				
				if ($managed->delegacion == 2 || $managed->delegacion == 6) {
					$delegar = DNS42_RMQ::send_add_record ($record);
				}
				
				if ($managed->reversa) {
					$url = Gatuf_HTTP_URL_urlForView ('DNS42_Views_Managed::administrar', array ($managed->prefix));
				} else {
					$url = Gatuf_HTTP_URL_urlForView ('DNS42_Views_Managed::administrar', array ($managed->dominio));
				}
				return new Gatuf_HTTP_Response_Redirect ($url);
			}
		} else {
			$form = new $form_type (null, $extra);
		}
		
		$request->active_tab = 'free_dns';
		return DNS42_Shortcuts_RenderToResponse ('dns42/managed/agregar_registro.html',
		                                         array ('page_title' => $title,
		                                                'managed' => $managed,
		                                                'form' => $form),
		                                         $request);
	}
	
	public $agregar_registro_simple_precond = array ('Gatuf_Precondition::loginRequired');
	public function agregar_registro_simple ($request, $match) {
		$managed = self::recover_domain ($match[1], $request->user);
		
		if ($request->method != 'POST') {
			$url = Gatuf_HTTP_URL_urlForView ('DNS42_Views_Managed::administrar_simple', array ($managed->prefix));
		}
		
		$extra = array ('dominio' => $managed);
		$form = new DNS42_Form_Record_Add_PTRSimple ($request->POST, $extra);

		if ($form->isValid()) {
			$record = $form->save();
			$url = Gatuf_HTTP_URL_urlForView ('DNS42_Views_Managed::administrar_simple', array ($managed->prefix));
			
			if ($managed->delegacion == 2 || $managed->delegacion == 6) {
				$delegar = DNS42_RMQ::send_add_record ($record);
			}
			
			return new Gatuf_HTTP_Response_Redirect($url);
		}
		
		/* En caso de formulario inválido, retornar la vista de arriba */
		return $this->administrar_simple ($request, $match, array ('check_form_agregar' => true));
	}
	
	public $eliminar_registro_precond = array ('Gatuf_Precondition::loginRequired');
	public function eliminar_registro ($request, $match) {
		$record = new DNS42_Record ();
		
		if (false === ($record->get($match[1]))) {
			throw new Gatuf_HTTP_Error404 ();
		}
		
		$managed = $record->get_dominio ();
		
		if ($request->user->id != $managed->owner) {
			/* La otra posibilidad es que es sea un sub-administrador */
			$found = false;
			$allowed_users = $managed->get_users_list ();
			foreach ($allowed_users as $one) {
				if ($one->id == $request->user->id) {
					$found = true;
				}
			}
			if (!$found) {
				throw new Gatuf_HTTP_Error404 ();
			}
		}
		
		if ($record->locked) {
			if ($managed->reversa) {
				$url = Gatuf_HTTP_URL_urlForView ('DNS42_Views_Managed::administrar', array ($managed->prefix));
			} else {
				$url = Gatuf_HTTP_URL_urlForView ('DNS42_Views_Managed::administrar', array ($managed->dominio));
			}
			return new Gatuf_HTTP_Response_Redirect ($url);
		}
		
		$simple = false;
		if ($managed->reversa && isset ($request->REQUEST['simple'])) $simple = true;
		if ($managed->reversa && $record->type == 'PTR') {
			/* Calcular la IP completa para las zonas inversas */
			if ($managed->tipo_reversa == 6) {
				$domain = '.ip6.arpa';
				/* Es un IPv6 */
				$full_hexs = array_reverse (explode ('.', substr ($record->name, 0, -strlen ($domain))));
				$full_ip = inet_ntop (pack ("H*", implode ('', $full_hexs)));
			} else if ($managed->tipo_reversa == 4) {
				/* Es una dirección de IPv4 */
				$toks = explode ('_', $managed->prefix);
				$network = $toks[0];
				$mask = ((int) $toks[1]);
				
				$toks = explode ('.', $network);
				if ($mask >= 24) {
					$prefix = $toks[0].'.'.$toks[1].'.'.$toks[2].'.';
				} else if ($mask >= 16) {
					$prefix = $toks[0].'.'.$toks[1].'.';
				} else if ($mask >= 8) {
					$prefix = $toks[0].'.';
				} else {
					$prefix = '';
				}
				
				$toks = explode ('.', $record->name);
				$full_ip = $prefix.$toks[0];
			}
			$record->full_ip = $full_ip;
		}
		
		if ($request->method == 'POST') {
			if ($managed->delegacion == 2 || $managed->delegacion == 6) {
				DNS42_RMQ::send_del_record ($record);
			}
			
			$record->delete ();
			
			if ($managed->reversa) {
				if ($simple) {
					$url = Gatuf_HTTP_URL_urlForView ('DNS42_Views_Managed::administrar_simple', array ($managed->prefix));
				} else {
					$url = Gatuf_HTTP_URL_urlForView ('DNS42_Views_Managed::administrar', array ($managed->prefix));
				}
			} else {
				$url = Gatuf_HTTP_URL_urlForView ('DNS42_Views_Managed::administrar', array ($managed->dominio));
			}
			return new Gatuf_HTTP_Response_Redirect ($url);
		}
		
		$request->active_tab = 'free_dns';
		return DNS42_Shortcuts_RenderToResponse ('dns42/managed/eliminar_registro.html',
		                                         array ('page_title' => __('Delete record'),
		                                                'record' => $record,
		                                                'simple' => $simple,
		                                                'managed' => $managed),
		                                         $request);
	}
	
	public $actualizar_registro_precond = array ('Gatuf_Precondition::loginRequired');
	public function actualizar_registro ($request, $match) {
		$record = new DNS42_Record ();
		
		if (false === ($record->get($match[1]))) {
			throw new Gatuf_HTTP_Error404 ();
		}
		
		$managed = $record->get_dominio ();
		
		if ($request->user->id != $managed->owner) {
			/* La otra posibilidad es que es sea un sub-administrador */
			$found = false;
			$allowed_users = $managed->get_users_list ();
			foreach ($allowed_users as $one) {
				if ($one->id == $request->user->id) {
					$found = true;
				}
			}
			if (!$found) {
				throw new Gatuf_HTTP_Error404 ();
			}
		}
		
		if ($record->can_be_updated () == false) {
			if ($managed->reversa) {
				$url = Gatuf_HTTP_URL_urlForView ('DNS42_Views_Managed::administrar', array ($managed->prefix));
			} else {
				$url = Gatuf_HTTP_URL_urlForView ('DNS42_Views_Managed::administrar', array ($managed->dominio));
			}
			return new Gatuf_HTTP_Response_Redirect ($url);
		}
		
		$update_forms = array (
			'A' => 'DNS42_Form_Record_Update_A',
			'AAAA' => 'DNS42_Form_Record_Update_AAAA',
			'CNAME' => 'DNS42_Form_Record_Update_CNAME',
			'MX' => 'DNS42_Form_Record_Update_MX',
			'NS' => 'DNS42_Form_Record_Update_NS',
			'TXT' => 'DNS42_Form_Record_Update_TXT',
			'SRV' => 'DNS42_Form_Record_Update_SRV',
			'PTR' => 'DNS42_Form_Record_Update_PTR',
		);
		
		$form_type = $update_forms[$record->type];
		
		$title = sprintf (__('Update record %s'), $record->type);
		$extra = array ('record' => $record);
		if ($request->method == 'POST') {
			$form = new $form_type ($request->POST, $extra);
			
			if ($form->isValid ()) {
				$old_record_name = $record->name;
				$old_record_rdata = $record->rdata;
				$old_record_ttl = $record->ttl;
				
				$record = $form->save ();
				
				if ($managed->delegacion == 2 || $managed->delegacion == 6) {
					$delegar = DNS42_RMQ::send_update_record ($record, $old_record_name, $old_record_rdata);
				}
				
				/* Como Bind no permite que el mismo registro tenga diferentes TTL, actualizar todos los registros que coincidan por nombre y tipo al mismo TTL */
				$sql = new Gatuf_SQL ('dominio=%s AND name=%s AND type=%s AND id!=%s', array ($record->dominio, $record->name, $record->type, $record->id));
				$others = Gatuf::factory ('DNS42_Record')->getList (array ('filter' => $sql->gen ()));
				foreach ($others as $other) {
					$other->ttl = $record->ttl;
					
					$other->update ();
				}
				
				if ($managed->reversa) {
					$url = Gatuf_HTTP_URL_urlForView ('DNS42_Views_Managed::administrar', array ($managed->prefix));
				} else {
					$url = Gatuf_HTTP_URL_urlForView ('DNS42_Views_Managed::administrar', array ($managed->dominio));
				}
				return new Gatuf_HTTP_Response_Redirect ($url);
			}
		} else {
			$form = new $form_type (null, $extra);
		}
		
		$request->active_tab = 'free_dns';
		return DNS42_Shortcuts_RenderToResponse ('dns42/managed/actualizar_registro.html',
		                                         array ('page_title' => $title,
		                                                'managed' => $managed,
		                                                'record' => $record,
		                                                'form' => $form),
		                                         $request);
	}
	
	public $agregar_reversa_precond = array ('Gatuf_Precondition::loginRequired');
	public function agregar_reversa ($request, $match) {
		$title = __('Add new reverse');
		
		$key_name = Gatuf::config ('current_default_update_key', '');
		$sql = new Gatuf_SQL ('nombre=%s', $key_name);
		$key = Gatuf::factory ('DNS42_UpdateKey')->getOne ($sql->gen ());
		
		if ($key === null) {
			throw new Exception (__('Configuration Error. Missing default update key'));
		}
		
		$extra = array ('user' => $request->user, 'key' => $key);
		if ($request->method == 'POST') {
			$form = new DNS42_Form_Managed_AgregarInversa ($request->POST, $extra);
			
			if ($form->isValid ()) {
				$managed = $form->save ();
				
				/* Antes de crear el SOA, revisar de mi configuración cuál es el master que tengo para agregarlo al SOA */
				$masters = Gatuf::config ('rndc_master', array ());
				if (count ($masters) != 1) {
					throw new Exception (__('Configuration Error. There should be only 1 master'));
				}
				
				$master_name = array_key_first ($masters);
				if (substr ($master_name, -1) != '.') {
					$master_name .= '.';
				}
				
				/* Crear el SOA */
				$record = new DNS42_Record ();
				$record->ttl = 86400;
				$record->dominio = $managed;
				$record->name = $managed->dominio;
				$record->type = 'SOA';
				$serial = date ('Ymd').'00';
				$record->rdata = sprintf ('%s hostmaster.gatuno.dn42. %s 10800 1800 604800 86400', $master_name, $serial);
				$record->locked = TRUE;
				$record->create ();
			
				/* Crear al menos el primer NS */
				$record = new DNS42_Record ();
				$record->ttl = 86400;
				$record->dominio = $managed;
				$record->name = $managed->dominio;
				$record->type = 'NS';
				$record->rdata = $master_name;
				$record->locked = TRUE;
				$record->create ();
				
				/* Si existen esclavos, agregar los registros NS correspondientes */
				$slaves = Gatuf::config ('rndc_slaves', array ());
				foreach ($slaves as $slave_name => $slave_ip) {
					if (substr ($slave_name, -1) != '.') {
						$slave_name .= '.';
					}
					
					$record = new DNS42_Record ();
					$record->ttl = 86400;
					$record->dominio = $managed;
					$record->name = $managed->dominio;
					$record->type = 'NS';
					$record->rdata = $slave_name;
					$record->locked = FALSE;
					$record->create ();
				}
				
				$delegar = DNS42_RMQ::send_create_domain ($managed);
				
				/* TODO: Revisar delegar */
				$url = Gatuf_HTTP_URL_urlForView ('DNS42_Views_Managed::administrar', array ($managed->prefix));
				return new Gatuf_HTTP_Response_Redirect ($url);
			}
		} else {
			$form = new DNS42_Form_Managed_AgregarInversa (null, $extra);
		}
		
		return DNS42_Shortcuts_RenderToResponse ('dns42/managed/agregar_reversa.html',
		                                         array ('page_title' => $title,
		                                                'form' => $form),
		                                         $request);
	}
}

