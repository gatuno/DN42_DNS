<?php

class DNS42_Middleware_ActiveTab {
	function process_request(&$request) {
		$theme = 'default';
		
		if (!empty ($request->session)) {
			$theme = $request->session->getData ('dn42_theme', false);
			
			if ($theme and !in_array ($theme, Gatuf::config ('themes', array ('default')))) {
				$theme = 'default';
			}
		}
		
		if ($theme == 'default' && !empty ($request->COOKIE[Gatuf::config('theme_cookie', 'dn42_theme')])) {
			$theme = $request->COOKIE[Gatuf::config('theme_cookie', 'dn42_theme')];
			if ($theme and !in_array ($theme, Gatuf::config ('themes', array ('default')))) {
				$theme = 'default';
			}
		}
		
		$request->theme = $theme;
		
		return false;
	}
}
