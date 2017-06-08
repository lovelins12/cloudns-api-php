<?php


namespace tvorwachs\ClouDNS;

/**
 * ClouDNS API class
 * @copyright 2014 Techreanimate
 * @author Luis Rodriguez
 * @author Tobias Vorwachs
 */
class ClouDNS
{
    /**
	 * API credential information required to execute requests
	 */
    private $apiUrl = 'https://api.cloudns.net/';
    private $authId;
    private $authPassword;
	
	/**
	 * Verify SSL connection
	 */
	private $sslCheck = true;
	
    /**
	 * storage for API responses
	 */
    public $Response;
	
	/**
	 * Pass in options to set as an array
	 * @param $options
     * @return array
	 */
	public function setOptions(Array $options = array()){
		$this->apiUrl = isset($options['apiUrl']) ? $options['apiUrl'] : $this->apiUrl;
		$this->authId = isset($options['authId']) ? $options['authId'] : $this->authId;
        $this->sslCheck = isset($options['sslCheck']) ? $options['sslCheck'] : $this->sslCheck;
		$this->authPassword = isset($options['authPassword']) ? $options['authPassword'] : $this->authPassword;
		
		/* Check if login still works */
		$status = $this->login();
		if($status['status'] != 'Success') return $status;
		
		
		return array('status' => 'Success');
	}
	
	/**
	 * Test login details
	 */
	private function login(){
		$get = array(
			'auth-id' => $this->authId,
			'auth-password' => $this->authPassword
		);
		
		/* Clean options for GET */
		$get_string = $this->url_encode($get);
		
		/* Connect */
		$result = $this->connect($get_string,'dns/login.json');
		
		/* Return an array result */
		return json_decode($result,true);
	}
    
    /**
	 * determine our IP address
	 * @return string our public IP address, as seen by icanhazip.com
	 */
    public function detectIp(){
        $ch = curl_init( 'http://icanhazip.com' );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, TRUE );
        $result = rtrim(curl_exec( $ch ) );
        curl_close( $ch );
        return $result;
    }

	/**
	 * Get a list with available domain name servers.
	 * @return array
	 */
	public function listNameServers(){
		$get_string = $this->url_encode($this->getAuth());
		$result = $this->connect($get_string,'dns/available-name-servers.json');
		return json_decode($result,true);
	}

	/**
	 * Gets a list with zones you have or zone names matching a keyword. The method works with pagination. Reverse zones are included.
	 * @param $page - Current page your zone list is on
	 * @param $rows - Results per page. Can be 10, 20, 30, 50 or 100.
	 * @param $search - Domain name, reverse zone name or keyword to search for in the zone names
	 * @return array('Pages','Data' => Array)
	 */
	public function listZones($page = 1, $rows = 10, $search = null){
		$get = $this->getAuth();

		/* Validate the params, default if fail */
		$get['page'] = intval($page) > 0 ? intval($page) : 1;
		$get['rows-per-page'] = in_array(intval($rows), array(10,20,30,50,100)) ? intval($rows) : 10;
		if($search != null) $get['search'] =  $search;

		/* Run the connection and get result for page count */
		$get_string = $this->url_encode($get);
		$pg_result = $this->connect($get_string,'dns/get-pages-count.json');

		/* Run the connection and get result */
		$get_string = $this->url_encode($get);
		$result = $this->connect($get_string,'dns/list-zones.json');

		return array('Pages'=>json_decode($pg_result,true),'Data'=>json_decode($result,true));
	}

	/**
	 * Gets the number of the zones you have and the zone limit of your customer plan. Reverse zones are included.
	 * @return array
	 */
	public function listZoneStats(){
		$get_string = $this->url_encode($this->getAuth());
		$result = $this->connect($get_string,'dns/get-zones-stats.json');
		return json_decode($result,true);
	}

	/**
	 * This function is available only for slave zones, master zones and cloud/bulk domains. Works with reverse zones too.
	 * @param $domain
	 * @return array
	 */
	public function deleteDomainZone($domain){
		$get = $this->getAuth();

		if(filter_var($domain, FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)){
			$get['domain-name'] = $domain.'.in-addr.arpa';
		}elseif(filter_var($domain, FILTER_VALIDATE_IP,FILTER_FLAG_IPV6)){
			$get['domain-name'] = $domain.'.ip6.arpa';
		}else{
			$get['domain-name'] = $domain;
		}

		$get_string = $this->url_encode($get);
		$result = $this->connect($get_string,'dns/delete.json');
		return json_decode($result,true);
	}
	
	/**
	 * Returns the GET array to be used for authentication
	 */
	private function getAuth(){
		return array('auth-id' => $this->authId,'auth-password' => $this->authPassword);
	}
	
	/** 
	 * Runs the final connection with all the data needed
	 * @param $get_string
	 * @param $directory (optional) - The directory of the api you are calling dns/ or domains/
	 * @return mixed
	 */
	private function connect($get_string, $directory = 'dns/'){
		$request = curl_init($this->apiUrl.$directory.'?'.$get_string); // initiate curl object
			curl_setopt($request, CURLOPT_HEADER, 0); // set to 0 to eliminate header info from response
			curl_setopt($request, CURLOPT_RETURNTRANSFER, 1); // Returns response data instead of TRUE(1)
			curl_setopt($request, CURLOPT_SSL_VERIFYPEER, $this->sslCheck); // uncomment this line if you get no gateway response.
			$this->Response = curl_exec($request); // execute curl post and store results in $post_response
		curl_close ($request); // close curl object
		
		return $this->Response;
	}
	
	/** 
	 * This section takes the input fields and converts them to the proper format
	 * for an http post.  For example: "auth_id=username&auth_password=a1B2c3D4"
     *
     * @param $get_values array
     * @return string
	 */
	private function url_encode(Array $get_values = array()){
		$get_string = "";
		foreach( $get_values as $key => $value ){
			$get_string .= "$key=" . urlencode( $value ) . "&";
		}
		return rtrim( $get_string, "& " );
	}
}
