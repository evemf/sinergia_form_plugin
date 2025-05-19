<?php

class APIClient {
    public $sessionId;
    public $url;
    public $verbose;

    public function __construct($url, $verbose = false) {
        $this->url = $url;
        $this->verbose = $verbose;
    }

    public function call($method, $parameters) {
        $post = array(
            "method"        => $method,
            "input_type"    => "JSON",
            "response_type" => "JSON",
            "rest_data"     => json_encode($parameters),
        );

        if ($this->verbose && php_sapi_name() === 'cli') {
            error_log("Llamada a la API: " . strtoupper($method));
            error_log("Parámetros: " . print_r($post, true));
        }

        $curl_request = curl_init();
        curl_setopt($curl_request, CURLOPT_URL, $this->url);
        curl_setopt($curl_request, CURLOPT_POST, 1);
        curl_setopt($curl_request, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        curl_setopt($curl_request, CURLOPT_HEADER, 0);
        curl_setopt($curl_request, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl_request, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl_request, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($curl_request, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl_request, CURLOPT_POSTFIELDS, http_build_query($post));
        $result = curl_exec($curl_request);

        if ($result === false) {
            echo 'Curl error: ' . curl_error($curl_request);
            return null;
        }
        if (curl_errno($curl_request)) {
            echo 'Curl error: ' . curl_error($curl_request);
            return null;
        }
        curl_close($curl_request);

        return json_decode($result);
    }

    public function login($username, $password, $language, $notifyOnSave = false) {
        $params = array(
            'user_auth' => array(
                'user_name' => $username,
                'password'  => md5($password),
            ),
            'application_name' => 'SinergiaCRM API Examples v4.1',
            "name_value_list"  => array(
                array("name" => "notifyonsave", "value" => $notifyOnSave),
                array("name" => "language", "value" => $language),
            ),
        );
        
        $result = $this->call('login', $params);
        
        if (isset($result->id)) {
            $this->sessionId = $result->id;
        } else {
            $this->sessionId = null;
        }
    
        if ($this->verbose && $this->sessionId) {
            error_log("Session ID obtenido: " . $this->sessionId);
        }
    
        return $this->sessionId;
    }

    public function logout() {
        $parameters = array('session' => $this->sessionId);
        $this->call("logout", $parameters);
        if ($this->verbose) {
            error_log("Sesión cerrada.");
        }
        $this->sessionId = '';
    }

    public function getEntryList($params) {
        $params = array_merge(['session' => $this->sessionId], $params);
        return $this->call("get_entry_list", $params);
    }

    public function getEntry($params) {
        $params = is_array($params) ? $params : [];
        $params = array_merge(array('session' => $this->sessionId), $params);
        $result = $this->call("get_entry", $params, $this->url);
        if ($this->verbose) {
            echo "- INFORMACIÓN DEL REGISTRO<br /><br />";
            echo "<span style='color:green'";
            print_r($result);
            echo "</span>";
        }
        return $result;
    }

    function getModuleFields($params) {
        $params = is_array($params) ? $params : [];
        $params = array_merge(array('session' => $this->sessionId), $params);
        $result = $this->call("get_module_fields", $params, $this->url);
        if ($this->verbose) {
            echo "- CAMPOS DEL MÓDULO: {$params['module_name']} <br /><br />";
            echo "<span style='color:green'";
            print_r($result);
            echo "</span>";
        }
        return $result;
    }
}
?>
