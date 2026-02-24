<?php

class OptionsModel {
	public static function get(string $name) {
		global $pdo;

		$stmt = $pdo->prepare("SELECT option_value FROM jta_options WHERE option_name = :name LIMIT 1");
		$stmt->execute([':name' => $name]);
		$result = $stmt->fetch(PDO::FETCH_ASSOC);

		if (!$result) {
			return false;
		}

		$value = $result['option_value'];
		$unserialized = @unserialize($value);
		return $unserialized !== false || $value === 'b:0;' ? $unserialized : $value;
	}

	public static function insert(string $name, $value): bool {
		global $pdo;

		if (self::exists($name)) {
			return self::update($name, $value);
		}

		$value = is_array($value) ? serialize($value) : $value;

		$stmt = $pdo->prepare("INSERT INTO jta_options (option_name, option_value) VALUES (:name, :value)");
		return $stmt->execute([
			':name'  => $name,
			':value' => $value
		]);
	}

	public static function update(string $name, $value): bool {
		global $pdo;

		if (!self::exists($name)) {
			return self::insert($name, $value);
		}

		$value = is_array($value) ? serialize($value) : $value;

		$stmt = $pdo->prepare("UPDATE jta_options SET option_value = :value WHERE option_name = :name");
		return $stmt->execute([
			':name'  => $name,
			':value' => $value
		]);
	}

	public static function delete(string $name): bool {
		global $pdo;

		$stmt = $pdo->prepare("DELETE FROM jta_options WHERE option_name = :name");
		return $stmt->execute([':name' => $name]);
	}

	public static function exists(string $name): bool {
		global $pdo;

		$stmt = $pdo->prepare("SELECT 1 FROM jta_options WHERE option_name = :name LIMIT 1");
		$stmt->execute([':name' => $name]);
		return (bool) $stmt->fetchColumn();
	}
}

function get_option(string $name, $default = null) {
	$value = OptionsModel::get($name);
	return $value === false ? $default : $value;
}

function insert_option(string $name, $value): bool {
	return OptionsModel::insert($name, $value);
}

function update_option(string $name, $value): bool {
	return OptionsModel::update($name, $value);
}

function delete_option(string $name): bool {
	return OptionsModel::delete($name);
}
