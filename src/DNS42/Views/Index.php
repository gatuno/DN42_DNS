<?php

Gatuf::loadFunction('Gatuf_HTTP_URL_urlForView');
Gatuf::loadFunction('Gatuf_Shortcuts_RenderToResponse');

class DNS42_Views_Index {
	public function index ($request, $match) {
		$domains = Gatuf::factory ('DNS42_Domain')->getList ();

		$sql = new Gatuf_SQL ('ping4=%s', 'failed');
		$offip4 = Gatuf::factory('DNS42_Server')->getList (array ('filter' => $sql->gen (), 'count' => true));
		$sql = new Gatuf_SQL ('ping4!=%s', 'failed');
		$onip4 = Gatuf::factory('DNS42_Server')->getList (array ('filter' => $sql->gen (), 'count' => true));

		$sql = new Gatuf_SQL ('ping6=%s', 'failed');
		$offip6 = Gatuf::factory('DNS42_Server')->getList (array ('filter' => $sql->gen (), 'count' => true));
		$sql = new Gatuf_SQL ('ping6!=%s', 'failed');
		$onip6 = Gatuf::factory('DNS42_Server')->getList (array ('filter' => $sql->gen (), 'count' => true));
		$sql = new Gatuf_SQL ('ipv6=%s', '');
		$nsip4 = Gatuf::factory('DNS42_Server')->getList (array ('filter' => $sql->gen (), 'count' => true));
		$sql = new Gatuf_SQL ('ipv4=%s', '');
		$nsip6 = Gatuf::factory('DNS42_Server')->getList (array ('filter' => $sql->gen (), 'count' => true));
		$sql = new Gatuf_SQL ('ipv4!=%s and ipv6!=%s', ['', '']);
		$nsip46 = Gatuf::factory('DNS42_Server')->getList (array ('filter' => $sql->gen (), 'count' => true));


		$values = array( 'page_title' => __('Start page'),
			'domains' => count($domains),
			'nsip4' => $nsip4 * 100 / ($nsip4 + $nsip6 + $nsip46) ,
			'nsip6'	=> $nsip6 * 100 / ($nsip4 + $nsip6 + $nsip46),
			'nsip46' => $nsip46 * 100 / ($nsip4 + $nsip6 + $nsip46),
			'onip4' => $onip4 * 100 / ($onip4 + $offip4),
			'offip4' => $offip4 * 100 / ($onip4 + $offip4),
			'onip6' => $onip6 * 100 / ($onip6 + $offip6),
			'offip6' => $offip6 * 100 / ($onip6 + $offip6));

		return Gatuf_Shortcuts_RenderToResponse ('dns42/index.html', $values, $request);
	}
}

