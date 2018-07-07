<?php
add_hook('ClientAreaPageDomainDNSManagement', 1, "set_dns_entries");

function set_dns_entries($vars) {
	$supported_dns_records['records'] = [
		'A' => 'A (IPv4)',
		'AAAA' => 'AAAA (IPv6)',
		'MX' => 'MX',
		'CNAME' => 'CNAME',
		'SRV'=>'SRV',
		'TXT' => 'TXT',
		'HTTP_HEADER'=>'HTTP-Redirect',
		'HTTP_FRAME' => 'HTTP-Frame',
	];
	return $supported_dns_records;
}