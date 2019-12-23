<?php

use Illuminate\Database\Capsule\Manager as Capsule;

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


function nicapi_DomainEdit($variables)
{
    include_once '../../modules/registrars/nicapi/nicapi.php';
    $params = getregistrarconfigoptions("nicapi");
    if (empty($params))
        return;
    $domainID = $variables['domainid'];
    $domain = (array)Capsule::table('tbldomains')->where('id', '=', $domainID)->first();
    if ($domain['registrar'] != 'nicapi') return;
    // user defined configuration values
    $token = $params['APIKey'];
    $api = new NicAPIClient($token);
    if ($domain['donotrenew']) {
        nicapi_RequestDeleteExpire($params+$domain);
    } else {
        nicapi_CancelExpireDelete($params+$domain);
    }
}
add_hook("DomainEdit", 1, "nicapi_DomainEdit");
