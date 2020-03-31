<?php

class DNS42_Middleware_ActiveTab {
	function process_request(&$request) {
		$request->active_tab = 'none';
		
		return false;
	}
}

function DNS42_Middleware_ActiveTab_ContextPreProcessor ($request) {
	return array ('active_tab' => $request->active_tab);
}

