<?php if( ! defined('JTA') ){ header('HTTP/1.0 403 Forbidden'); exit('Direct access is not permitted.'); }

/**
 * Helper per la gestione dei file upload
 */

// Configurazione upload
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('UPLOAD_ALLOWED_TYPES', [
    'application/pdf',
    'image/jpeg',
    'image/jpg',
    'image/png',
    'image/gif',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'text/plain'
]);

define('UPLOAD_ALLOWED_EXTENSIONS', [
    'pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx', 'txt'
]);

/**
 * Valida un file upload
 */
function validateUploadedFile($file) {
    $errors = [];
    
    // Controlla se il file è stato caricato
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Errore nel caricamento del file';
        return $errors;
    }
    
    // Controlla dimensione
    if ($file['size'] > UPLOAD_MAX_SIZE) {
        $errors[] = 'Il file è troppo grande. Dimensione massima: ' . formatBytes(UPLOAD_MAX_SIZE);
    }
    
    // Controlla tipo MIME
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, UPLOAD_ALLOWED_TYPES)) {
        $errors[] = 'Tipo di file non supportato. Tipi permessi: ' . implode(', ', UPLOAD_ALLOWED_EXTENSIONS);
    }
    
    // Controlla estensione
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, UPLOAD_ALLOWED_EXTENSIONS)) {
        $errors[] = 'Estensione file non supportata. Estensioni permesse: ' . implode(', ', UPLOAD_ALLOWED_EXTENSIONS);
    }
    
    return $errors;
}

/**
 * Salva un file upload nella cartella dell'utente
 */
function saveUploadedFile($file, $userId, $folder = 'terapies') {
    try {
        // Crea la struttura delle cartelle
        $userDir = "uploads/users/{$userId}";
        
        if (!is_dir($userDir)) {
            mkdir($userDir, 0755, true);
        }
        
        // Genera nome file unico con prefisso per tipo
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $timestamp = time();
        $randomString = bin2hex(random_bytes(8));
        $prefix = ($folder === 'terapies') ? 'therapy' : 'expiry';
        $filename = "{$prefix}_{$timestamp}_{$randomString}.{$extension}";
        
        $filepath = "{$userDir}/{$filename}";
        
        // Sposta il file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Restituisci il percorso relativo per il database
            return "users/{$userId}/{$filename}";
        } else {
            throw new Exception('Errore nel salvataggio del file');
        }
        
    } catch (Exception $e) {
        throw new Exception('Errore nel salvataggio del file: ' . $e->getMessage());
    }
}

/**
 * Elimina un file
 */
function deleteUploadedFile($filepath) {
    $fullPath = "uploads/{$filepath}";
    
    if (file_exists($fullPath)) {
        return unlink($fullPath);
    }
    
    return false;
}

/**
 * Ottiene il percorso completo di un file
 */
function getUploadedFilePath($filepath) {
    return site_path() . "/uploads/{$filepath}";
}

/**
 * Controlla se un file esiste
 */
function fileExists($filepath) {
    return file_exists(getUploadedFilePath($filepath));
}

/**
 * Formatta bytes in formato leggibile
 */
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Ottiene informazioni su un file
 */
function getFileInfo($filepath) {
    $fullPath = getUploadedFilePath($filepath);
    
    if (!file_exists($fullPath)) {
        return null;
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $fullPath);
    finfo_close($finfo);
    
    return [
        'name' => basename($filepath),
        'size' => filesize($fullPath),
        'size_formatted' => formatBytes(filesize($fullPath)),
        'mime_type' => $mimeType,
        'extension' => strtolower(pathinfo($filepath, PATHINFO_EXTENSION)),
        'created_at' => date('Y-m-d H:i:s', filectime($fullPath))
    ];
}

/**
 * Pulisce i file orfani (opzionale, per manutenzione)
 */
function cleanupOrphanFiles() {
    // Implementazione per pulire file orfani
    // Da chiamare periodicamente o manualmente
}

/**
 * Ridimensiona un'immagine (da $_FILES o da path) con Zebra_Image e la salva come JPG randomico.
 *
 * @param array|string $input              $_FILES['...'] oppure path file
 * @param string       $output_folder_path Cartella di destinazione (senza slash finale)
 *
 * @return false|array FALSE se fallisce, altrimenti array con info file
 */
