<?php

use Illuminate\Database\Capsule\Manager as Capsule;

function nicapi_DomainEdit($variables)
{
    include_once '../../modules/registrars/nicapi/nicapi.php';

    $params = getModuleParams("nicapi");
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