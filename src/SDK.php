<?php
namespace EasyGCO\PublicAPI;

class SDK
{
    private $endPoint = 'https://easygco.com/api/public/v1/';
    private $guzzleConfig = '';
    private $guzzleClient;
    private $apikey = '';
    private $apisecret = '';
    private $apisignature = '';
    
    private $lastQuery = null;

    public function __construct(string $apikey, string $apisecret, array $_guzzleConfig = []) {

        $this->apikey = $apikey;
        $this->apisecret = $apisecret;
        $this->apisignature = hash_hmac('sha256', $this->apikey, $this->apisecret);

        $this->guzzleConfig = array_merge([
            'headers' => ['user-agent' => 'EasyGCO-API-Library'],
            'connect_timeout' => 60,
        ], $_guzzleConfig);

        $this->guzzleClient = new \GuzzleHttp\Client($this->guzzleConfig);
        
    }

    public function endPoint(string $endPoint) {
        if(!filter_var($endPoint, FILTER_VALIDATE_URL)) return false;
        $this->endPoint = $endPoint;
        return true;
    }

    public function connectionConfig(string $paramKey, $paramValue) {
        $this->guzzleConfig[$paramKey] = $paramValue;
        try {
            $this->guzzleClient = new \GuzzleHttp\Client($this->guzzleConfig);
        } catch(\Exception $e) {
            throw new Exception($e->getMessage());
        }
        return true;
    }

    public function getConnectionConfig(string $paramKey = null) {
        return ( $paramKey === null ) ? $this->guzzleConfig 
            :  ( array_key_exists($paramKey, $guzzleConfig )? $guzzleConfig[$paramKey] : null );
    }

    public function request(string $route, array $data = []) {

        $postdata = array_merge([
            'route' => 'routes|' . $route,
            'auth' => [
                'apikey_id' => $this->apikey,
                'apikey_signature' => $this->apisignature,
            ],
        ], $data);

        return $this->httpRequest($postdata);

    }

    public function httpRequest(array $postdata) {

        $this->lastQuery = http_build_query($postdata);

        try {
            $apiRequest = $this->guzzleClient->request('POST', $this->endPoint, ['form_params' => $postdata]);
        } catch(\Exception $e) {
            return [
                'status' => 'failed',
                'message' => $e->getMessage(),
            ];
        }
        
		if(!$apiRequest->getStatusCode() || intval($apiRequest->getStatusCode()) !== 200) {
            $returnResult = [
                'status' => 'failed', 
                'message' => 'HTTP request failure',
            ];

            try {
                $returnResult['message'] = $apiRequest->getReasonPhrase();
            } catch(\Exception $e) {
                return $returnResult;
            }
            return $returnResult;
        }

        $apiResponse = null;

        try {
            $apiResponse = $apiRequest->getBody()->getContents();
        } catch(\Exception $e) {
            return [
                'status' => 'failed', 
                'message' => $e->getMessage(),
            ];
        }
        
        $apiResponse = $this->checkResponse($apiResponse);

        if(!$apiResponse || !is_array($apiResponse))
            return [
                'status' => 'failed', 
                'message' => 'Invalid API Response',
            ];

        return $apiResponse;
    }

    public function lastQuery() {
        return $this->lastQuery? $this->lastQuery : null;
    }

    private function checkResponse($apiResponse = null) {
        if(!$apiResponse) return false;

        if(!is_array($apiResponse)) {
            json_decode($apiResponse,true);
            if(json_last_error() !== JSON_ERROR_NONE) return false;
            $apiResponse = json_decode($apiResponse,true);
        }
        if(!is_array($apiResponse) || !isset($apiResponse['status']) || !array_key_exists('message', $apiResponse)) return false;

        return $apiResponse;
    }

    public function success($requestResponse) {
        if(!$this->checkResponse($requestResponse)) return false;
        return ($requestResponse['status'] === 'success')? true : false;
    }

    public function getData($requestResponse) {
        if(!$this->checkResponse($requestResponse) || !isset($requestResponse['data'])) return null;
        return $requestResponse['data'];
    }

    public function getMessage($requestResponse) {
        if(!$this->checkResponse($requestResponse)) return 'Unknown Error';
        return $requestResponse['message'];
    }

    public function redirect($url) {
        if(!filter_var($url, FILTER_VALIDATE_URL)) exit();
        try {
            header("Location: $url");
        } catch(\Exception $e) {
            exit();
        }
        exit();
    }
  
}