function minimize_image($input, string $output_folder_path, $new_filename = NULL ) {
	// Normalizza $source_path
	if (is_array($input) && isset($input['tmp_name'])) {
		// proviene da $_FILES
		if (!isset($input['error']) || $input['error'] !== UPLOAD_ERR_OK) {
			// write_log('minimize_image #1');
			return false;
		}
		$source_path = $input['tmp_name'];
		$original_name = $input['name'];
		$file_size = $input['size'];
	} elseif (is_string($input)) {
		// proviene da path locale
		if (!file_exists($input) || !is_readable($input)) {
			// write_log('minimize_image #2');
			return false;
		}
		$source_path = $input;
		$original_name = basename($input);
		$file_size = filesize($input);
	} else {
		// write_log('minimize_image #3');
		return false;
	}

	// dimensione max 10MB
	if ($file_size > 10 * 1024 * 1024) {
		// write_log('minimize_image #4');
		return false;
	}

	// MIME e dimensioni
	$info = @getimagesize($source_path);
	if ($info === false) {
		// write_log('minimize_image #5');
		return false;
	}

	[$width, $height] = $info;
	$mime = $info['mime'];

	// tipi supportati
	$allowedMime = ['image/jpeg', 'image/png', 'image/gif'];
	if (!in_array($mime, $allowedMime, true)) {
		// write_log('minimize_image #6');
		return false;
	}

	// max dimensione 8000px
	if ($width > 8000 || $height > 8000) {
		// write_log('minimize_image #7');
		return false;
	}

	if( $new_filename ){
		$filename = $new_filename . '.jpg';
	}else{
		// nome randomico .jpg
		$filename = bin2hex(random_bytes(16)) . '.jpg';
	}
	$destination = rtrim($output_folder_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

	// Zebra_Image setup
	$image = new stefangabos\Zebra_Image\Zebra_Image();
	$image->source_path = $source_path;
	$image->target_path = $destination;
	$image->jpeg_quality = 90;
	$image->preserve_aspect_ratio = true;
	$image->enlarge_smaller_images = false;
	$image->preserve_time = true;

	// Ridimensiona max 2000px
	if (!$image->resize(2000, 2000, ZEBRA_IMAGE_NOT_BOXED)) {
		// write_log('minimize_image #8');

		// if there was an error, let's see what the error is about
		// switch ($image->error) {
		// 	case 1: write_log('Source file could not be found'); break;
		// 	case 2: write_log('Source file is not readable'); break;
		// 	case 3: write_log('Could not write target file'); break;
		// 	case 4: write_log('Unsupported source file type'); break;
		// 	case 5: write_log('Unsupported target file type'); break;
		// 	case 6: write_log('GD library version does not support target file format'); break;
		// 	case 7: write_log('GD library is not installed'); break;
		// 	case 8: write_log('"chmod" command is disabled via configuration'); break;
		// 	case 9: write_log('"exif_read_data" function is not available'); break;
		// }

		return false;
	}

	// info sul file creato
	$finalInfo = @getimagesize($destination);
	if ($finalInfo === false) {
		// write_log('minimize_image #9');
		return false;
	}

	[$finalWidth, $finalHeight] = $finalInfo;
	$finalMime = $finalInfo['mime'];
	$filesize = filesize($destination);

	$destination = str_replace("\\", '/', $destination);
	$destination = str_replace("//", '/', $destination);

	return [
		'path'          => str_replace(site_path(), '', $destination),
		'folder'        => str_replace(site_path(), '', $output_folder_path),
		'filename'      => $filename,
		'filename_noext'=> pathinfo($filename, PATHINFO_FILENAME),
		'extension'     => pathinfo($filename, PATHINFO_EXTENSION),
		'mime'          => $finalMime,
		'size'          => $filesize,
		'width'         => $finalWidth,
		'height'        => $finalHeight,
	];
}

function jt_mkdir( $dir, $recursive = TRUE ){
	if( $dir === NULL OR $dir === FALSE ){
		return FALSE;
	}

	if( ! file_exists( $dir ) && ! is_dir( $dir ) ){
		mkdir( $dir, 0777, $recursive );
		return TRUE;
	} 

	return FALSE;
}

/**
 * Controlla se un file caricato corrisponde ai formati consentiti
 *
 * @param array $file L'array di $_FILES['...']
 * @param array $allowedExt Estensioni consentite
 * @param array $allowedMime MIME consentiti
 * @return bool
 */
function is_valid_upload_type(array $file, array $allowedExt, array $allowedMime): bool {
	if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
		return false;
	}

	// Estensione
	$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
	if (!in_array($ext, $allowedExt, true)) {
		return false;
	}

	// MIME
	$finfo = finfo_open(FILEINFO_MIME_TYPE);
	$mime  = finfo_file($finfo, $file['tmp_name']);
	finfo_close($finfo);

	if (!in_array($mime, $allowedMime, true)) {
		return false;
	}

	return true;
}

/**
 * Verifica se il file è un'immagine (jpg, jpeg, png, gif)
 *
 * @param array $file
 * @return bool
 */
function is_upload_image(array $file): bool {
	$allowedExt  = ['jpg', 'jpeg', 'png', 'gif'];
	$allowedMime = ['image/jpeg', 'image/png', 'image/gif'];

	return is_valid_upload_type($file, $allowedExt, $allowedMime);
}

/**
 * Verifica se il file è un PDF
 *
 * @param array $file
 * @return bool
 */
function is_upload_pdf(array $file): bool {
	$allowedExt  = ['pdf'];
	$allowedMime = ['application/pdf'];

	return is_valid_upload_type($file, $allowedExt, $allowedMime);
}

/**
 * Restituisce i metadati di un PDF
 *
 * @param string $pdf_fullpath Percorso completo del file PDF
 * @return array|null Ritorna array metadati oppure null se file non valido
 */
function get_pdf_info(string $pdf_fullpath): ?array {
	if (!is_file($pdf_fullpath)) {
		return null;
	}

	$filename   = basename($pdf_fullpath);
	$filesize   = filesize($pdf_fullpath);

	// MIME con Fileinfo
	$finfo     = finfo_open(FILEINFO_MIME_TYPE);
	$finalMime = finfo_file($finfo, $pdf_fullpath);
	finfo_close($finfo);

	$destination = $pdf_fullpath;
	$output_folder_path = str_replace($filename, '', $destination);

	$destination = str_replace("\\", '/', $destination);
	$destination = str_replace("//", '/', $destination);

	return [
		'path'          => str_replace(site_path(), '', $destination),
		'folder'        => str_replace(site_path(), '', $output_folder_path),
		'filename'       => $filename,
		'filename_noext' => pathinfo($filename, PATHINFO_FILENAME),
		'extension'      => pathinfo($filename, PATHINFO_EXTENSION),
		'mime'           => $finalMime,
		'size'           => $filesize,
		'width'          => null,
		'height'         => null,
	];
}
