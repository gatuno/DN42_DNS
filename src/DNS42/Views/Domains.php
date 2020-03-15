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
}

