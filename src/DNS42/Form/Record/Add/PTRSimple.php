<?php

class DNS42_Form_Record_Add_PTRSimple extends Gatuf_Form {
	private $dominio;
	public function initFields($extra=array()) {
		$this->dominio = $extra['dominio'];
		if ($this->dominio->reversa == false) {
			throw new Exception (__('Invalid Domain for PTRSimple'));
		}
		$this->fields['IP'] = new Gatuf_Form_Field_Varchar (
			array (
				'required' => true,
				'label' => __('IP'),
				'help_text' => __("The IP address, please omit the prefix"),
				'initial' => '',
				'widget_attrs' => array ('autocomplete' => 'off'),
		));
		
		$this->fields['hostname'] = new Gatuf_Form_Field_Varchar (
			array (
				'required' => true,
				'label' => __('Hostname'),
				'help_text' => __("A hostname should be valid and may only contain A-Z, a-z, 0-9, _, -, and .."),
				'initial' => '',
				'widget_attrs' => array ('autocomplete' => 'off', 'size' => 60),
		));
	}
	
	public function clean () {
		/* Limpieza general de los registros. */
		$IP = $this->cleaned_data['IP'];
		$try_without_prefix = false;
		$prefix = $this->dominio->get_reverse_prefix ();
		
		$full_ip = $prefix.$IP;
		
		if ($this->dominio->tipo_reversa == 4) {
			if (filter_var ($full_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) == false) {
				$try_without_prefix = true;
			}
		} else if ($this->dominio->tipo_reversa == 6) {
			if (filter_var ($full_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) == false) {
				$try_without_prefix = true;
			}
		}
		
		if ($try_without_prefix) {
			if ($this->dominio->tipo_reversa == 4) {
				if (filter_var ($IP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) == false) {
					throw new Gatuf_Form_Invalid (__('Invalid IPv4 Address'));
				}
			} else if ($this->dominio->tipo_reversa == 6) {
				if (filter_var ($IP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) == false) {
					throw new Gatuf_Form_Invalid (__('Invalid IPv6 Address'));
				}
			}
			$full_ip = $IP;
		}
		/* Siguiente validación, estar seguro de que esta dirección IP cae dentro del prefijo de la reversa */
		if ($this->dominio->tipo_reversa == 4) {
			if (DNS42_Utils::checkIp4 ($full_ip, $this->dominio->prefix_nice ()) == false) {
				throw new Gatuf_Form_Invalid (__('The specified IP address falls outside the reverse zone'));
			}
		} else if ($this->dominio->tipo_reversa == 6) {
			if (DNS42_Utils::checkIp6 ($full_ip, $this->dominio->prefix_nice ()) == false) {
				throw new Gatuf_Form_Invalid (__('The specified IP address falls outside the reverse zone'));
			}
		}
		$this->cleaned_data['IP'] = $full_ip;
		
		$hostname = $this->cleaned_data['hostname'];
		
		if (filter_var ($hostname, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) == false) {
			throw new Gatuf_Form_Invalid (__('Invalid domain name'));
		}
		
		/* Para el hostname solo asegurarnos que tenga el punto al final */
		$hostname = trim ($hostname);
		
		if (substr ($hostname, -1) != '.') {
			$hostname = $hostname . '.';
		}
		
		$this->cleaned_data['hostname'] = $hostname;
		
		return $this->cleaned_data;
	}
	
	public function save ($commit = true) {
		if (!$this->isValid()) {
			throw new Exception (__('Cannot save an invalid form.'));
		}
		
		$record = new DNS42_Record ();
		
		$record->dominio = $this->dominio;
		/* Armar el nombre del registro.
		 * En el caso de IPv4 solo tomamos el último octeto de la IP y le agregamos el dominio
		 * En el caso de IPv6, escribimos TODA la IP invertida */
		if ($this->dominio->tipo_reversa == 4) {
			$toks = explode ('.', $this->cleaned_data['IP']);
			$name = $toks[3].'.'.$this->dominio->dominio;
		} else if ($this->dominio->tipo_reversa == 6) {
			$hex = unpack ("H*hex", inet_pton ($this->cleaned_data['IP']));
			$hex = array_reverse (str_split ($hex['hex']));
			$name = implode ('.', $hex).'.ip6.arpa';
		}
		$record->name = $name;
		$record->type = 'PTR';
		$record->ttl = 86400;
		$record->rdata = $this->cleaned_data ['hostname'];
		
		if ($commit) {
			$record->create ();
		}
		
		return $record;
	}
}
