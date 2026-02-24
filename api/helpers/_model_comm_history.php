<?php

class CommModel {

	/**
	 * Inserisce una nuova comunicazione
	 */
	public static function insert(array $data) {
		global $pdo;

		// Controllo su response: se è array o oggetto, lo codifichiamo in JSON
		if (isset($data['response']) && (is_array($data['response']) || is_object($data['response']))) {
			$data['response'] = json_encode($data['response'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}

		$data['group_id']   = $data['group_id'] ?? NULL;
		$data['type']       = 'wa';
		$data['created_at'] = date('Y-m-d H:i:s');
		$data['updated_at'] = $data['created_at'];
		$data['sent_date']  = $data['sent_date'] ?? NULL;
		$data['response']   = $data['response'] ?? NULL;

		$sql = "INSERT INTO jta_comm_history ( group_id, type, pharma_id, pharma_info, user_id, user_info, body, schedule_date, sent_date, response, status, created_at, updated_at )
				VALUES ( :group_id, :type, :pharma_id, :pharma_info, :user_id, :user_info, :body, :schedule_date, :sent_date, :response, :status, :created_at, :updated_at )";

		$stmt = $pdo->prepare($sql);
		$stmt->execute([
			':group_id'      => $data['group_id'],
			':type'          => $data['type'],
			':pharma_id'     => $data['pharma_id'],
			':pharma_info'   => $data['pharma_info'],
			':user_id'       => $data['user_id'],
			':user_info'     => $data['user_info'],
			':body'          => $data['body'],
			':schedule_date' => $data['schedule_date'],
			':sent_date'     => $data['sent_date'],
			':response'      => $data['response'],
			':status'        => $data['status'],
			':created_at'    => $data['created_at'],
			':updated_at'    => $data['updated_at'],
		]);

		return (int) $pdo->lastInsertId();
	}

	/**
	 * Aggiorna una comunicazione esistente.
	 */
	public static function update(int $id, array $data): bool {
		global $pdo;

		// Controllo su response: se è array o oggetto, lo codifichiamo in JSON
		if (isset($data['response']) && (is_array($data['response']) || is_object($data['response']))) {
			$data['response'] = json_encode($data['response'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}

		$data['updated_at'] = date('Y-m-d H:i:s');

		$fields = [];
		foreach ($data as $key => $val) {
			$fields[] = "$key = :$key";
		}

		$data['id'] = $id;

		$sql = "UPDATE jta_comm_history SET " . implode(", ", $fields) . " WHERE id = :id";
		$stmt = $pdo->prepare($sql);

		return $stmt->execute($data);
	}

	/**
	 * Restituisce tutte le comunicazioni presenti in tabella.
	 */
	public static function getAll(): array {
		global $pdo;
		$stmt = $pdo->query("SELECT * FROM jta_comm_history ORDER BY id DESC");
		$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

		if (!$results) return [];

		foreach ($results as &$row) {
			$row = self::_decodeJsonFields($row);
		}

		return $results;
	}

	public static function getOne(int $id) {
		global $pdo;
		$stmt = $pdo->prepare("SELECT * FROM jta_comm_history WHERE id = :id LIMIT 1");
		$stmt->execute(['id' => $id]);

		$result = $stmt->fetch(PDO::FETCH_ASSOC);
		return $result ? self::_decodeJsonFields($result) : false;
	}

	public static function getGroup($group_id) {
		global $pdo;
		$stmt = $pdo->prepare("SELECT * FROM jta_comm_history WHERE group_id = :group_id");
		$stmt->execute(['group_id' => $group_id]);
		$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

		if (!$results) return [];

		foreach ($results as &$row) {
			$row = self::_decodeJsonFields($row);
		}

		return $results;
	}

	/**
	 * Restituisce tutte le comunicazioni ancora da inviare.
	 */
	public static function getAllToSend(int $limit): array {
		global $pdo;
		$sql = "SELECT * FROM jta_comm_history WHERE status = 0 AND (schedule_date IS NULL OR schedule_date <= NOW()) ORDER BY schedule_date ASC";

		if( $limit > 0 ) $sql .= " LIMIT ".$limit;
		$stmt = $pdo->query($sql);
		$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

		if (!$results) return [];

		foreach ($results as &$row) {
			$row = self::_decodeJsonFields($row);
		}

		return $results;
	}

	/**
	 * Imposta la comunicazione come inviata.
	 */
	public static function completed(int $id, $response = NULL, $sent_date = NULL ) {
		$data = [ 'status' => 2 ];
		if( $response ) $data['response'] = $response;
		$data['sent_date'] = $sent_date ?? date('Y-m-d H:i:s');
		return self::update($id, $data);
	}

	/**
	 * Imposta la comunicazione come ignorata (non sara' piu' presa in considerazione).
	 */
	public static function ignore(int $id, $response = NULL ) {
		$data = [ 'status' => -1 ];
		if( $response ) $data['response'] = $response;
		return self::update($id, $data);
	}

	/**
	 * Invia la comunicazione (si occupa anche di impostarla come completata).
	 */
	public static function send($comm, int $delay_ms = 0 ) {
		$message = $comm['body'];
		$to = $comm['user_info'];
		$from = $comm['pharma_info'];
		$schedule_date = $comm['schedule_date'];

		if( $comm['status'] < 0 OR $comm['status'] > 0 ) return FALSE;
		if( $schedule_date && $schedule_date > date('Y-m-d H:i:s') ) return FALSE;

		$user = get_user_by_id($comm['user_id']);
		if( ! $user ){
			self::update($comm['id'], ['status' => -1, 'response' => ['error' => 'Utente non trovato', 'details' => 'User not found' ]]);
			return FALSE;
		}elseif( $user['accept_marketing'] != 1 ){
			self::update($comm['id'], ['status' => -1, 'response' => ['error' => 'Comunicazioni marketing non autorizzate', 'details' => 'No marketing consent' ]]);
			return FALSE;
		}elseif( $user['status'] != 'active' ){
			self::update($comm['id'], ['status' => -1, 'response' => ['error' => 'Accoun non attivo', 'details' => 'Account not active' ]]);
			return FALSE;
		}elseif( $user['is_deleted '] == 1 ){
			self::update($comm['id'], ['status' => -1, 'response' => ['error' => 'Account cancellato', 'details' => 'Account deleted' ]]);
			return FALSE;
		}

		if( $comm['type'] == 'wa' ){
			if( $delay_ms > 0 ) usleep( $delay_ms * 1000 );
			$response = wa_send($message, $to, $comm['pharma_id']);
			if( $response && is_array($response) AND isset($response['success']) ){
				if( $response['success'] ){
					return self::completed($comm['id'], $response);
				}else{
					self::update($comm['id'], ['status' => -1, 'response' => $response]);
					return FALSE;
				}
			}else{
				self::update($comm['id'], ['status' => -1, 'response' => $response]);
			}
		}
		return FALSE;
	}

	/**
	 * Mette in coda l'invio di una o piu' comunicazioni per un certo gruppo di utenti.
	 */
	public static function scheduleWa( $pharma_id, $user_ids = [], $body = '', $schedule_date = NULL, $options = [] ) {
		$pharma = get_pharma_by_id($pharma_id);
		if( ! $body ) return FALSE;
		if( ! $pharma ) return FALSE;
		if( ! $pharma['phone_number'] ) return FALSE;
		if( $schedule_date !== NULL && ! is_valid_datetime($schedule_date) ) return 5;

		$group_id = generate_unique_string(12);

		$_base_data = [
			'group_id'      => $group_id,
			// 'type'          => 'wa',
			'pharma_id'     => $pharma_id,
			'pharma_info'   => $pharma['phone_number'],
			'body'          => $body,
			'schedule_date' => $schedule_date,
			'status'        => 0,
		];

		if( ! is_array($user_ids) ) $user_ids = [$user_ids];
		$user_ids = array_filter($user_ids);
		if( empty($user_ids) ) return FALSE;
		$count = 0;

		if( ! is_array($options) ) $options = [];

		if( ! isset($options['chunk']) ) $options['chunk'] = 200;
		if( ! is_numeric($options['chunk']) || $options['chunk'] <= 0 ) $options['chunk'] = 200;

		if( ! isset($options['chunk_gap']) ) $options['chunk_gap'] = 60 * 15;
		if( ! is_numeric($options['chunk_gap']) || $options['chunk_gap'] < 0 ) $options['chunk_gap'] = 60 * 15;

		if( ! isset($options['per_minute_gap']) ) $options['per_minute_gap'] = 0;
		if( ! is_numeric($options['per_minute_gap']) || $options['per_minute_gap'] <= 0 ) $options['per_minute_gap'] = 0;

		if( ! isset($options['daily_limit']) ) $options['daily_limit'] = 50;
		if( ! is_numeric($options['daily_limit']) || $options['daily_limit'] < 0 ) $options['daily_limit'] = 50;

		if( ! isset($options['daily_start_time']) ) $options['daily_start_time'] = '08:00:00';

		$base_ts = strtotime($schedule_date);
		$insert_idx = 0;

			foreach( $user_ids AS $_user_id ){
				$_user = get_user_by_id($_user_id);
				if( ! $_user ) continue;
				if( ! $_user['phone_number'] ) continue;
				if( $_user['role'] !== 'user' ) continue;
				if( $_user['status'] !== 'active' ) continue;
				if( $_user['is_deleted'] == '1' ) continue;

				$_data = array_merge($_base_data, [
					'user_id'   => $_user['id'],
					'user_info' => $_user['phone_number'],
				]);

				if( $options['daily_limit'] > 0 ){
					$daily_limit = (int) $options['daily_limit'];
					$chunk = (int) $options['chunk'];

					$day_idx = (int) floor($insert_idx / $daily_limit);
					$idx_in_day = $insert_idx % $daily_limit;

					$chunk_idx = (int) floor($idx_in_day / $chunk);
					$pos_in_chunk = $idx_in_day % $chunk;

					if( $day_idx === 0 ){
						$day_start_ts = $base_ts;
					}else{
						$day_date = date('Y-m-d', $base_ts + ($day_idx * 86400));
						$day_start_ts = strtotime($day_date . ' ' . $options['daily_start_time']);
					}

					$_data['schedule_date'] = date(
						'Y-m-d H:i:s',
						$day_start_ts + ($chunk_idx * $options['chunk_gap']) + ($pos_in_chunk * $options['per_minute_gap'])
					);
				}else{
					// fallback: comportamento attuale (tutti eleggibili da schedule_date, con chunk_gap solo tra chunk)
					$_data['schedule_date'] = $schedule_date;
				}

				if( self::insert($_data) ){
					$count++;
					$insert_idx++;
				}
			}

		if( ! $count ) return FALSE;
		return $group_id ?? TRUE;
	}

	/**
	 * Decodifica automatica dei campi JSON di una comunicazione.
	 */
	private static function _decodeJsonFields(array $row, array $fields = ['response']): array {
		foreach ($fields as $field) {
			if (isset($row[$field])) {
				$row[$field] = json_decode($row[$field], true);
			}
		}
		return $row;
	}

}
