<?php

Gatuf::loadFunction('Gatuf_HTTP_URL_urlForView');
Gatuf::loadFunction('DNS42_Shortcuts_RenderToResponse');

class DNS42_Views_Index {
	public function index ($request, $match) {
		$request->active_tab = 'home';
		return DNS42_Shortcuts_RenderToResponse ('dns42/index.html', array ('page_title' => __('Start page')), $request);
	}
}

