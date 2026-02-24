<?php if( ! defined('JTA') ){ header('HTTP/1.0 403 Forbidden'); exit('Direct access is not permitted.'); }

// Queries

// Option(s) query
	require_once( '_model_options.php' );

// --------------------------------

function searchValidAuthRefreshToken( $refresh_token = NULL ){
	if( empty($refresh_token) ) return FALSE;

	global $pdo;

	$stmt = $pdo->prepare("SELECT * FROM jta_user_refresh_tokens WHERE token = ? AND is_valid = 1 AND expires_at > NOW()");
	$stmt->execute([$refresh_token]);

	return $stmt->fetch(PDO::FETCH_ASSOC);
}

function insertAuthRefreshToken( $user_id = NULL, $refresh_token = NULL ){
	if( empty($user_id) ) return FALSE;
	if( empty($refresh_token) ) return FALSE;

	global $pdo;

	$pdo->prepare("INSERT INTO jta_user_refresh_tokens (user_id, token, expires_at) VALUES (?, ?, ?)")
		->execute([$user_id, $refresh_token, date('Y-m-d H:i:s', time() + getRefreshTokenTimelife() )]);

	return TRUE;
}

function setAsInvalidAuthRefreshToken( $refresh_token = NULL ){
	if( empty($refresh_token) ) return FALSE;

	global $pdo;

	$pdo->prepare("UPDATE jta_user_refresh_tokens SET is_valid = 0 WHERE token = ?")->execute([$refresh_token]);

	return TRUE;
}


function get_users(){
	global $pdo;

	$stmt = $pdo->prepare("SELECT * FROM jta_users WHERE is_deleted = 0 AND status = 'active'");
	$stmt->execute();
	$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

	return $users;
}

function get_user_by_id( $user_id = NULL ){
	if( empty($user_id) ) return FALSE;

	global $pdo;

	$stmt = $pdo->prepare("SELECT * FROM jta_users WHERE is_deleted = 0 AND status = 'active' AND id = ? LIMIT 1");
	$stmt->execute([$user_id]);
	$user = $stmt->fetch(PDO::FETCH_ASSOC);

	return $user;
}

function get_user_by_username( $username = NULL ){
	if( empty($username) ) return FALSE;

	global $pdo;

	$stmt = $pdo->prepare("SELECT * FROM jta_users WHERE is_deleted = 0 AND status = 'active' AND slug_name = ? LIMIT 1");
	$stmt->execute([$username]);
	$user = $stmt->fetch(PDO::FETCH_ASSOC);

	return $user;
}

function get_user_by_email(string $email) {
	global $pdo;
	$stmt = $pdo->prepare("SELECT * FROM jta_users WHERE email = :email LIMIT 1");
	$stmt->execute(['email' => $email]);
	return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
}

function is_valid_email(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}


function get_user_by_wa( $wa_number = NULL ){
	if( empty($wa_number) ) return FALSE;

	global $pdo;

	$stmt = $pdo->prepare("SELECT * FROM jta_users WHERE is_deleted = 0 AND status = 'active' AND phone_number = ? LIMIT 1");
	$stmt->execute([$wa_number]);
	$user = $stmt->fetch(PDO::FETCH_ASSOC);

	return $user;
}

function update_user_init_profiling( $user_id = NULL, $profiling_data = NULL ){
	if( empty($user_id) || empty($profiling_data) ) return FALSE;

	global $pdo;

	$stmt = $pdo->prepare("UPDATE jta_users SET init_profiling = ? WHERE id = ? AND is_deleted = 0 AND status = 'active'");
	$stmt->execute([json_encode($profiling_data), $user_id]);

	return $stmt->rowCount() > 0;
}

function insert_user( $params = [] ){
	global $pdo;

	$hashedPassword = password_hash($params['password'], PASSWORD_DEFAULT);
	$now = date('Y-m-d H:i:s');

	$stmt = $pdo->prepare("INSERT INTO jta_users (
		slug_name, name, surname, email, password, phone_number,
		status, tos_date,
		created_at, updated_at, last_access,
		starred_pharma, accept_marketing
	) VALUES (
		:slug_name, :name, :surname, :email, :password, :phone_number,
		:status, :tos_date,
		:created_at, :updated_at, :last_access,
		:starred_pharma, :accept_marketing
	)");

	$stmt->execute([
		':slug_name'     => $params['slug_name'],
		':name'          => $params['name'],
		':surname'       => $params['surname'],
		':email'         => $params['email'],
		':password'      => $hashedPassword,
		':phone_number'  => $params['phone_number'],

		':status'        => 'active',
		':tos_date'      => $now,
		':created_at'    => $now,
		':updated_at'    => $now,
		':last_access'   => $now,

		':starred_pharma'=> $params['pharma_id'] ?? 1,
		':accept_marketing'=> !empty($params['accept_marketing']) ? 1 : 0,
	]);

	return $pdo->lastInsertId();
}

