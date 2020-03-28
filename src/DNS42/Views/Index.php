<?php

Gatuf::loadFunction('Gatuf_HTTP_URL_urlForView');
Gatuf::loadFunction('Gatuf_Shortcuts_RenderToResponse');

class DNS42_Views_Index {
	public function index ($request, $match) {
		$values = array( 'page_title' => __('Start page'),
			'nsip4' => '20',
			'nsip6'	=> '8',
			'nsip46' => '70',
			'onip4' => 10,
			'offip4' => 90,
			'onip6'	=> 8,
			'offip6'=> 92,
			'onip46' => 20,
			'offip46' => 80);

		return Gatuf_Shortcuts_RenderToResponse ('dns42/index.html', $values, $request);
	}
}

