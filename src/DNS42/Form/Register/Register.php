<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of InDefero, an open source project management application.
# Copyright (C) 2008 Céondo Ltd and contributors.
#
# InDefero is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# InDefero is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
#
# ***** END LICENSE BLOCK ***** */

/**
 * Create a new user account.
 *
 */
class DNS42_Form_Register_Register extends Gatuf_Form {
	public function initFields($extra=array()) {
		$login = '';
		if (isset($extra['initial']['login'])) {
			$login = $extra['initial']['login'];
		}
		$this->fields['login'] = new Gatuf_Form_Field_Varchar (
			array (
				'required' => true,
				'label' => __('Your login name'),
				'max_length' => 15,
				'min_length' => 3,
				'initial' => $login,
				'help_text' => '',
				'widget_attrs' => array(
					'maxlength' => 15,
					'size' => 10,
				),
		));
		$this->fields['email'] = new Gatuf_Form_Field_Email (
			array (
				'required' => true,
				'label' => __('Your email address'),
				'initial' => '',
				'help_text' => __('Right now, the email is used for gravatar.'),
		));
		
		$this->fields['first_name'] = new Gatuf_Form_Field_Varchar (
			array('required' => false,
				'label' => __('First name'),
				'initial' => '',
				'widget_attrs' => array(
					'maxlength' => 50,
					'size' => 15,
				),
		));
		$this->fields['last_name'] = new Gatuf_Form_Field_Varchar (
			array(
				'required' => true,
				'label' => __('Last name'),
				'initial' => '',
				'widget_attrs' => array(
					'maxlength' => 50,
					'size' => 15,
				),
		));
		
		$this->fields['password'] = new Gatuf_Form_Field_Varchar (
			array(
				'required' => true,
				'label' => __('Your password'),
				'initial' => '',
				'widget' => 'Gatuf_Form_Widget_PasswordInput',
				'help_text' => __('Your password must be hard for other people to find it, but easy for you to remember.'),
				'widget_attrs' => array (
					'maxlength' => 50,
					'size' => 15,
				),
		));
		
		$this->fields['password2'] = new Gatuf_Form_Field_Varchar(
			array(
				'required' => true,
				'label' => __('Confirm your password'),
				'initial' => '',
				'widget' => 'Gatuf_Form_Widget_PasswordInput',
				'widget_attrs' => array(
					'maxlength' => 50,
					'size' => 15,
				),
		));
		
		$this->fields['terms'] = new Gatuf_Form_Field_Boolean (
			array (
				'required' => true,
				'label' => __('I accept the terms and conditions of this site'),
				'initial' => false,
		));
	}

	/**
	 * Validate the interconnection in the form.
	 */
	public function clean_login() {
		$this->cleaned_data['login'] = mb_strtolower(trim($this->cleaned_data['login']));
		if (preg_match('/[^A-Za-z0-9_]/', $this->cleaned_data['login'])) {
			throw new Gatuf_Form_Invalid(__('The login may only have letters, numbers or _'));
		}
		$model = Gatuf::config('gatuf_custom_user', 'Gatuf_User');
		$guser = new $model();
		$sql = new Gatuf_SQL('login=%s', $this->cleaned_data['login']);
		if ($guser->getCount (array('filter' => $sql->gen())) > 0) {
			throw new Gatuf_Form_Invalid(sprintf(__('The login name "%s" is already in use, please choose a different one'), $this->cleaned_data['login']));
		}
		return $this->cleaned_data['login'];
	}

	/**
	 * Check the terms.
	 */
	public function clean_terms() {
		if (!$this->cleaned_data['terms']) {
			throw new Gatuf_Form_Invalid(__('We know that is boring, but you must accept the terms and conditions'));
		}
		return $this->cleaned_data['terms'];
	}

	function clean_email() {
		$this->cleaned_data['email'] = mb_strtolower(trim($this->cleaned_data['email']));
		$model = Gatuf::config('gatuf_custom_user', 'Gatuf_User');
		$guser = new $model();
		$sql = new Gatuf_SQL('email=%s', $this->cleaned_data['email']);
		if ($guser->getCount(array('filter' => $sql->gen())) > 0) {
			throw new Gatuf_Form_Invalid(sprintf(__('The email "%s" is already in use'), $this->cleaned_data['email']));
		}
		return $this->cleaned_data['email'];
	}
	
	public function clean() {
		if ($this->cleaned_data['password'] != $this->cleaned_data['password2']) {
			throw new Gatuf_Form_Invalid(__('The two passwords must be the same.'));
		}
		
		return $this->cleaned_data;
	}

	/**
	 * Save the model in the database.
	 *
	 * @param bool Commit in the database or not. If not, the object
	 *			 is returned but not saved in the database.
	 * @return Object Model with data set from the form.
	 */
	function save($commit=true) {
		if (!$this->isValid()) {
			throw new Exception (__('Cannot save an invalid form.'));
		}
		$model = Gatuf::config('gatuf_custom_user', 'Gatuf_User');
		$user = new $model();
		$user->first_name = $this->cleaned_data['first_name'];
		$user->last_name = $this->cleaned_data['last_name'];
		$user->setPassword ($this->cleaned_data ['password']);
		$user->login = $this->cleaned_data['login'];
		$user->email = $this->cleaned_data['email'];
		$user->active = true;
		$user->administrator = false;
		$user->create();
		//self::sendVerificationEmail($user);
		return $user;
	}

	public static function sendVerificationEmail($user) {
		Gatuf::loadFunction('Gatuf_HTTP_URL_urlForView');
		$from_email = Gatuf::config('from_email');
		$cr = new Gatuf_Crypt(md5(Gatuf::config('secret_key')));
		$encrypted = trim($cr->encrypt($user->email.':'.$user->id), '~');
		$key = substr(md5(Gatuf::config('secret_key').$encrypted), 0, 2).$encrypted;
		$url = Gatuf::config('url_base').Gatuf_HTTP_URL_urlForView('DNS42_Views_Register::confirmation', array($key), array(), false);
		$urlik = Gatuf::config('url_base').Gatuf_HTTP_URL_urlForView('DNS42_Views_Register::inputKey', array(), array(), false);
		$context = new Gatuf_Template_Context (array (
			'key' => $key,
			'url' => $url,
			'urlik' => $urlik,
			'user'=> $user,
		));
		$tmpl = new Gatuf_Template('dns42/register/confirmation-email.txt');
		$text_email = $tmpl->render($context);
		$email = new Gatuf_Mail($from_email, $user->email,
							   __('Free DNS Management - Confirm your account'));
		$email->addTextMessage($text_email);
		$email->sendMail();
	}
}
