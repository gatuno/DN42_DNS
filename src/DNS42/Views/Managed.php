<?php

Gatuf::loadFunction('Gatuf_HTTP_URL_urlForView');
Gatuf::loadFunction('Gatuf_Shortcuts_RenderToResponse');

class DNS42_Views_Managed {
	public $index_precond = array ('Gatuf_Precondition::loginRequired');
	public function index ($request, $match) {
		$domains = $request->user->get_managed_domains_list ();
		
		return Gatuf_Shortcuts_RenderToResponse ('dns42/managed/index.html',
		                                         array('page_title' => __('Free DNS Management'),
		                                         'domains' => $domains),
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
				
				/* Crear el SOA */
				$record = new DNS42_Record ();
				$record->ttl = 86400;
				$record->dominio = $managed;
				$record->name = $managed->dominio;
				$record->type = 'SOA';
				$serial = date ('Ymd').'00';
				$record->rdata = sprintf ('ns1.gatuno.dn42. hostmaster.gatuno.dn42. %s 10800 1800 604800 86400', $serial);
				$record->locked = TRUE;
				$record->create ();
			
				/* Crear al menos el primer NS */
				$record = new DNS42_Record ();
				$record->ttl = 86400;
				$record->dominio = $managed;
				$record->name = $managed->dominio;
				$record->type = 'NS';
				$record->rdata = 'ns1.gatuno.dn42.';
				$record->locked = TRUE;
				$record->create ();
				
				$delegar = DNS42_RMQ::send_create_domain ($managed);
				
				/* TODO: Revisar delegar */
				$url = Gatuf_HTTP_URL_urlForView ('DNS42_Views_Managed::administrar', array ($managed->dominio));
				return new Gatuf_HTTP_Response_Redirect ($url);
			}
		} else {
			$form = new DNS42_Form_Managed_Agregar (null, $extra);
		}
		
