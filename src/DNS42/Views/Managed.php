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
}

