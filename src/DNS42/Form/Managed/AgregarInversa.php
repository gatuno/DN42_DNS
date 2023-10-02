<?php

class DNS42_Form_Managed_AgregarInversa extends Gatuf_Form {
	private $user;
	private $key;
	public function initFields($extra=array()) {
		$this->user = $extra['user'];
		$this->key = $extra['key'];
		$this->fields['prefix'] = new Gatuf_Form_Field_Varchar (
			array (
				'required' => true,
				'label' => __('Prefix'),
				'help_text' => 'fd42:0:c0ff:ee::/64 or 172.20.1.0/24',
				'initial' => '',
		));
	}
	
	public function clean_prefix () {
		$prefix = $this->cleaned_data['prefix'];
		
		$prefix = rtrim (trim ($prefix));
		
		/* Manipular el dominio primero */
		$slash = strrpos ($prefix, '/');
		$mask = substr ($prefix, $slash + 1);
		$ip = substr ($prefix, 0, $slash);
		
		$type = 0;
		if (filter_var ($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) == true) {
			$type = 4;
		} else if (filter_var ($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) == true) {
			$type = 6;
		}
		
		if ($type == 0) {
			throw new Gatuf_Form_Invalid (__('Malformed prefix'));
		}
		
		$int_mask = ((int) $mask);
		
		if ($type == 4 && $int_mask < 24) {
			/* No permitir prefijos mayores a /24 */
			throw new Gatuf_Form_Invalid (__('Prefix too big. Only prefixes >= 24 are allowed'));
		} else if ($type == 6 && ($int_mask < 32 || $int_mask > 64)) {
			throw new Gatuf_Form_Invalid (__('Prefix too big or too small. Only prefixes >= 32 and <= 64 are allowed'));
		} else if ($type == 6 && ($int_mask % 4) != 0) {
			throw new Gatuf_Form_Invalid (__('Malformed prefix. Perhaps you are not on the subnet boundary?'));
		}
		
		if ($type == 4 && $int_mask > 24) {
			/* Validar que el último octeto sea la red */
			$last_octeto = ((int) substr ($ip, strrpos ($ip, '.') + 1));
			
			$pre_mask = (1 << (8 - ($int_mask - 24))) - 1;
			$pre_mask = ~$pre_mask;
			$net = $last_octeto & $pre_mask;
			
			if ($last_octeto != $net) {
				throw new Gatuf_Form_Invalid (__('Malformed prefix. Perhaps you are not on the subnet boundary?'));
			}
		} else if ($type == 6) {
			$hex = unpack ("H*hex", inet_pton ($ip));
			$hex = $hex['hex'];
			
			$len = (128 - $int_mask) / 4;
			$masked = $hex;
			for ($g = 0; $g < $len; $g++) {
				$masked = substr_replace ($masked, '0', (32 - $g) - 1, 1);
			}
			
			if ($hex != $masked) {
				throw new Gatuf_Form_Invalid (__('Malformed prefix. Perhaps you are not on the subnet boundary?'));
			}
		}
		
		/* Definir la zona */
		if ($type == 4) {
			/* 0/27.122.22.172.in-addr.arpa */
			$dot_one = strpos ($ip, '.');
			$dot_two = strpos ($ip, '.', $dot_one + 1);
			$dot_three = strpos ($ip, '.', $dot_two + 1);
			$dot_four = strpos ($ip, '.', $dot_three + 1);
			$first_octeto = substr ($ip, 0, $dot_one);
			$second_octeto = substr ($ip, $dot_one + 1, $dot_two - $dot_one - 1);
			$third_octeto = substr ($ip, $dot_two + 1, $dot_three - $dot_two - 1);
			$last_octeto = substr ($ip, $dot_three + 1);
			if ($int_mask > 24) {
				$pre_mask = (1 << (8 - ($int_mask - 24))) - 1;
				$pre_mask = ~$pre_mask;
				$net = $last_octeto & $pre_mask;
				
				$dominio = sprintf ("%s/%s.%s.%s.%s.in-addr.arpa", $net, $int_mask, $third_octeto, $second_octeto, $first_octeto);
			} else {
				$dominio = sprintf ("%s.%s.%s.in-addr.arpa", $third_octeto, $second_octeto, $first_octeto);
			}
		} else {
			/* Pendiente para IPv6 */
			$hex = unpack ("H*hex", inet_pton ($ip));
			$hex = $hex['hex'];
			$dominio = 'ip6.arpa';
			
			$len = $int_mask / 4;
			for ($g = 0; $g < $len; $g++) {
				$dominio = substr ($hex, $g, 1).'.'.$dominio;
			}
		}
		
		$sql = new Gatuf_SQL ('dominio=%s', $dominio);
		$managed = Gatuf::factory ('DNS42_ManagedDomain')->getList (array ('filter' => $sql->gen (), 'count' => true));
		
		if ($managed > 0) {
			throw new Gatuf_Form_Invalid (__('This DNS zone already exists in this server'));
		}
		
		return $prefix;
	}
	
