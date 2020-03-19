<?php

Gatuf::loadFunction('Gatuf_HTTP_URL_urlForView');
Gatuf::loadFunction('Gatuf_Shortcuts_RenderToResponse');

class DNS42_Views_Domains {
	public function index ($request, $match) {
		$domains = Gatuf::factory ('DNS42_Domain')->getList ();
		
		return Gatuf_Shortcuts_RenderToResponse ('dns42/domains/index.html',
		                                         array('page_title' => __('Domains'),
		                                         'domains' => $domains),
		                                         $request);
	}
	
	public function ver ($request, $match) {
		$sql = new Gatuf_SQL ('dominio=%s', $match[1]);
		$dominio = Gatuf::factory ('DNS42_Domain')->getOne ($sql->gen ());
		
		if (null === $dominio) {
			throw new Gatuf_HTTP_Error404 ();
		}
		
		$page_title = sprintf (__('Domain %s'), $dominio->dominio);
		
		$ns = $dominio->get_ns_list ();
		
		return Gatuf_Shortcuts_RenderToResponse ('dns42/domains/dominio.html',
		                                         array('page_title' => $page_title,
		                                         'ns' => $ns,
		                                         'dominio' => $dominio),
		                                         $request);
	}
	
	public function programar_check_ns ($request, $match) {
		$ns = new DNS42_NameServer ();
		
		if ($ns->get ($match[1]) === false) {
			throw new Gatuf_HTTP_Error404 ();
		}
		
		$sql = new Gatuf_SQL ('ns=%s', $ns->id);
		$ns_check = Gatuf::factory ('DNS42_NSCheck')->getOne ($sql->gen ());
		
		if ($ns_check == null) {
			$ns->open_transfer4 = 0;
			$ns->open_transfer6 = 0;
			$ns->autoritative4 = 0;
			$ns->autoritative6 = 0;
			$ns->parent_match4 = 0;
			$ns->parent_match6 = 0;
			$ns->soa4 = '';
			$ns->soa6 = '';
			$ns->ns_list4 = '';
			$ns->ns_list6 = '';
			$ns->response4 = 0;
			$ns->response6 = 0;
			$ns->update ();
			
			$ns_check = new DNS42_NSCheck ();
			$ns_check->ns = $ns;
			$ns_check->prioridad = 50;
			
			$ns_check->create ();
		}
		
		$server = $ns->get_server ();
		
		$sql = new Gatuf_SQL ('server=%s', $server->id);
		$ping_check = Gatuf::factory ('DNS42_PingCheck')->getOne ($sql->gen ());
		
		if ($ping_check == null) {
			$server->ping4 = '';
			$server->ping6 = '';
			$server->estado = 0;
			
			$server->update ();
			
			$ping_check = new DNS42_PingCheck ();
			$ping_check->server = $server;
			$ping_check->prioridad = 50;
			$ping_check->estado = 0;
			
			$ping_check->create ();
		}
		
		$url = Gatuf_HTTP_URL_urlForView ('DNS42_Views_Domains::ver', $ns->get_dominio ()->dominio);
		return new Gatuf_HTTP_Response_Redirect ($url);
	}
	
	public function ver_server ($request, $match) {
		$sql = new Gatuf_SQL ('nombre=%s', $match[1]);
		$server = Gatuf::factory ('DNS42_Server')->getOne ($sql->gen ());
		
		if (null === $server) {
			throw new Gatuf_HTTP_Error404 ();
		}
		
		$page_title = sprintf (__('Server %s'), $server->nombre);
		
		$ns = $server->get_domains_list ();
		
		return Gatuf_Shortcuts_RenderToResponse ('dns42/domains/ns.html',
		                                         array('page_title' => $page_title,
		                                         'ns' => $ns,
		                                         'server' => $server),
		                                         $request);
	}
	
	public function programar_check_ping ($request, $match) {
		$sql = new Gatuf_SQL ('nombre=%s', $match[1]);
		$server = Gatuf::factory ('DNS42_Server')->getOne ($sql->gen ());
		
		if (null === $server) {
			throw new Gatuf_HTTP_Error404 ();
		}
		
		$sql = new Gatuf_SQL ('server=%s', $server->id);
		$ping_check = Gatuf::factory ('DNS42_PingCheck')->getOne ($sql->gen ());
		
		if ($ping_check == null) {
			$server->ping4 = '';
			$server->ping6 = '';
			$server->estado = 0;
			
			$server->update ();
			
			$ping_check = new DNS42_PingCheck ();
			$ping_check->server = $server;
			$ping_check->prioridad = 50;
			$ping_check->estado = 0;
			
			$ping_check->create ();
		}
		
		$url = Gatuf_HTTP_URL_urlForView ('DNS42_Views_Domains::ver_server', $server->nombre);
		return new Gatuf_HTTP_Response_Redirect ($url);
	}
	
	public function programar_check_domain ($request, $match) {
		$sql = new Gatuf_SQL ('dominio=%s', $match[1]);
		$dominio = Gatuf::factory ('DNS42_Domain')->getOne ($sql->gen ());
		
		if (null === $dominio) {
			throw new Gatuf_HTTP_Error404 ();
		}
		
		/* Recoger la lista de NS asociados a este dominio */
		$nss = $dominio->get_ns_list ();
		
		foreach ($nss as $ns) {
			$sql = new Gatuf_SQL ('ns=%s', $ns->id);
			$ns_check = Gatuf::factory ('DNS42_NSCheck')->getOne ($sql->gen ());
			
			if ($ns_check == null) {
				$ns->open_transfer4 = 0;
				$ns->open_transfer6 = 0;
				$ns->autoritative4 = 0;
				$ns->autoritative6 = 0;
				$ns->parent_match4 = 0;
				$ns->parent_match6 = 0;
				$ns->soa4 = '';
				$ns->soa6 = '';
				$ns->ns_list4 = '';
				$ns->ns_list6 = '';
				$ns->response4 = 0;
				$ns->response6 = 0;
				
				$ns->update ();
			
				$ns_check = new DNS42_NSCheck ();
				$ns_check->ns = $ns;
				$ns_check->prioridad = 50;
			
				$ns_check->create ();
			}
			
			$server = $ns->get_server ();
			$sql = new Gatuf_SQL ('server=%s', $server->id);
			$ping_check = Gatuf::factory ('DNS42_PingCheck')->getOne ($sql->gen ());
		
			if ($ping_check == null) {
				$server->ping4 = '';
				$server->ping6 = '';
				$server->estado = 0;
			
				$server->update ();
			
				$ping_check = new DNS42_PingCheck ();
				$ping_check->server = $server;
				$ping_check->prioridad = 50;
				$ping_check->estado = 0;
			
				$ping_check->create ();
			}
		}
		
		$url = Gatuf_HTTP_URL_urlForView ('DNS42_Views_Domains::ver', $ns->get_dominio ()->dominio);
		return new Gatuf_HTTP_Response_Redirect ($url);
	}
}

