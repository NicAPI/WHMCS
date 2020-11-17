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


/**function nicapi_DomainEdit($variables)
{
    include_once '../../modules/registrars/nicapi/nicapi.php';
    $params = getregistrarconfigoptions("nicapi");
    if (empty($params))
        return;
    $domainID = $variables['domainid'];
    $domain = (array)Capsule::table('tbldomains')->where('id', '=', $domainID)->first()[0];
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
add_hook("DomainEdit", 1, "nicapi_DomainEdit");*/


add_hook('AfterCronJob', 1, function($vars) {
	$params = getregistrarconfigoptions("nicapi");
	if (empty($params))
			return;

  $domains = Capsule::table('tbldomains')
		->where('registrar', '=', 'nicapi')
		->get();

		foreach ($domains as $item) {
			$domain = 	nicapi_GetDomainInfo([
				'domain' => $item->domain,
				'APIKey' => $params['APIKey']
			]);

			if (!$domain['expire'])
				continue;

			$dbExpire = strtotime($item->expirydate);
			$apiExpire = strtotime($domain['expire']);

			if ($dbExpire-$apiExpire > 86400*15) {
				if ($domain['delete']) {
					$result = nicapi_CancelExpireDelete([
						'domain' => $item->domain,
						'APIKey' => $params['APIKey']
					]);
					if (isset($result['error'])) {
						$receiver = $params['SystemNotificationMail'];
						mail($receiver, 'Domain deletion withdraw failed', 'Deletion withdrawal of domain ' . $item->domain . ' failed with message: ' . $result['error']);
					}
				}

				continue; // further renew at nicapi necessary
			}

			if ($domain['delete'])
				continue;

			nicapi_RequestDeleteExpire([
				'domain' => $item->domain,
				'APIKey' => $params['APIKey']
			]);
		}
});
