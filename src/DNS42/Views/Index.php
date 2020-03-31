<?php

Gatuf::loadFunction('Gatuf_HTTP_URL_urlForView');
Gatuf::loadFunction('DNS42_Shortcuts_RenderToResponse');

class DNS42_Views_Index {
	public function index ($request, $match) {
		$request->active_tab = 'home';
		return DNS42_Shortcuts_RenderToResponse ('dns42/index.html', array ('page_title' => __('Start page')), $request);
	}
	
	public function cambiar_tema ($request, $match) {
		$tema = $match[1];
		
		if (!empty($request->REQUEST['_redirect_after'])) {
			$success_url = $request->REQUEST['_redirect_after'];
		} else {
			$success_url = Gatuf::config('dns42_base').Gatuf::config ('login_success_url', '/');
		}
		
		$redirect = new Gatuf_HTTP_Response_Redirect ($success_url);
		
		if (in_array ($tema, Gatuf::config ('themes', array ('default')))) {
			$redirect->cookies[Gatuf::config('theme_cookie', 'dn42_theme')] = $tema;
		}
		return $redirect;	
	}
}

