<?php

namespace Services;

class DBService {

	public $DBCon = null;

	public $affectedRows = 0;

	private $hostNow = null;
	private $baseNow = null;


	public function connect($host='', $dbname='', $user='', $pass='') {

		if(!empty($host)) {
			if($this->hostNow != $host) {
				$this->DBCon = new \mysqli($host, $user, $pass);

				if($this->DBCon->connect_errno) {
					throw new \Error('Error BD. Host not connected.');
				}

				if(!$this->DBCon->set_charset('utf8mb4')) {
					throw new \Error('Error BD. Not possible to define the charset.');
				}
				$this->DBCon->query("SET time_zone='". date('P') ."'");
				$this->hostNow = $host;
				$this->baseNow = '';
			}
		}

		if(!empty($dbname)) {
			if($this->baseNow != $dbname) {

				if(!isset($this->DBCon)) {
                    throw new \Error('Error BD. Host not connected.');
				}

				if(!$this->DBCon->select_db($dbname)) {
                    throw new \Error('Error BD. Not possible to select the base.'); 
				}

				$this->baseNow = $dbname;
			}
		}

		return $this->DBCon;
	}

	public function select($sqlQuery='', $dataFields=[]) {

		if(is_null($this->DBCon)) {
			throw new \Error('Error BD. Connection not established with the database.');
		}

		$sqlQuery = trim($sqlQuery);

		if(count($dataFields)) {

			$replaceList = [];

			preg_match_all('/[^a-z0-9](:[a-z0-9]+)/im', $sqlQuery, $regexResult);
			$regexList = $regexResult[1];

			foreach($regexList as $nil => $key) {

				$keyPurified = substr($key, 1);

				if(!isset($dataFields[$keyPurified])) {
					throw new \Error('Error to generate the SQL. Field '. $key .' not found for bind');
				}

				$replaceList[$key] = $this->real_escape_string($dataFields[$keyPurified]);
			}

			if(count($replaceList)) $sqlQuery = str_replace(array_keys($replaceList), $replaceList, $sqlQuery);
		}

		if(!$result = $this->DBCon->query($sqlQuery)) {
			throw new \Error('Error to execute the SQL. '. $this->DBCon->error);
		}

		$response = [];

		if(strtolower(substr($sqlQuery, -7)) == 'limit 1') {
			$response = $result->fetch_array(MYSQLI_ASSOC);
		}
		else {
			while($row = $result->fetch_array(MYSQLI_ASSOC)) $response[] = $row;
		}

		return $response;
	}


	public function sql($com, $dataFields='', $stripTags=true) {

		$com = trim($com);

		//If $dataFields is empty, just run the $com command
		if(!empty($dataFields)) {
			if(is_array($dataFields)) {
				foreach($dataFields as $key => $value) {
					if(!empty($key)) {
						$fields[] = $key;
						if($stripTags) $value = strip_tags($value);
						$value = iconv('UTF-8', 'UTF-8//IGNORE', $value);
						$value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value);
						$value = str_replace('\0', '/0', $value);
						$values[] = addslashes($value);
					}
				}
			}
			//If it is an insert command
			if(strtolower(substr($com, 0, 6)) == 'insert') { //INSERT
				$field = implode(', ', $fields);
				$value = implode('\', \'', $values);
				$com = $com .' ('. $field .') values (\''. $value .'\')';
			}
			//If it is an update command
			elseif(strtolower(substr($com, 0, 6)) == 'update') { //UPDATE
				foreach($fields as $key => $value) $texts[] = $value .'=\''. $values[$key] .'\'';
				$text = implode(', ', $texts);
				$com = str_replace('[fields]', $text, $com);
			}
		}

		if (is_null($this->DBCon)) {
			throw new \Error('Error BD. Connection not established with the database');
		}

		if(!$result = $this->DBCon->query($com)) {
			throw new \Error('Error to execute the SQL. '. $this->DBCon->error);
		}

		$this->affectedRows = $this->DBCon->affected_rows;

		if(strtolower(substr($com, 0, 6)) == 'insert'   ) {
			$insertId = $this->getInsertId();
			$tableName = $this->extractTableName($com);
			
			if ($tableName && $insertId) {
					$selectQuery = "SELECT * FROM $tableName WHERE id = $insertId LIMIT 1";
					$insertedData = $this->select($selectQuery);

					if (!empty($insertedData) && isset($insertedData[0]) && isset($insertedData[0]['id'])) {
						return $insertedData[0];
					} elseif (!empty($insertedData) && isset($insertedData['id'])) {
						return $insertedData;
					}

					return true;
			}
			
			return true;
		}
		elseif(strtolower(substr($com, 0, 6)) != 'select') {
			return true;
		}
		else {
			if(strtolower(substr($com, -7)) == 'limit 1') { //If is 'limit 1' set, return the value directly
				$response = $result->fetch_array(MYSQLI_ASSOC);
			}
			else { //Else, return into a list
				$response = array();

				while($row = $result->fetch_array(MYSQLI_ASSOC))  {
					$response[] = $row;
				}
			}

			return $response;
		}
	}

	private function extractTableName($query) {
		if (preg_match('/INSERT\s+INTO\s+`?(\w+)`?/i', $query, $matches)) {
			return $matches[1];
		}
		return null;
	}

	public function real_escape_string($str) {
		return $this->DBCon->real_escape_string($str);
	}


	public function close() {
		if(is_resource($this->DBCon)) {
			mysqli_close($this->DBCon);
		}
	}

	public function getInsertId() {
		return $this->DBCon->insert_id;
	}


}