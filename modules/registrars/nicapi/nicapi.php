<?php

use WHMCS\Domains\DomainLookup\ResultsList;
use WHMCS\Domains\DomainLookup\SearchResult;

include('apiclient.php');

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}


function nicapi_MetaData()
{
    return array(
        'DisplayName' => 'NicAPI (LUMASERV Systems) Domain Module',
        'APIVersion' => '1.0',
    );
}

function nicapi_getConfigArray()
{
    return array(
        // Friendly display name for the module
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'NicAPI Domain Module',
        ),
        // api key
        'APIKey' => array(
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Enter your api key here',
        ),
        // admin handle
        'AdminC' => array(
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Enter your admin handle here. If provided, the setting of whmcs will be used.',
        ),
        // tech handle
        'TechC' => array(
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Enter your tech handle here',
        ),
        // zone handle
        'ZoneC' => array(
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Enter your zone handle here',
        ),
        // create dns zone
        'CreateZone' => array(
            'Type' => 'yesno',
            'Description' => 'Always create a dns zone (even if you are not using our nameservers).',
        ),
    );
}

function nicapi_Sync($params)
{
	// user defined configuration values
    $token = $params['APIKey'];

    $api = new NicAPIClient($token);

    try {
        $result = $api->get('domain/domains/show', [
        	'domainName' => $params['sld'].'.'.$params['tld']
        ]);
        if ($result->status != 'success')
    		return [
    			'error' => $result->messages->errors{0}->message
    		];

        $domain = $result->data->domain;
        
        return [
        	'expirydate' => date("Y-m-d", strtotime($domain->expire)),
        	'active' => strtotime($domain->expire) >= time(),
        	'expired' => strtotime($domain->expire) < time(),
        	'transferredAway' => false,
        ];
        
    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

function nicapi_CheckAvailability($params)
{
    // user defined configuration values
    $token = $params['APIKey'];

    $api = new NicAPIClient($token);

    try {        
        $results = new ResultsList();
        
        foreach ($params['tlds'] as $tld) {
        	$tld = substr($tld, 1, strlen($tld));
        	
        	$result = 	$api->post('domain/domains/check', [
        		'domainName' => $params['sld'].'.'.$tld
        	]);
        	
        	$searchResult = new SearchResult($params['sld'], $tld);
        	// Determine the appropriate status to return
        	if ($result->data->available) {
      	      $status = SearchResult::STATUS_NOT_REGISTERED;
       	 	} else {
            	$status = SearchResult::STATUS_REGISTERED;
        	}
        	$searchResult->setStatus($status);
        	
        	// Append to the search results list
        	$results->append($searchResult);
        }
            
        return $results;
    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

function nicapi_RegisterDomain($params, $authcode = null)
{
    // user defined configuration values
    $token = $params['APIKey'];

    // registration parameters
    $sld = $params['sld'];
    $tld = $params['tld'];
    $registrationPeriod = $params['regperiod'];
    /**
     * Nameservers.
     *
     * If purchased with web hosting, values will be taken from the
     * assigned web hosting server. Otherwise uses the values specified
     * during the order process.
     */
    $nameserver1 = $params['ns1'];
    $nameserver2 = $params['ns2'];
    $nameserver3 = $params['ns3'];
    $nameserver4 = $params['ns4'];
    $nameserver5 = $params['ns5'];
    
    // registrant information
    $firstName = $params["firstname"];
    $lastName = $params["lastname"];
    $fullName = $params["fullname"]; // First name and last name combined
    $companyName = $params["companyname"];
    $email = $params["email"];
    $address1 = $params["address1"];
    $address2 = $params["address2"];
    $city = $params["city"];
    $state = $params["state"]; // eg. TX
    $stateFullName = $params["fullstate"]; // eg. Texas
    $postcode = $params["postcode"]; // Postcode/Zip code
    $countryCode = $params["countrycode"]; // eg. GB
    $countryName = $params["countryname"]; // eg. United Kingdom
    $phoneNumber = $params["phonenumber"]; // Phone number as the user provided it
    $phoneCountryCode = $params["phonecc"]; // Country code determined based on country
    $phoneNumberFormatted = $params["fullphonenumber"]; // Format: +CC.xxxxxxxxxxxx
    
    /**
     * Admin contact information.
     *
     * Defaults to the same as the client information. Can be configured
     * to use the web hosts details if the `Use Clients Details` option
     * is disabled in Setup > General Settings > Domains.
     */
    $adminFirstName = $params["adminfirstname"];
    $adminLastName = $params["adminlastname"];
    $adminCompanyName = $params["admincompanyname"];
    $adminEmail = $params["adminemail"];
    $adminAddress1 = $params["adminaddress1"];
    $adminAddress2 = $params["adminaddress2"];
    $adminCity = $params["admincity"];
    $adminState = $params["adminstate"]; // eg. TX
    $adminStateFull = $params["adminfullstate"]; // eg. Texas
    $adminPostcode = $params["adminpostcode"]; // Postcode/Zip code
    $adminCountry = $params["admincountry"]; // eg. GB
    $adminPhoneNumber = $params["adminphonenumber"]; // Phone number as the user provided it
    $adminPhoneNumberFormatted = $params["adminfullphonenumber"]; // Format: +CC.xxxxxxxxxxxx
    // domain addon purchase status
    $enableDnsManagement = (bool) $params['dnsmanagement'];
    $enableEmailForwarding = (bool) $params['emailforwarding'];
    $enableIdProtection = (bool) $params['idprotection'];
    
    if (!$adminCountry)
    	$adminCountry = $countryCode;
    if (!$countryCode)
    	$countryCode = $adminCountry;

    $api = new NicAPIClient($token);
    
    preg_match('/([A-Za-z0-9-]* )+([0-9A-Za-z\/]+)/', $address1, $matches);
    $handle = $api->post('domain/handles/create', [
    	    "type"           => "PERS",
    		"sex"            => "MALE",
    		"firstname"      => $firstName,
    		"lastname"       => $lastName,
    		"organisation"   => $companyName,
    		"street"         => $matches[1],
    		"number"         => $matches[2],
    		"postcode"       => $postcode,
    		"city"           => $city,
    		"region"         => $state,
    		"country"        => $countryCode,
    		"email"          => $email,
    		"phone"			 => $phoneNumberFormatted,
    	]);
    $ownerHandle = $handle->data->handle->handle;
    
    if (!$params['AdminC']) {
    	preg_match('/([A-Za-z0-9-]* )+([0-9A-Za-z\/]+)/', $adminAddress1, $matches);
    	$handle = $api->post('domain/handles/create', [
    		    "type"           => "PERS",
    			"sex"            => "MALE",
    			"firstname"      => $adminFirstName,
    			"lastname"       => $adminLastName,
    			"organisation"   => $adminCompanyName,
    			"street"         => $matches[1],
    			"number"         => $matches[2],
    			"postcode"       => $adminPostcode,
    			"city"           => $adminCity,
    			"region"         => $adminState,
    			"country"        => $adminCountry,
    			"email"          => $adminEmail,
    			"phone"			 => $adminPhoneNumberFormatted,
    		]);
    	$adminHandle = $handle->data->handle->handle;
    } else {
    	$adminHandle = $params['AdminC'];
    }
	
    try {
        $result = $api->post('domain/domains/create', [
        	'domainName' => $sld.'.'.$tld,
        	'ownerC' => $ownerHandle,
        	'adminC' => $adminHandle,
        	'techC' => $params['TechC'],
        	'zoneC' => $params['ZoneC'],
        	'ns1' => $nameserver1,
        	'ns2' => $nameserver2,
        	'ns3' => $nameserver3,
        	'ns4' => $nameserver4,
        	'ns5' => $nameserver5,
        	'authinfo' => $authcode,
        	'create_zone' => $params['CreateZone'] == 'on'
        ]);
        if ($result->status != 'success')
    		return [
    			'error' => $result->messages->errors{0}->message
    		];
        
        return array(
            'success' => $result->status == 'success',
        );
    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

function nicapi_TransferDomain($params) {
	return nicapi_RegisterDomain($params, $params['transfersecret']);
}

function nicapi_GetNameservers($params)
{
	// user defined configuration values
    $token = $params['APIKey'];

    $api = new NicAPIClient($token);

    try {        
        $result = $api->get('domain/domains/show', [
        	'domainName' => $params['sld'].'.'.$params['tld']
        ]);
        if ($result->status != 'success')
    		return [
    			'error' => $result->messages->errors{0}->message
    		];

        $domain = $result->data->domain;
        
        return [
        	'success' => true,
        	'ns1' => $domain->nameservers->ns1->servername,
        	'ns2' => $domain->nameservers->ns2->servername,
        	'ns3' => $domain->nameservers->ns3->servername,
        	'ns4' => $domain->nameservers->ns4->servername,
        	'ns5' => $domain->nameservers->ns5->servername,
        ];
        
    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

function nicapi_SaveNameservers($params) {
	// user defined configuration values
    $token = $params['APIKey'];

    $api = new NicAPIClient($token);
    
    $result = 	$api->get('domain/domains/show', [
        'domainName' => $params['sld'].'.'.$params['tld']
    ]);
    $domain = $result->data->domain;

    try {        
        $result = $api->post('domain/domains/edit', [
        	'domainName' => $params['sld'].'.'.$params['tld'],
        	'ownerC' => $domain->ownerC,
        	'adminC' => $domain->adminC,
        	'techC' => $domain->techC,
        	'zoneC' => $domain->zoneC,
        	'ns1' => $params['ns1'],
        	'ns2' => $params['ns2'],
        	'ns3' => $params['ns3'],
        	'ns4' => $params['ns4'],
        	'ns5' => $params['ns5'],
        ]);
        if ($result->status != 'success')
    		return [
    			'error' => $result->messages->errors{0}->message
    		];

        $domain = $result->data->domain;
        
        return [
        	'success' => true,
        	'ns1' => $domain->nameservers->ns1->servername,
        	'ns2' => $domain->nameservers->ns2->servername,
        	'ns3' => $domain->nameservers->ns3->servername,
        	'ns4' => $domain->nameservers->ns4->servername,
        	'ns5' => $domain->nameservers->ns5->servername,
        ];
        
    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

function nicapi_GetEPPCode($params) {
	// user defined configuration values
    $token = $params['APIKey'];

    $api = new NicAPIClient($token);

    try {        
        $result = $api->post('domain/domains/authcode', [
        	'domainName' => $params['sld'].'.'.$params['tld'],
        ]);
        if ($result->status != 'success')
    		return [
    			'error' => $result->messages->errors{0}->message
    		];

        $domain = $result->data->domain;
        
        return [
        	'eppcode' => $domain->authinfo,
        ];
        
    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

function nicapi_GetDNS($params) {
	// user defined configuration values
    $token = $params['APIKey'];

    $api = new NicAPIClient($token);

	$result = 	$api->get('domain/domains/show', [
        'domainName' => $params['sld'].'.'.$params['tld']
    ]);
    $domain = $result->data->domain;
    
    $result = $api->get('dns/zones/show', [
    	'zone' => $domain->zone
    ]);
    if ($result->status != 'success')
    	return [
    		'error' => $result->messages->errors{0}->message
    	];

    $zone = $result->data->zone;
    
    $hostRecords = [];
    foreach ($zone->records as $record) {
    	$hostRecords[] = [
    		'hostname' => $record->name,
    		'type' => $record->type,
    		'address' => $record->data,
    		'priority' => 'N/A'
    	];
    }
    
    return $hostRecords;
}

function nicapi_SaveDNS($params) {
	// user defined configuration values
    $token = $params['APIKey'];

    $api = new NicAPIClient($token);

	$result = 	$api->get('domain/domains/show', [
        'domainName' => $params['sld'].'.'.$params['tld']
    ]);
    $domain = $result->data->domain;
    
    $records = [];
    foreach ($params['dnsrecords'] as $item) {
    	$hostname = trim($item['hostname'], 'N/A');
    	if ($item['address']) {
            $records[] = [
                'name' => $hostname ?: '@',
                'type' => $item['type'],
                'data' => ($item['priority'] != 'N/A' && is_numeric($item['priority']) ? ($item['priority'] . ' ') : '') . $item['address']
            ];
        }
    }
        
    $result = $api->put('dns/zones/update', [
    	'zone' => $domain->zone,
    	'records' => $records
    ]);
    if ($result->status != 'success')
    	return [
    		'error' => $result->messages->errors{0}->message
    	];
        
    return [
    	'success' => true
    ];
}

function nicapi_GetContactDetails($params) {
    // user defined configuration values
    $token = $params['APIKey'];

    $api = new NicAPIClient($token);

    $result = 	$api->get('domain/domains/show', [
        'domainName' => $params['sld'].'.'.$params['tld']
    ]);
    $domain = $result->data->domain;

    $contacts = [];

    foreach (['owner', 'admin'] as $type) {
        $response = $api->get('domain/handles/show', [
            'handle' => $domain->{$type.'C'}
        ]);
        $contact = $response->data->handle;

        $contacts[$type] = [
            'firstname' => $contact->firstname,
            'lastname' => $contact->lastname,
            'organisation' => $contact->organisation,
            'street' => $contact->street,
            'number' => $contact->number,
            'postcode' => $contact->postcode,
            'city' => $contact->city,
            'region' => $contact->region,
            'country' => $contact->country,
            'phone' => $contact->phone,
            'fax' => $contact->fax,
            'email' => $contact->email
        ];
    }

    return $contacts;
}

function nicapi_SaveContactDetails($params) {
    // user defined configuration values
    $token = $params['APIKey'];

    $api = new NicAPIClient($token);

    $result = 	$api->get('domain/domains/show', [
        'domainName' => $params['sld'].'.'.$params['tld']
    ]);
    $domain = $result->data->domain;

    foreach (['owner', 'admin'] as $type) {

        $edit = $api->post('domain/handles/edit', [
            'handle' => $domain->{$type.'C'},
            'firstname' => $params['contactdetails'][$type]['firstname'],
            'lastname' => $params['contactdetails'][$type]['lastname'],
            'organisation' => $params['contactdetails'][$type]['organisation'],
            'street' => $params['contactdetails'][$type]['street'],
            'number' => $params['contactdetails'][$type]['number'],
            'postcode' => $params['contactdetails'][$type]['postcode'],
            'city' => $params['contactdetails'][$type]['city'],
            'region' => $params['contactdetails'][$type]['region'],
            'country' => $params['contactdetails'][$type]['country'],
            'phone' => $params['contactdetails'][$type]['phone'],
            'fax' => $params['contactdetails'][$type]['fax'],
            'email' => $params['contactdetails'][$type]['email']
        ]);
        if ($edit->status != 'success')
            return [
                'error' => $result->messages->errors{0}->message
            ];

    }

    return true;
}

function nicapi_RequestDelete($params) {
    // user defined configuration values
    $token = $params['APIKey'];

    $api = new NicAPIClient($token);

    $result = 	$api->delete('domain/domains/delete', [
        'domainName' => $params['sld'].'.'.$params['tld']
    ]);

    if ($result->status != 'success')
        return [
            'error' => $result->messages->errors{0}->message
        ];

    return true;
}

function nicapi_RequestDeleteExpire($params) {
    // user defined configuration values
    $token = $params['APIKey'];

    $api = new NicAPIClient($token);

    $result = 	$api->get('domain/domains/show', [
        'domainName' => $params['domain'] ?: $params['sld'].'.'.$params['tld']
    ]);
    $domain = $result->data->domain;

    $result = 	$api->delete('domain/domains/delete', [
        'domainName' => $params['domain'] ?: $params['sld'].'.'.$params['tld'],
        'date' => $domain->expire
    ]);

    print_r($result);

    if ($result->status != 'success')
        return [
            'error' => $result->messages->errors{0}->message
        ];

    return true;
}

function nicapi_CancelExpireDelete($params) {
    // user defined configuration values
    $token = $params['APIKey'];

    $api = new NicAPIClient($token);

    $result = 	$api->post('domain/domains/undelete', [
        'domainName' => $params['domain'] ?: $params['sld'].'.'.$params['tld']
    ]);

    if ($result->status != 'success')
        return [
            'error' => $result->messages->errors{0}->message
        ];

    return true;
}

function nicapi_RequestRestore($params) {
    // user defined configuration values
    $token = $params['APIKey'];

    $api = new NicAPIClient($token);

    $result = 	$api->post('domain/domains/restore', [
        'domainName' => $params['sld'].'.'.$params['tld']
    ]);

    if ($result->status != 'success')
        return [
            'error' => $result->messages->errors{0}->message
        ];

    return true;
}

function nicapi_AdminCustomButtonArray($params) {
    return Array(
        "Löschung zum Expire" => "RequestDeleteExpire",
        "Löschung zurücknehmen" => "CancelExpireDelete",
        "Wiederherstellung (Restore)" => "RequestRestore",
    );
}










