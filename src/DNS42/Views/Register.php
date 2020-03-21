<?php

Gatuf::loadFunction('Gatuf_HTTP_URL_urlForView');
Gatuf::loadFunction('Gatuf_Shortcuts_RenderToResponse');

class DNS42_Views_Register {
	function register ($request, $match) {
		$title = __('Create your account');
		if ($request->method == 'POST') {
			$form = new DNS42_Form_Register_Register ($request->POST);
			if ($form->isValid ()) {
				$user = $form->save();
				
				$request->user = $user;
				$request->session->clear();
				$request->session->setData('login_time', gmdate('Y-m-d H:i:s'));
				$user->last_login = gmdate('Y-m-d H:i:s');
				$user->update();
				$request->user->setMessage(1, __('Welcome!'));
				$url = Gatuf_HTTP_URL_urlForView('DNS42_Views_Index::index');
				return new Gatuf_HTTP_Response_Redirect($url);
			}
		} else {
			$params = array ();
			if (isset($request->GET['login'])) {
				$params['initial'] = array('login' => $request->GET['login']);
			}
			$form = new DNS42_Form_Register_Register (null, $params);
		}
		$context = new Gatuf_Template_Context (array());
		$tmpl = new Gatuf_Template('dns42/register/terms.html');
		$terms = Gatuf_Template::markSafe($tmpl->render($context));
		return Gatuf_Shortcuts_RenderToResponse('dns42/register/index.html', 
		                                         array ('page_title' => $title,
		                                         'form' => $form,
		                                         'terms' => $terms),
		                                         $request);
	}
}