function update_user(int $user_id, array $params = []): bool {
	global $pdo;

	if (empty($params)) {
		return false; // Niente da aggiornare
	}

	$fields = [];
	$values = [];

	foreach ($params as $key => $value) {
		if ($key === 'password') {
			$fields[] = "$key = :$key";
			$values[":$key"] = password_hash($value, PASSWORD_DEFAULT);
		} else {
			$fields[] = "$key = :$key";
			$values[":$key"] = $value;
		}
	}

	$sql = "UPDATE jta_users SET " . implode(", ", $fields) . " WHERE id = :user_id";
	$values[":user_id"] = $user_id;

	$stmt = $pdo->prepare($sql);
	return $stmt->execute($values);
}

function get_users_by_pharma( $pharma_id = NULL ){
	if( empty($pharma_id) ) return [];

	global $pdo;

	$stmt = $pdo->prepare("SELECT * FROM jta_users WHERE is_deleted = 0 AND status = 'active' AND starred_pharma  = ?");
	$stmt->execute([$pharma_id]);
	$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

	return $users;
}

// --------------------------------
// Pharma(s) query
// --------------------------------

function get_pharma_by_id( $pharma_id = NULL ){
	if( empty($pharma_id) ) return FALSE;

	global $pdo;

	$stmt = $pdo->prepare("SELECT * FROM jta_pharmas WHERE is_deleted = 0 AND id = ? LIMIT 1");
	$stmt->execute([$pharma_id]);
	$user = $stmt->fetch(PDO::FETCH_ASSOC);

	return $user;
}

function get_pharma_by_slug( $pharma_slug = NULL ){
	if( empty($pharma_slug) ) return FALSE;

	global $pdo;

	$stmt = $pdo->prepare("SELECT * FROM jta_pharmas WHERE is_deleted = 0 AND slug_url = ? LIMIT 1");
	$stmt->execute([$pharma_slug]);
	$user = $stmt->fetch(PDO::FETCH_ASSOC);

	return $user;
}

function get_fav_pharma_by_user_id( $user_id = NULL ){
	if( empty($user_id) ) return FALSE;

	global $pdo;

	$sql = "
		SELECT p.*
		FROM jta_users u
		JOIN jta_pharmas p ON p.id = u.starred_pharma
		WHERE u.id = :user_id
		AND p.is_deleted = 0
		LIMIT 1
	";

	$stmt = $pdo->prepare($sql);
	$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
	$stmt->execute();

	$pharma = $stmt->fetch(PDO::FETCH_ASSOC);
	return $pharma ?: FALSE;
}

function get_pharmas_followed_by_user_id( $user_id = NULL ){
	if( empty($user_id) ) return [];

	global $pdo;

	$sql = "
		SELECT p.*
		FROM jta_pharmas p
		JOIN jta_pharma_user_rel r ON p.id = r.p_id
		WHERE r.u_id = :user_id
		AND p.is_deleted = 0
	";

	$stmt = $pdo->prepare($sql);
	$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
	$stmt->execute();

	$pharmas = $stmt->fetchAll(PDO::FETCH_ASSOC);
	return $pharmas ?: [];
}

