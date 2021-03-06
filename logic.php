<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once 'jaro.php';
require_once 'database.php';

function function_get_db() {	
	$db = Database::getInstance();
    $conn = $db->getConnection(); 
    return $conn;	
}

function function_get($request_endpoint) {
	try {		
		$exploded = explode('/', $request_endpoint);
		if ($exploded[1] == 'test') {
			$response['error']  ='false';
			$response['response'] = 'test API endpoint working';
			echo json_encode($response);
		} else if ($exploded[1] == 'v1') {
			if ($exploded[2] == 'register' & !isset($exploded[3])) {
				function_generate_api_key();
				return;
			}
			if (explode('?',$exploded[2])[0] == 'alias') {
				$error = 'input_error';				
				if (isset($_GET['key'])) $access_key = $_GET['key'];	
				else throw new Exception('api_key_error');

				if (isset($_GET['name'])) $name = $_GET['name'];	
				else throw new Exception('input_error');

				if (isset($_GET['alias'])) $alias = explode(',',$_GET['alias']);
				else throw new Exception('input_error');

				if (isset($_GET['threshold'])) $threshold = $_GET['threshold'];
				else $threshold = 0.0;

				$x = function_validate_api_key($access_key);	
				if (!$x[0]) {
				 	throw new Exception('api_key_error');				 	
				} else {
					$alias_scoring = jaroWinkler($name, $alias, $threshold);					
					$response = [];
					$response['error']  ='false';
					$response['response'] = [];
					$response['response']['name'] = $name;
					$response['response']['comparisons'] = [];
					foreach($alias_scoring as $name => $score) {
						$response['response']['comparisons'][$name] = $score;
					}
					
					echo json_encode($response);
				}

			} else {
				throw new Exception('resource_error');
			}
		} else {
			throw new Exception('version_error');
		}	
	}
	catch (Exception $e) {
		switch ($e->getMessage()) {
			case 'input_error':
				function_error('input_error');
				break;

			case 'resource_error':
				function_error('resource_error');
				break;

			case 'version_error':
				function_error('version_error');
				break;

			case 'api_key_error':
				function_error('api_key_error');
				break;			

			default:
				function_error();
				break;

		}
	}
	return;
}

function function_generate_api_key() {	
	$token = bin2hex(openssl_random_pseudo_bytes(16));
	$response = [];
	$status = true;
	
	$conn = function_get_db();		
	
	while ($status) {
		$stmt = $conn->prepare('SELECT * FROM access_key WHERE access_key = :access_key');
		$stmt->execute(array('access_key' => $token));
		$result = $stmt->fetchAll();
		if (count($result)) {
			$token = bin2hex(openssl_random_pseudo_bytes(16));
		} else {
			$status = false;
		}
	}

	$stmt = $conn->prepare("INSERT INTO access_key (access_key, created) VALUES (:access_key,:created)");
  	$stmt->bindParam(':access_key', $token);  
  	$date = date("Y-m-d H:i:s");	
  	$stmt->bindParam(':created', $date);  	  	
  	try {
  		$stmt->execute();	
  	}
  	catch (PDOException $e) {
  		echo $e->getMessage();
  	}  	
 	$response['error'] = 'false';
 	$response['access_key'] = $token;
 	echo json_encode($response);
}

function function_validate_api_key($access_key) {
	$conn = function_get_db();
	$stmt = $conn->prepare("SELECT access_key FROM access_key WHERE access_key = :access_key");
	$stmt->bindParam(':access_key',$access_key); 
  	try {
  		$stmt->execute();
  		$rows = $stmt->fetchAll();  		  		
  		$stmt->setFetchMode(PDO::FETCH_ASSOC);
  		if(count($rows) == 1) {  			
  			return array(true,'key_found');		
  		} else {
  			return array(false,'not_found_error');
  		}  		
  	}
  	catch (PDOException $e) {
  		echo $e->getMessage();
  	}  	
}

function function_error($reason) {
	$error_message = [];
	$error_message['error'] = 'true';
	switch($reason) {
		case 'method_error':
			$error_message['response'] = 'method not allowed';
			echo json_encode($error_message);
			header("HTTP/1.0 405 Method Not Allowed");
			break;

		case 'resource_error':
			$error_message['response'] = 'not found';
			echo json_encode($error_message);
			header("HTTP/1.0 404 Not Found");
			break;

		case 'version_error':
			$error_message['response'] = 'api version not supported';
			echo json_encode($error_message);
			header("HTTP/1.0 404 Not Found");
			break;

		case 'input_error':
			$error_message['response'] = 'missing or malformed input';
			echo json_encode($error_message);
			header("HTTP/1.0 400 Bad Request");
			break;

		case 'api_key_error':
			$error_message['response'] = 'missing or malformed api key';
			echo json_encode($error_message);
			header("HTTP/1.0 401 Unauthorized");
			break;

		default:
			$error_message['response'] = 'unknown error';
			echo json_encode($error_message);
			header("HTTP/1.0 520 Unkown Error");
			break;
	}
}

?>
