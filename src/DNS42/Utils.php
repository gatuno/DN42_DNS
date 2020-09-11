<?php

class DNS42_Utils {
	/**
	 * Compares two IPv4 addresses.
	 * In case a subnet is given, it checks if it contains the request IP.
	 *
	 * @param string $subnet IPv4 address or subnet in CIDR notation
	 *
	 * @return bool Whether the request IP matches the IP, or whether the request IP is within the CIDR subnet
	 */
	public static function checkIp4(string $requestIp, string $subnet) {
		if (!filter_var($requestIp, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4)) {
			return false;
		}

		if (false !== strpos($subnet, '/')) {
			list($address, $netmask) = explode('/', $subnet, 2);

			if ('0' === $netmask) {
				return filter_var($address, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4);
			}

			if ($netmask < 0 || $netmask > 32) {
				return false;
			}
		} else {
			$address = $subnet;
			$netmask = 32;
		}

		if (false === ip2long($address)) {
			return false;
		}

		return 0 === substr_compare(sprintf('%032b', ip2long($requestIp)), sprintf('%032b', ip2long($address)), 0, $netmask);
	}

	/**
	 * Compares two IPv6 addresses.
	 * In case a subnet is given, it checks if it contains the request IP.
	 *
	 * @author David Soria Parra <dsp at php dot net>
	 *
	 * @see https://github.com/dsp/v6tools
	 *
	 * @param string $subnet IPv6 address or subnet in CIDR notation
	 *
	 * @return bool Whether the IP is valid
	 *
	 * @throws \RuntimeException When IPV6 support is not enabled
	 */
	public static function checkIp6(string $requestIp, string $subnet) {
		if (!((extension_loaded('sockets') && \defined('AF_INET6')) || @inet_pton('::1'))) {
			throw new Exception('Unable to check Ipv6. Check that PHP was not compiled with option "disable-ipv6".');
		}

		if (false !== strpos($subnet, '/')) {
			list($address, $netmask) = explode('/', $subnet, 2);

			if ('0' === $netmask) {
				return (bool) unpack('n*', @inet_pton($address));
			}

			if ($netmask < 1 || $netmask > 128) {
				return false;
			}
		} else {
			$address = $subnet;
			$netmask = 128;
		}

		$bytesAddr = unpack('n*', @inet_pton($address));
		$bytesTest = unpack('n*', @inet_pton($requestIp));

		if (!$bytesAddr || !$bytesTest) {
			return false;
		}

		for ($i = 1, $ceil = ceil($netmask / 16); $i <= $ceil; ++$i) {
			$left = $netmask - 16 * ($i - 1);
			$left = ($left <= 16) ? $left : 16;
			$mask = ~(0xffff >> $left) & 0xffff;
			if (($bytesAddr[$i] & $mask) != ($bytesTest[$i] & $mask)) {
				return false;
			}
		}

		return true;
	}
}
