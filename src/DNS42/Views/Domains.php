<?php

Gatuf::loadFunction('Gatuf_HTTP_URL_urlForView');
Gatuf::loadFunction('Gatuf_Shortcuts_RenderToResponse');

class DNS42_Views_Domains {
	public function index ($request, $match) {
		$domains = Gatuf::factory ('DNS42_TopLevelDomain')->getList ();
		
		return Gatuf_Shortcuts_RenderToResponse ('dns42/domains/index.html',
		                                         array('page_title' => __('Domains'),
		                                         'domains' => $domains),
		                                         $request);
	}
	
	public function ver_nameserver ($request, $match) {
		$sql = new Gatuf_SQL ('nombre=%s', $match[1]);
		$ns = Gatuf::factory ('DNS42_NameServer')->getOne ($sql->gen ());
		
		if (null === $ns) {
			throw new Gatuf_HTTP_Error404 ();
		}
		
		$page_title = sprintf (__('Name server %s'), $ns->nombre);
		
		$domains = $ns->get_dominios_list ();
		
		return Gatuf_Shortcuts_RenderToResponse ('dns42/domains/ns.html',
		                                         array('page_title' => $page_title,
		                                         'domains' => $domains,
		                                         'ns' => $ns),
		                                         $request);
	}
}