function unsetAllUserRelFavPharma(int $user_id): bool {
	global $pdo;

	try {
		$stmt = $pdo->prepare("
			UPDATE jta_pharma_user_rel
			SET is_fav = 0
			WHERE u_id = :user_id AND is_fav = 1
		");
		return $stmt->execute([':user_id' => $user_id]);
	} catch (Exception $e) {
		return false;
	}
}

function existsUserPharmaRel(int $user_id, int $pharma_id): bool {
	global $pdo;

	try {
		$stmt = $pdo->prepare("
			SELECT COUNT(*) FROM jta_pharma_user_rel
			WHERE u_id = :user_id AND p_id = :pharma_id
		");
		$stmt->execute([
			':user_id' => $user_id,
			':pharma_id' => $pharma_id
		]);
		return $stmt->fetchColumn() > 0;
	} catch (Exception $e) {
		return false;
	}
}

function insertUserPharmaRel(int $user_id, int $pharma_id, bool $is_fav = false): bool {
	global $pdo;

	try {
		$stmt = $pdo->prepare("
			INSERT INTO jta_pharma_user_rel (u_id, p_id, is_fav)
			VALUES (:user_id, :pharma_id, :is_fav)
		");
		return $stmt->execute([
			':user_id'   => $user_id,
			':pharma_id' => $pharma_id,
			':is_fav'    => $is_fav ? 1 : 0
		]);
	} catch (Exception $e) {
		return false;
	}
}

function updateUserPharmaRel(int $user_id, int $pharma_id, bool $is_fav ): bool {
	global $pdo;

	try {
		$stmt = $pdo->prepare("
			UPDATE jta_pharma_user_rel
			SET is_fav = :is_fav
			WHERE u_id = :user_id AND p_id = :pharma_id
		");
		return $stmt->execute([
			':is_fav'    => $is_fav ? 1 : 0,
			':user_id'   => $user_id,
			':pharma_id' => $pharma_id
		]);
	} catch (Exception $e) {
		return false;
	}
}

function setUserPharmaFav(int $user_id, int $pharma_id, ?string &$error = null): bool {
	global $pdo;

	try {
		$pdo->beginTransaction();

		// 1. Aggiorna il campo starred_pharma su jta_users
		if (!update_user($user_id, ['starred_pharma' => $pharma_id])) {
			$error = "Errore durante update_user";
			throw new Exception($error);
		}

		// 2. Imposta tutti i rel is_fav = 0 per quell'utente
		if (!unsetAllUserRelFavPharma($user_id)) {
			$error = "Errore durante unsetAllUserRelFavPharma";
			throw new Exception($error);
		}

		// 3. Controlla se esiste già la relazione user-pharma
		$relationExists = existsUserPharmaRel($user_id, $pharma_id);
		if ($relationExists === false) {
			$error = "Errore durante existsUserPharmaRel";
			throw new Exception($error);
		}

		// 4. Aggiorna o crea la relazione con is_fav = 1
		$result = $relationExists
			? updateUserPharmaRel($user_id, $pharma_id, true)
			: insertUserPharmaRel($user_id, $pharma_id);

		if (!$result) {
			$error = "Errore durante update/insert relazione";
			throw new Exception($error);
		}

		$pdo->commit();
		return true;

	} catch (Exception $e) {
		$pdo->rollBack();
		if (!$error) {
			$error = "Eccezione generica: " . $e->getMessage();
		}
		return false;
	}
}

function deleteUserPharmaRel(int $user_id, int $pharma_id): bool {
	global $pdo;

	try {
		$stmt = $pdo->prepare("
			DELETE FROM jta_pharma_user_rel
			WHERE u_id = :user_id AND p_id = :pharma_id
		");
		return $stmt->execute([
			':user_id' => $user_id,
			':pharma_id' => $pharma_id
		]);
	} catch (Exception $e) {
		return false;
	}
}

function getMyPharma(){
	return get_fav_pharma_by_user_id( get_my_id() );
}


// Request(s) query
	require_once( '_model_requests.php' );
// Product(s)/Promo(s) query
	require_once( '_model_products.php' );
// Daily Pill(s) query
	require_once( '_model_pills.php' );
// Quiz(zes) query
	require_once( '_model_quizzes.php' );
// Challenge(s) query
	require_once( '_model_challenges.php' );
// Surveys(s) query
	require_once( '_model_surveys.php' );
// Point(s) query
	require_once( '_model_points.php' );
// Comm History query (whatsapp messages)
	// require_once( '_model_comm_history.php' );

// --------------------------------

function get_daily_pill( $date = NULL ){
	if( $date == NULL ) $date = date('Y-m-d');

	$date = explode('-', $date);
	$y = $date[0];
	$m = $date[1];
	$d = $date[2];
	$name = $y.$m.$d;

	$path = site_path().'data/daily_pill/'.$y.'/'.$m.'/pill-'.$name.'.json';
	if( file_exists($path) ){
		$json = json_decode(file_get_contents($path), TRUE);
		// $json = json_decode($json['message'], TRUE);
		// $json = json_decode($json['message'], TRUE);
		return $json;
	}
	return FALSE;
}

function get_events( $pharma_id ){
	global $pdo;

	$stmt = $pdo->prepare("SELECT * FROM jta_events WHERE is_deleted = 0 AND pharma_id = :pharma_id ORDER BY `datetime_start` ASC");
	$stmt->execute([':pharma_id' => $pharma_id]);
	$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$events = array_map( function($_event){
		if( $_event && ! empty($_event['img_cover']) ){
			$_event['img_cover'] = (array) json_decode($_event['img_cover']);
		}
		return $_event;
	}, $events );

	return $events;
}

function get_event_by_id( $event_id = NULL ){
	if( empty($event_id) ) return FALSE;

	global $pdo;

	$stmt = $pdo->prepare("SELECT * FROM jta_events WHERE is_deleted = 0 AND id = ? LIMIT 1");
	$stmt->execute([$event_id]);
	$event = $stmt->fetch(PDO::FETCH_ASSOC);

	if( $event && ! empty($event['img_cover']) ){
		$event['img_cover'] = (array) json_decode($event['img_cover']);
	}

	return $event;
}

function get_services( $pharma_id ){
	global $pdo;

	$stmt = $pdo->prepare("SELECT * FROM jta_services WHERE is_deleted = 0 AND pharma_id = :pharma_id ORDER BY title ASC");
	$stmt->execute([':pharma_id' => $pharma_id]);
	$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$services = array_map( function($_service){
		if( $_service && ! empty($_service['img_cover']) ){
			$_service['img_cover'] = (array) json_decode($_service['img_cover']);
		}
		return $_service;
	}, $services );

	return $services;
}

function get_service_by_id( $service_id = NULL ){
	if( empty($service_id) ) return FALSE;

	global $pdo;

	$stmt = $pdo->prepare("SELECT * FROM jta_services WHERE is_deleted = 0 AND id = ? LIMIT 1");
	$stmt->execute([$service_id]);
	$service = $stmt->fetch(PDO::FETCH_ASSOC);

	return $service;
}


// Clean data

function normalize_user_data( $user_db ){
	if( ! $user_db ) return FALSE;

	$init_profiling = null;
	if (!empty($user_db['init_profiling'])) {
		try {
			$init_profiling = json_decode($user_db['init_profiling'], true);
		} catch (Exception $e) {
			$init_profiling = $user_db['init_profiling'];
		}
	}

	$acceptMarketing = !empty($user_db['accept_marketing']) ? true : false;

	$user = [
		'id'             => (int) $user_db['id'],
		'username'       => $user_db['slug_name'],
		'slug_name'      => $user_db['slug_name'],
		'phone_number'   => $user_db['phone_number'],
		'name'    		 => $user_db['name']    ?? null,
		'surname'        => $user_db['surname'] ?? null,
		'email'          => $user_db['email']   ?? null,
		'has_profiling'  => $user_db['init_profiling'] ? true : false,
		'init_profiling' => empty($init_profiling) ? NULL : [
			'genere'     => $init_profiling['genere'],
			'fascia_eta' => $init_profiling['fascia_eta'],
			'lifestyle'  => $init_profiling['lifestyle'],
			'argomenti'  => $init_profiling['argomenti'],
		],
		'points'               => (int) $user_db['points_current_month'] ?? 0,
		'points_current_month' => (int) $user_db['points_current_month'],
		'is_tester'            => $user_db['is_tester'] ? true : false,
		'accept_marketing'     => $acceptMarketing,
		'consents' => [
			'consentPrivacy'     => true,
			'consent_term'       => true,
			'consent_age_profile'=> true,
			'accept_marketing'   => $acceptMarketing,
		],
	];

	return $user;
}

function normalize_user_profile_data($user_db) {
	if (!$user_db) return false;

	$init_profiling = null;
	if (!empty($user_db['init_profiling'])) {
		try {
			$init_profiling = json_decode($user_db['init_profiling'], true);
		} catch (Exception $e) {
			$init_profiling = $user_db['init_profiling'];
		}
	}

	$acceptMarketing = !empty($user_db['accept_marketing']) ? true : false;

	$user = [
		'id'             => (int)$user_db['id'],
		'slug_name'      => $user_db['slug_name'],
		'phone_number'   => $user_db['phone_number'],
		'name'    		 => $user_db['name']    ?? null,
		'surname'        => $user_db['surname'] ?? null,
		'email'          => $user_db['email']   ?? null,
		'has_profiling'  => $user_db['init_profiling'] ? true : false,
		'init_profiling' => empty($init_profiling) ? NULL : [
			'genere'     => $init_profiling['genere'],
			'fascia_eta' => $init_profiling['fascia_eta'],
			'lifestyle'  => $init_profiling['lifestyle'],
			'argomenti'  => $init_profiling['argomenti'],
		],
		'points'               => (int) $user_db['points_current_month'] ?? 0,
		'points_current_month' => (int) $user_db['points_current_month'],
		'is_tester'            => $user_db['is_tester'] ? true : false,
		'accept_marketing'     => $acceptMarketing,
		'consents' => [
			'consentPrivacy'     => true,
			'consent_term'       => true,
			'consent_age_profile'=> true,
			'accept_marketing'   => $acceptMarketing,
		],
	];

	return $user;
}

function normalize_pharma_data( $pharma_db ){
	if( ! $pharma_db ) return FALSE;

	$pharma = [
		'id'            => (int) $pharma_db['id'],
		'name'          => $pharma_db['slug_name'],
		'business_name' => $pharma_db['business_name'],
		'email'         => $pharma_db['email'],
		'phone_number'  => $pharma_db['phone_number'],
		'wa_number'     => $pharma_db['phone_number'],
		'description'   => $pharma_db['description'],

		// 'image_bot'    => site_url().'/assets/pharmacies/'.$pharma_db['id'].'/'.$pharma_db['img_bot'],
		// 'image_avatar' => site_url().'/assets/pharmacies/'.$pharma_db['id'].'/'.$pharma_db['img_avatar'],
		// 'image_cover'  => site_url().'/assets/pharmacies/'.$pharma_db['id'].'/'.$pharma_db['img_cover'],
		'image_logo'   => 'https://app.assistentefarmacia.it/panel/'.$pharma_db['logo'],
		// 'image_bot'    => get_pharma_img_src($pharma_db['id'], $pharma_db['img_bot']),
		'image_bot'    => 'https://assistentefarmacia.it/app-cliente-farmacia/img/Raffaella.jpg',
		'image_avatar' => get_pharma_img_src($pharma_db['id'], $pharma_db['img_avatar']),
		'image_cover'  => get_pharma_img_src($pharma_db['id'], $pharma_db['img_cover']),
		'social_list'  => [],
		'working_info' => [
			'human' => format_schedule_human_friendly($pharma_db['working_info']),
			'data'  => json_decode($pharma_db['working_info'], TRUE),
		],
	];

	if( $pharma_db['id'] == 1 ){
		// $pharma['social_list']['fb'] = [
		// 	'name' => 'Facebook',
		// 	'aria' => 'Visita la nostra pagina Facebook',
		// 	'url'  => 'https://www.facebook.com/p/Farmacia-Giovinazzi-100063672513586/'
		// ];
		// $pharma['social_list']['ig'] = [
		// 	'name' => 'Instagram',
		// 	'aria' => 'Visita il nostro profilo Instagram',
		// 	'url'  => 'https://www.instagram.com/farmaciagiovinazzi/'
		// ];

		$pharma['image_bot']    = 'https://assistentefarmacia.it/app-cliente-farmacia/img/Raffaella.jpg';
		$pharma['image_logo']   = 'https://app.assistentefarmacia.it/panel/'.$pharma_db['logo'];
		$pharma['image_avatar'] = 'https://api.assistentefarmacia.it/uploads/pharmacies/1/logo_farmacia_giovinazzi.png';
		$pharma['image_cover']  = 'https://api.assistentefarmacia.it/uploads/pharmacies/1/logo_farmacia_giovinazzi.png';

	}elseif( $pharma_db['id'] == 2 ){
	}elseif( $pharma_db['id'] == 3 ){
		$pharma['image_bot']    = 'https://api.assistentefarmacia.it/uploads/pharmacies/3/bot_aigemelli.jpg';
		$pharma['image_logo']   = 'https://app.assistentefarmacia.it/panel/'.$pharma_db['logo'];
		$pharma['image_avatar'] = 'https://api.assistentefarmacia.it/uploads/pharmacies/3/logo_farmacia_aigemelli.png';
		$pharma['image_cover']  = 'https://api.assistentefarmacia.it/uploads/pharmacies/3/logo_farmacia_aigemelli.png';
	}

	return $pharma;
}

function normalize_service_data( $service_db ){
	if( ! $service_db ) return FALSE;

	$service = [
		'id'          => $service_db['id'],
		'title'       => $service_db['title'],
		'description' => $service_db['description'],
		'cover_image' => $service_db['img_cover'],
		'is_featured' => !! $service_db['is_featured'],
		'iconClass'   => $service_db['icon_class'],
	];

	if( $service['cover_image'] ){
		$service['cover_image']['src'] = rtrim(site_url(), '/').'/uploads/pharmacies/'.$service_db['pharma_id'].'/services/'.$service_db['img_cover']['src'].'.jpg';
		$service['cover_image']['is_default'] = FALSE;
	}else{
		$service['cover_image'] = [
			'src'    => rtrim(site_url(), '/').'/uploads/images/placeholder-service.jpg',
			'alt'    => 'Immagine servizio',
			'width'  => 1200,
			'height' => 600,
			'is_default' => TRUE
		];
	}

	return $service;
}

function normalize_request_data( $request_db ){
	if( ! $request_db ) return FALSE;

	switch($request_db['status']){
		case 0: $status_label = 'In attesa'; break;
		case 1: $status_label = 'In lavorazione'; break;
		case 2: $status_label = 'Completata'; break;
		case 3: $status_label = 'Rifiutata'; break;
		case 4: $status_label = 'Annulata'; break;
		default: $status_label = 'In attesa'; break;
	}

	$service = [
		'id'           => (int) $request_db['id'],
		'type'         => $request_db['request_type'],
		'description'  => $request_db['message'],
		'status_id'    => (int) $request_db['status'],
		'status_label' => $status_label,
		'created_at'   => $request_db['created_at'],
		'updated_at'   => $request_db['updated_at'],
	];

	return $service;
}

function normalize_event_data( $event_db ){
	if( ! $event_db ) return FALSE;

	$event = [
		'id'               => $event_db['id'],
		'title'            => $event_db['title'],
		"cover_image"      => $event_db['img_cover'],
		'description'      => $event_db['description'],
		'dates'            => [
			$event_db['datetime_start'],
			$event_db['datetime_end'],
		],
		'is_expired'       => $event_db['datetime_end'] !== NULL && date('Y-m-d H:i:s') > $event_db['datetime_end'],
		'has_availability' => !!$event_db['has_availability'],
		'is_featured' => !!$event_db['is_featured'],
		'subscriptions'    => [
			"total"    => $event_db['sub_total'],
			"reserved" => $event_db['sub_reserved'],
			"left"     => $event_db['sub_left'],
		],
	];

	if( $event['cover_image'] ){
		$event['cover_image']['src'] = rtrim(site_url(), '/').'/uploads/pharmacies/'.$event_db['pharma_id'].'/events/'.$event_db['img_cover']['src'].'.jpg';
		$event['cover_image']['is_default'] = FALSE;
	}else{
		$event['cover_image'] = [
			'src'    => rtrim(site_url(), '/').'/uploads/images/placeholder-event.jpg',
			'alt'    => 'Immagine evento',
			'width'  => 1200,
			'height' => 600,
			'is_default' => TRUE
		];
	}

	return $event;
}

function normalize_product_data(array $prod): array {
	$price_regular = (float) $prod['price'];
	$price_sale = $prod['is_on_sale'] && $prod['sale_price'] !== null
		? (float) $prod['sale_price']
		: null;

	$final_price = $price_sale !== null && $price_sale < $price_regular
		? $price_sale
		: $price_regular;

	$quantity = (int) $prod['num_items'];
	$is_active = (int) $prod['is_active'] === 1;

	$price_hidden = empty($price_regular) OR $price_regular <= 0;
	if( $price_hidden ){
		$final_price = NULL;
		$price_regular = NULL;
		$price_sale = NULL;
	}

	// Gestione immagine
	if (empty($prod['image'])) {
		$image = [
			'src'        => site_url() . '/uploads/images/placeholder-product.jpg',
			'alt'        => 'Prodotto senza immagine',
			'width'      => 1024,
			'height'     => 1024,
			'is_default' => TRUE,
		];
	} else {
		$image = [
			// 'src'    => site_url() . '/uploads/drugs/' . $prod['image'],
			// 'src'    => site_url() . '/panel/' . $prod['image'],
			'src'        => str_replace('api.' , 'app.', site_url()) . '/panel/' . $prod['image'],
			'alt'        => 'Immagine prodotto ' . trim($prod['name']),
			'width'      => 1000,
			'height'     => 1000,
			'is_default' => FALSE,
		];

		if( $_SERVER['REMOTE_ADDR'] == '127.0.0.1' ){
			$image['src'] = str_replace('assistente_farmacia_api', 'assistente_farmacia_app', $image['src']);
		}

	}

	return [
		'id'                => (int) $prod['id'],
		'name'              => trim($prod['name']),
		// 'label'             => trim($prod['name']),
		// 'full_label'        => trim($prod['name']),
		'description'       => trim($prod['description'] ?? ''),
		'price'             => $final_price,
		'price_regular'     => $price_regular,
		'price_sale'        => $price_sale,
		'price_hidden'      => $price_hidden,
		'is_on_sale'        => (bool) $prod['is_on_sale'],
		// 'quantity'          => $quantity,
		'sku'               => $prod['sku'] ?? null,
		'image'             => $image,
		'has_low_threshold' => $quantity < get_option('product_min_qty_threshold', 10),
		'is_purchasable'    => $is_active,
		'is_featured'       => isset($prod['is_featured']) ? (!!$prod['is_featured']) : FALSE,
		// 'pharma_id'         => (int) $prod['pharma_id'],
		// 'product_id'        => isset($prod['product_id']) ? (int) $prod['product_id'] : null,
		// 'created_at'        => $prod['created_at'],
		// 'updated_at'        => $prod['updated_at'],
	];
}

function normalize_pill_data($pill_db) {
	if (!$pill_db) return false;

	return [
		'id'        => $pill_db['id'],
		'day'       => $pill_db['day'],
		'category'  => $pill_db['category'],
		'title'     => $pill_db['title'],
		'excerpt'   => $pill_db['excerpt'],
		'content'   => $pill_db['content'],
		// 'metadata'  => json_decode($pill_db['metadata'], true),
		// 'createdAt' => $pill_db['created_at'],
		// 'updatedAt' => $pill_db['updated_at'],
	];
}

// Per altre funzioni di normalizzazione cercare in coda nei file delle model. (Es. normalize_quiz_data() sta dentro _model_quizzes.php)




// URL helpers for assets

// Reminders Therapy Functions

function get_reminders_therapy_by_user_id( $user_id = NULL ){
	if( empty($user_id) ) return FALSE;

	global $pdo;

	$stmt = $pdo->prepare("
		SELECT * FROM jta_reminder_therapy 
		WHERE user_id = ? AND deleted_at IS NULL 
		ORDER BY created_at DESC
	");
	$stmt->execute([$user_id]);
	$reminders = $stmt->fetchAll(PDO::FETCH_ASSOC);

	// Decodifica JSON per i times
	foreach ($reminders as &$reminder) {
		$reminder['times'] = json_decode($reminder['times'], true);
	}

	return $reminders;
}

function get_reminder_therapy_by_id( $reminder_id = NULL, $user_id = NULL ){
	if( empty($reminder_id) || empty($user_id) ) return FALSE;

	global $pdo;

	$stmt = $pdo->prepare("
		SELECT * FROM jta_reminder_therapy 
		WHERE id = ? AND user_id = ? AND deleted_at IS NULL 
		LIMIT 1
	");
	$stmt->execute([$reminder_id, $user_id]);
	$reminder = $stmt->fetch(PDO::FETCH_ASSOC);

	if( $reminder ){
		$reminder['times'] = json_decode($reminder['times'], true);
	}

	return $reminder;
}

function create_reminder_therapy( $user_id = NULL, $data = NULL ){
	if( empty($user_id) || empty($data) ) return FALSE;

	global $pdo;

	$stmt = $pdo->prepare("
		INSERT INTO jta_reminder_therapy (
			user_id, drug_name, dosage, start_date, end_date, 
			frequency, times, notes, file
		) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
	");
	
	$stmt->execute([
		$user_id,
		$data['medicationName'],
		$data['dosage'],
		$data['startDate'],
		$data['endDate'],
		$data['frequency'],
		json_encode($data['times']),
		$data['notes'] ?? null,
		$data['file'] ?? null
	]);

	return $pdo->lastInsertId();
}

function update_reminder_therapy( $reminder_id = NULL, $user_id = NULL, $data = NULL ){
	if( empty($reminder_id) || empty($user_id) || empty($data) ) return FALSE;

	global $pdo;

	$stmt = $pdo->prepare("
		UPDATE jta_reminder_therapy SET 
			drug_name = ?, dosage = ?, start_date = ?, end_date = ?,
			frequency = ?, times = ?, notes = ?, file = ?
		WHERE id = ? AND user_id = ?
	");
	
	$stmt->execute([
		$data['medicationName'],
		$data['dosage'],
		$data['startDate'],
		$data['endDate'],
		$data['frequency'],
		json_encode($data['times']),
		$data['notes'] ?? null,
		$data['file'] ?? null,
		$reminder_id,
		$user_id
	]);

	return $stmt->rowCount() > 0;
}

function delete_reminder_therapy( $reminder_id = NULL, $user_id = NULL ){
	if( empty($reminder_id) || empty($user_id) ) return FALSE;

	global $pdo;

	// Soft delete - imposta deleted_at invece di eliminare
	$stmt = $pdo->prepare("
		UPDATE jta_reminder_therapy 
		SET deleted_at = CURRENT_TIMESTAMP 
		WHERE id = ? AND user_id = ? AND deleted_at IS NULL
	");
	$stmt->execute([$reminder_id, $user_id]);

	return $stmt->rowCount() > 0;
}



// Reminders Expiry Functions

function get_reminders_expiry_by_user_id( $user_id = NULL ){
	if( empty($user_id) ) return FALSE;

	global $pdo;

	$stmt = $pdo->prepare("SELECT * FROM jta_reminders_expiry WHERE user_id = ? ORDER BY expiry_date ASC");
	$stmt->execute([$user_id]);
	$reminders = $stmt->fetchAll(PDO::FETCH_ASSOC);

	// Decodifica JSON per gli alerts
	foreach ($reminders as &$reminder) {
		$reminder['alerts'] = json_decode($reminder['alerts'], true);
	}

	return $reminders;
}

function get_reminder_expiry_by_id( $reminder_id = NULL, $user_id = NULL ){
	if( empty($reminder_id) || empty($user_id) ) return FALSE;

	global $pdo;

	$stmt = $pdo->prepare("SELECT * FROM jta_reminders_expiry WHERE id = ? AND user_id = ? LIMIT 1");
	$stmt->execute([$reminder_id, $user_id]);
	$reminder = $stmt->fetch(PDO::FETCH_ASSOC);

	if( $reminder ){
		$reminder['alerts'] = json_decode($reminder['alerts'], true);
	}

	return $reminder;
}

function create_reminder_expiry( $user_id = NULL, $data = NULL ){
	if( empty($user_id) || empty($data) ) return FALSE;

	global $pdo;

	$stmt = $pdo->prepare("
		INSERT INTO jta_reminders_expiry (
			user_id, product_name, expiry_date, alerts, notes, file
		) VALUES (?, ?, ?, ?, ?, ?)
	");
	
	$stmt->execute([
		$user_id,
		$data['productName'],
		$data['expiryDate'],
		json_encode($data['alerts']),
		$data['notes'] ?? null,
		$data['file'] ?? null
	]);

	return $pdo->lastInsertId();
}

function update_reminder_expiry( $reminder_id = NULL, $user_id = NULL, $data = NULL ){
	if( empty($reminder_id) || empty($user_id) || empty($data) ) return FALSE;

	global $pdo;

	$stmt = $pdo->prepare("
		UPDATE jta_reminders_expiry SET 
			product_name = ?, expiry_date = ?, alerts = ?, notes = ?, file = ?
		WHERE id = ? AND user_id = ?
	");
	
	$stmt->execute([
		$data['productName'],
		$data['expiryDate'],
		json_encode($data['alerts']),
		$data['notes'] ?? null,
		$data['file'] ?? null,
		$reminder_id,
		$user_id
	]);

	return $stmt->rowCount() > 0;
}

function delete_reminder_expiry( $reminder_id = NULL, $user_id = NULL ){
	if( empty($reminder_id) || empty($user_id) ) return FALSE;

	global $pdo;

	$stmt = $pdo->prepare("DELETE FROM jta_reminders_expiry WHERE id = ? AND user_id = ?");
	$stmt->execute([$reminder_id, $user_id]);

	return $stmt->rowCount() > 0;
}

// Normalize functions for reminders

function normalize_reminder_therapy_data( $reminder_db ){
	if( ! $reminder_db ) return FALSE;

	// Costruisci gli URL completi per il file se presente
	$file_url = null;
	$view_url = null;
	if( $reminder_db['file'] ){
		// $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
		$base_url = site_url();
		
		// Genera sempre un nuovo token valido per l'utente
		$user_id = $reminder_db['user_id'];
		$user = get_user_by_id($user_id);
		if ($user) {
			$payload = [
				'sub' => $user_id,
				'username' => $user['slug_name'],
				'exp' => time() + 3600 // Scade in 1 ora
			];
			$token = getJwtEncoded($payload);
		}
		
		$file_url = $base_url . "/download-file.php?file=" . urlencode($reminder_db['file']);
		$view_url = $base_url . "/view-file.php?file=" . urlencode($reminder_db['file']);
		
		if ($token) {
			$file_url .= "&token=" . urlencode($token);
			$view_url .= "&token=" . urlencode($token);
		}
	}

	$reminder = [
		'id'             => $reminder_db['id'],
		'medicationName' => $reminder_db['drug_name'],
		'dosage'         => $reminder_db['dosage'],
		'startDate'      => $reminder_db['start_date'],
		'endDate'        => $reminder_db['end_date'],
		'frequency'      => $reminder_db['frequency'],
		'times'          => $reminder_db['times'],
		'notes'          => $reminder_db['notes'],
		'file'           => $reminder_db['file'], // Percorso relativo per compatibilità
		'fileUrl'        => $file_url, // URL completo per download
		'viewUrl'        => $view_url, // URL completo per visualizzazione
		'createdAt'      => $reminder_db['created_at'],
		'deletedAt'      => $reminder_db['deleted_at'],
	];

	return $reminder;
}

function normalize_reminder_expiry_data( $reminder_db ){
	if( ! $reminder_db ) return FALSE;

	// Costruisci gli URL completi per il file se presente
	$file_url = null;
	$view_url = null;
	if( $reminder_db['file'] ){
		// $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
		$base_url = site_url();

		// Genera sempre un nuovo token valido per l'utente
		$user_id = $reminder_db['user_id'];
		$user = get_user_by_id($user_id);
		if ($user) {
			$payload = [
				'sub' => $user_id,
				'username' => $user['slug_name'],
				'exp' => time() + 3600 // Scade in 1 ora
			];
			$token = getJwtEncoded($payload);
		}
		
		$file_url = $base_url . "download-file.php?file=" . urlencode($reminder_db['file']);
		$view_url = $base_url . "view-file.php?file=" . urlencode($reminder_db['file']);
		
		if ($token) {
			$file_url .= "&token=" . urlencode($token);
			$view_url .= "&token=" . urlencode($token);
		}
	}

	$reminder = [
		'id'          => $reminder_db['id'],
		'productName' => $reminder_db['product_name'],
		'expiryDate'  => $reminder_db['expiry_date'],
		'alerts'      => $reminder_db['alerts'],
		'notes'       => $reminder_db['notes'],
		'file'        => $reminder_db['file'], // Percorso relativo per compatibilità
		'fileUrl'     => $file_url, // URL completo per download
		'viewUrl'     => $view_url, // URL completo per visualizzazione
		'completed'   => (bool)$reminder_db['completed'],
		'createdAt'   => $reminder_db['created_at'],
	];

	return $reminder;
}

function get_pharma_img_src( $pharma_id = NULL, $filename = NULL ){
	if( isset($pharma_id, $filename) ){
		return rtrim(site_url(), '/').'/uploads/pharmacies/'.$pharma_id.'/'.$filename;
	}
	return;
}