		return Gatuf_Shortcuts_RenderToResponse ('dns42/managed/agregar.html',
		                                         array ('page_title' => $title,
		                                                'form' => $form),
		                                         $request);
	}
	
	public $eliminar_master_precond = array ('Gatuf_Precondition::loginRequired');
	public function eliminar_master ($request, $match) {
		$managed = new DNS42_ManagedDomain ();
		$sql = new Gatuf_SQL ('dominio=%s AND user=%s', array ($match[1], $request->user->id));
		
		if (null === ($managed->getOne($sql->gen ()))) {
			throw new Gatuf_HTTP_Error404 ();
		}
		
		if ($request->method == 'POST') {
			if ($managed->delegacion == 2) {
				DNS42_RMQ::send_delete_domain ($managed);
			}
			
			$managed->delete ();
			
			$url = Gatuf_HTTP_URL_urlForView ('DNS42_Views_Managed::index');
			return new Gatuf_HTTP_Response_Redirect ($url);
		}
		
		return Gatuf_Shortcuts_RenderToResponse ('dns42/managed/eliminar.html',
		                                         array ('page_title' => __('Delete domain'),
		                                                'managed' => $managed),
		                                         $request);
	}
	
	public $administrar_precond = array ('Gatuf_Precondition::loginRequired');
	public function administrar ($request, $match) {
		$managed = new DNS42_ManagedDomain ();
		$sql = new Gatuf_SQL ('dominio=%s AND user=%s', array ($match[1], $request->user->id));
		
		if (null === ($managed->getOne($sql->gen ()))) {
			throw new Gatuf_HTTP_Error404 ();
		}
		
		$records = $managed->get_records_list ();
		
		return Gatuf_Shortcuts_RenderToResponse ('dns42/managed/ver.html',
		                                         array ('page_title' => __('Free DNS Management'),
		                                                'managed' => $managed,
		                                                'records' => $records),
		                                         $request);
	}
	
	public $revisar_delegacion_precond = array ('Gatuf_Precondition::loginRequired');
	public function revisar_delegacion ($request, $match) {
		$managed = new DNS42_ManagedDomain ();
		$sql = new Gatuf_SQL ('dominio=%s AND user=%s', array ($match[1], $request->user->id));
		
		if (null === ($managed->getOne($sql->gen ()))) {
			throw new Gatuf_HTTP_Error404 ();
		}
		
		if ($managed->delegacion == 2) {
			$request->user->setMessage (1, __("The delegation for this zone was found and is correct. The zone is now active"));
		} else {
			$delegar = DNS42_RMQ::send_create_domain ($managed);
			
			$request->user->setMessage (1, __("The delegation check for this zone was scheduled, please wait at least 1 minute and refresh the page to see the result"));
		}
		
		$url = Gatuf_HTTP_URL_urlForView ('DNS42_Views_Managed::administrar', array ($managed->dominio));
		return new Gatuf_HTTP_Response_Redirect ($url);
	}
	
	public $agregar_registro_precond = array ('Gatuf_Precondition::loginRequired');
	public function agregar_registro ($request, $match) {
		$managed = new DNS42_ManagedDomain ();
		$sql = new Gatuf_SQL ('dominio=%s AND user=%s', array ($match[1], $request->user->id));
		
		if (null === ($managed->getOne($sql->gen ()))) {
			throw new Gatuf_HTTP_Error404 ();
		}
		
		if ($managed->delegacion != 2) {
			$request->user->setMessage (3, __('You can create records on the domain, but the zone will became active in the DNS until delegation works.'));
		}
		
		$allowed = array (
			'A' => 'DNS42_Form_Record_A',
			'AAAA' => 'DNS42_Form_Record_AAAA',
			'CNAME' => 'DNS42_Form_Record_CNAME',
			'MX' => 'DNS42_Form_Record_MX',
			'NS' => 'DNS42_Form_Record_NS',
			'TXT' => 'DNS42_Form_Record_TXT',
		);
		$type = mb_strtoupper ($match[2]);
		
		if ($type != $match[2]) {
			/* Redirigir a las URLs en mayúsculas */
			$url = Gatuf_HTTP_URL_urlForView ('DNS42_Views_Managed::agregar_registro', array ($managed->dominio, $type));
			return new Gatuf_HTTP_Response_Redirect ($url);
		}
		
		if (!array_key_exists ($type, $allowed)) {
			$url = Gatuf_HTTP_URL_urlForView ('DNS42_Views_Managed::administrar', array ($managed->dominio));
			return new Gatuf_HTTP_Response_Redirect ($url);
		}
		
		$form_type = $allowed[$type];
		$title = sprintf (__('Add record %s'), $type);
		$extra = array ('dominio' => $managed);
		if ($request->method == 'POST') {
			$form = new $form_type ($request->POST, $extra);
			
			if ($form->isValid ()) {
				$record = $form->save ();
				
				if ($managed->delegacion == 2) {
					$delegar = DNS42_RMQ::send_add_record ($record);
				}
				
				$url = Gatuf_HTTP_URL_urlForView ('DNS42_Views_Managed::administrar', array ($managed->dominio));
				return new Gatuf_HTTP_Response_Redirect ($url);
			}
		} else {
			$form = new $form_type (null, $extra);
		}
		
		return Gatuf_Shortcuts_RenderToResponse ('dns42/managed/agregar_registro.html',
		                                         array ('page_title' => $title,
		                                                'managed' => $managed,
		                                                'form' => $form),
		                                         $request);
	}
	
	public $eliminar_registro_precond = array ('Gatuf_Precondition::loginRequired');
	public function eliminar_registro ($request, $match) {
		$record = new DNS42_Record ();
		
		if (false === ($record->get($match[1]))) {
			throw new Gatuf_HTTP_Error404 ();
		}
		
		$managed = $record->get_dominio ();
		$user = $managed->get_user ();
		
		if ($request->user->id != $user->id) {
			throw new Gatuf_HTTP_Error404 ();
		}
		
		if ($record->locked) {
			$url = Gatuf_HTTP_URL_urlForView ('DNS42_Views_Managed::administrar', array ($managed->dominio));
			return new Gatuf_HTTP_Response_Redirect ($url);
		}
		
		if ($request->method == 'POST') {
			if ($managed->delegacion == 2) {
				DNS42_RMQ::send_del_record ($record);
			}
			
			$record->delete ();
			
			$url = Gatuf_HTTP_URL_urlForView ('DNS42_Views_Managed::administrar', array ($managed->dominio));
			return new Gatuf_HTTP_Response_Redirect ($url);
		}
		
		return Gatuf_Shortcuts_RenderToResponse ('dns42/managed/eliminar_registro.html',
		                                         array ('page_title' => __('Delete record'),
		                                                'record' => $record,
		                                                'managed' => $managed),
		                                         $request);
	}
}