	public function save ($commit = true) {
		if (!$this->isValid()) {
			throw new Exception (__('Cannot save an invalid form.'));
		}
		
		$prefix = $this->cleaned_data['prefix'];
		
		/* Manipular el dominio primero */
		$slash = strrpos ($prefix, '/');
		$mask = substr ($prefix, $slash + 1);
		$ip = substr ($prefix, 0, $slash);
		
		if (filter_var ($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) == true) {
			$type = 4;
		} else if (filter_var ($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) == true) {
			$type = 6;
		}
		
		$int_mask = ((int) $mask);
		
		$prefix = '';
		/* Definir la zona */
		if ($type == 4) {
			/* 0-31.122.22.172.in-addr.arpa */
			$dot_one = strpos ($ip, '.');
			$dot_two = strpos ($ip, '.', $dot_one + 1);
			$dot_three = strpos ($ip, '.', $dot_two + 1);
			$dot_four = strpos ($ip, '.', $dot_three + 1);
			$first_octeto = substr ($ip, 0, $dot_one);
			$second_octeto = substr ($ip, $dot_one + 1, $dot_two - $dot_one - 1);
			$third_octeto = substr ($ip, $dot_two + 1, $dot_three - $dot_two - 1);
			$last_octeto = substr ($ip, $dot_three + 1);
			if ($int_mask > 24) {
				$pre_mask = (1 << (8 - ($int_mask - 24))) - 1;
				$pre_mask = ~$pre_mask;
				$net = $last_octeto & $pre_mask;
				
				$prefix = sprintf ("%s.%s.%s.%s_%s", $first_octeto, $second_octeto, $third_octeto, $net, $int_mask);
				$dominio = sprintf ("%s/%s.%s.%s.%s.in-addr.arpa", $net, $int_mask, $third_octeto, $second_octeto, $first_octeto);
			} else {
				$prefix = sprintf ("%s.%s.%s.0_%s", $first_octeto, $second_octeto, $third_octeto, $int_mask);
				$dominio = sprintf ("%s.%s.%s.in-addr.arpa", $third_octeto, $second_octeto, $first_octeto);
			}
		} else {
			/* Pendiente para IPv6 */
			$hex = unpack ("H*hex", inet_pton ($ip));
			$hex = $hex['hex'];
			$dominio = 'ip6.arpa';
			
			$prefix = sprintf ("%s_%s", $ip, $int_mask);
			
			$len = $int_mask / 4;
			for ($g = 0; $g < $len; $g++) {
				$dominio = substr ($hex, $g, 1).'.'.$dominio;
			}
		}
		
		$managed = new DNS42_ManagedDomain ();
		
		$managed->dominio = $dominio;
		$managed->reversa = true;
		$managed->maestra = true;
		$managed->tipo_reversa = $type;
		$managed->owner = $this->user;
		$managed->key = $this->key;
		$managed->good_delegation = false;
		$managed->prefix = $prefix;
		
		if ($commit) {
			$managed->create ();
		}
		
		return $managed;
	}
}
