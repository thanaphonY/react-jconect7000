<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * mqtt class is initiating mqtt
 */
class Mqtt
{
	function __construct()
	{
		require './application/libraries/phpMQTT/phpMQTT.php';
	}

	public function pub($server, $port, $username, $password, $client_id, $topic, $message)
	{
		
		$mqtt = new phpMQTT( $server,$port,$client_id);
		/*
		$mqtt->address = $server;
		$mqtt->port = $port;
		$mqtt->clientid = $client_id;
		*/
		if ($mqtt->connect(true, NULL, $username, $password)) {
			$mqtt->publish( $topic, $message, 0);
			$mqtt->close();
			return true;
		} else {
			return "timeout";
		}
	}
}
