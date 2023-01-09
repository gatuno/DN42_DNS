<?php

class DNS42_Form_Managed_Agregar extends Gatuf_Form {
	private $user;
	private $key;
	public function initFields($extra=array()) {
		$this->user = $extra['user'];
		$this->key = $extra['key'];
		$this->fields['domain'] = new Gatuf_Form_Field_Varchar (
			array (
				'required' => true,
				'label' => __('Domain Name'),
				'help_text' => '',
				'initial' => '',
		));
	}
	
	public function clean_domain () {
		$dominio = $this->cleaned_data['domain'];
		
		$dominio = rtrim (trim ($dominio), '.');
		
		if (filter_var ($dominio, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) == false) {
			throw new Gatuf_Form_Invalid (__('Invalid domain name'));
		}
		
		$sql = new Gatuf_SQL ('dominio=%s', $dominio);
		$managed = Gatuf::factory ('DNS42_ManagedDomain')->getList (array ('filter' => $sql->gen (), 'count' => true));
		
		if ($managed > 0) {
			throw new Gatuf_Form_Invalid (__('This DNS zone already exists in this server'));
		}
		
		$ending = substr ($dominio, -5);
		
		if ($ending != '.dn42') {
			throw new Gatuf_Form_Invalid (__('Only .dn42 ending domains are allowed at this moment'));
		}
		
		return $dominio;
	}
	
	public function save ($commit = true) {
		if (!$this->isValid()) {
			throw new Exception (__('Cannot save an invalid form.'));
		}
		
		$managed = new DNS42_ManagedDomain ();
		
		$managed->dominio = $this->cleaned_data['domain'];
		$managed->owner = $this->user;
		$managed->key = $this->key;
		$managed->good_delegation = false;
		
		if ($commit) {
			$managed->create ();
		}
		
		return $managed;
	}
}
