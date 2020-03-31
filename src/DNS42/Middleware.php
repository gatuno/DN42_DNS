<?php

class DNS42_Middleware {
	function process_theme (&$request) {
		$theme = false;
		
		if (!empty ($request->session)) {
			$theme = $request->session->getData ('dn42_theme', false);
			
			if ($theme !== false and !in_array ($theme, Gatuf::config ('themes', array ('default')))) {
				$theme = false;
			}
		}
		
		if ($theme === false && !empty ($request->COOKIE[Gatuf::config('theme_cookie', 'dn42_theme')])) {
			$theme = $request->COOKIE[Gatuf::config('theme_cookie', 'dn42_theme')];
			if ($theme and !in_array ($theme, Gatuf::config ('themes', array ('default')))) {
				$theme = false;
			}
		}
		
		$request->theme = $theme;
	}
	
	function process_request(&$request) {
		$request->active_tab = 'none';
		
		$this->process_theme ($request);
		
		return false;
	}
}

function DNS42_Middleware_ContextPreProcessor ($request) {
	return array ('active_tab' => $request->active_tab);
}

