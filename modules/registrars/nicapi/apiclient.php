<?php

class NicAPIClient {
	
	private $version = 'v1';
	private $token = null;
	
	public function __construct($token) {
		$this->token = $token;
		$this->url = 'https://connect.nicapi.eu/api/'.$this->version.'/';
	}
	
	public function get($url, $params) {
		return $this->request($url, $params, 'GET');
	}
	
	public function post($url, $params) {
		return $this->request($url, $params, 'POST');
	}
	
	public function delete($url, $params) {
		return $this->request($url, $params, 'DELETE');
	}
	
	public function put($url, $params) {
		return $this->request($url, $params, 'PUT');
	}
	
	private function request($url, $params, $method) {
		
		$params['authToken'] = $this->token;
		$url = $this->url.$url.'?authToken='.$this->token;
        $ch = curl_init($url);

		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		if ($method == 'POST')
	        curl_setopt($ch, CURLOPT_POSTFIELDS, ($params));
	    elseif ($method == 'GET')
	    	curl_setopt($ch, CURLOPT_URL, $url.'&'.http_build_query($params));
	    else
	    	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        if (!$data = curl_exec($ch)) {
            return FALSE;
        }		
		curl_close($ch);
        
        return json_decode($data);
		
	}
	
}