<?php

require_once realpath(__DIR__ . '/../config.php');

try {
	if (!isset($_GET['region']) || !isset($_GET['str'])) {
		throw new Exception('No data');
	}

	if (
		!is_string($region = $_GET['region'])
		|| !is_string($str = $_GET['str'])
	) {
		throw new Exception('Wrong data');
	}

	/*
	 * String
	 */

	$sourseStr = $str;

	// Заменяем все подряд идущие пробельные знаки на один пробел + trim
	$str = trim(mb_ereg_replace('\s+', ' ', $str));

	if (mb_strlen($str) < MIN_LENGTH) {
		throw new Exception('Trim string must be min length: ' . MIN_LENGTH);
	}
	
	// К нижнему регистру
	$str = mb_strtolower($str);

	/*
	 * Region
	 */

	/*if (
		!mb_ereg_match('^\d{2}$', $region)
		|| intval($region) == 0
	) {
		throw new Exception('Wrong region');
	}*/

	/*
	 * Count
	 */

	if (!isset($_GET['count'])) {
		$limit = COUNT_HINTS;
	} elseif (($limit = intval($_GET['count'])) < 1) {
		$limit = 1;
	} elseif ($limit > MAX_COUNT_HINTS) {
		$limit = MAX_COUNT_HINTS;
	}

	/*
	 * DB connection.
	 */

	$db = new PDO(DBSTRING);
	$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, TRUE);

	/*
	 * Find region.
	 */

	$sql = <<<SQL
SELECT * FROM addrobj WHERE parentguid IS NULL AND regioncode = :region LIMIT 1;
SQL;
	$prepare = $db->prepare($sql);
	$prepare->bindValue(':region', $region);
	$prepare->execute();
	if ($prepare->fetchAll(PDO::FETCH_ASSOC)) {
		throw new Exception('Wrong region');
	}
	
	// Проверяем, содержит ли фраза полный адрес адресного объекта
	// Отсекаем части с конца по пробелам. Убираем зяпятые в конце.
	$sql = <<<SQL
SELECT * FROM addrobj WHERE regioncode = :region AND lower(address) = :str LIMIT 1;
SQL;
	$prepare = $db->prepare($sql);
	$prepare->bindValue(':region', $region);
	$prepare->bindParam(':str', $part);

	$parent = NULL;
	$part = $str;
	while ($part) {
		if (mb_substr($part, -1) == ',') {
			$part = mb_substr($part, 0, mb_strlen($part) - 1);
		}
		$prepare->execute();
		if ($res = $prepare->fetchAll(PDO::FETCH_ASSOC)) {
			$parent = $res[0];
			break;
		}
		$part = mb_strrchr($part, ' ', TRUE);
	}

	if ($parent) {
		$str = mb_substr($str, mb_strlen($parent['address']));
		if (mb_substr($str, 0, 1) == ',') {
			$str = mb_substr($str, 1);
		}
		$str = trim($str);

		// Если финальный объект - ищем дома
		if ($parent['final'] == 't') {		
			$result = NULL;
			// Проверяем на полное соответствие
			if ($str != '') {
				$sql = <<<SQL
SELECT * FROM house WHERE aoguid = :aoguid AND lower(number) = :str LIMIT 1;
SQL;
				$prepare = $db->prepare($sql);
				$prepare->bindValue(':aoguid', $parent['aoguid']);
				$prepare->bindValue(':str', $str);
				$prepare->execute();
				if ($res = $prepare->fetchAll(PDO::FETCH_ASSOC)) {
					$finalStr = $parent['address'] . ', ' . $res[0]['number'];
					if ($sourseStr == $finalStr) {
						$result = [
							'final' => TRUE,
						];
					} else {
						$result = [
							'final' => FALSE,
							'items' => [
								[
									'address' => $finalStr,
								],
							],
						];
					}
				}
			}

			if (!$result) {
				if ($str == '') {
					$sql = <<<SQL
SELECT * FROM house WHERE aoguid = :aoguid
	ORDER BY lower(number) LIMIT :limit;
SQL;
				} else {
					$sql = <<<SQL
SELECT * FROM house WHERE aoguid = :aoguid
	ORDER BY similarity(number, :str) DESC LIMIT :limit;
SQL;
				}
				$prepare = $db->prepare($sql);
				$prepare->bindValue(':aoguid', $parent['aoguid']);
				$prepare->bindValue(':limit', $limit, PDO::PARAM_INT);
				if ($str != '') {
					$prepare->bindValue(':str', $str);
				}
				$prepare->execute();
				if ($res = $prepare->fetchAll(PDO::FETCH_ASSOC)) {
					$result = [
						'final' => FALSE,
						'items' => [],
					];
					foreach ($res as $item) {
						$result['items'][] = [
							'address' => $parent['address'] . ', ' . $item['number'],
						];
					}
				// Если у финального объекта нет домов
				} else {
					// Если поисковая строка равна полному адресу финального объекта
					if ($sourseStr == $parent['address']) {
						$result = [
							'final' => TRUE,
						];
					} else {
						$result = [
							'final' => FALSE,
							'items' => [
								[
									'address' => $parent['address'],
								],
							],
						];
					}
				}
			}
		// Иначе выводим подсказки по адресам
		} else {
			if ($str != '') {
				$sql = <<<SQL
SELECT * FROM (
	WITH RECURSIVE r AS (
		SELECT * FROM addrobj WHERE aoguid = :aoguid
		UNION ALL
		SELECT ao.* FROM addrobj ao
			JOIN r
				ON ao.parentguid = r.aoguid
					AND r.final = 'f'
	)
	SELECT * FROM r
) as tmp
WHERE aoguid != :aoguid
ORDER BY similarity(substring(address, :parAddrLen), :str) DESC LIMIT :limit;
SQL;
			} else {
				$sql = <<<SQL
SELECT * FROM addrobj WHERE parentguid = :aoguid
ORDER BY lower(formalname) LIMIT :limit;
SQL;
			}
			$prepare = $db->prepare($sql);
			$prepare->bindValue(':aoguid', $parent['aoguid']);
			$prepare->bindValue(':limit', $limit, PDO::PARAM_INT);
			if ($str != '') {
				$prepare->bindValue(':parAddrLen', mb_strlen($parent['address']) + 1, PDO::PARAM_INT);
				$prepare->bindValue(':str', $str);
			}
			$prepare->execute();
			$res = $prepare->fetchAll(PDO::FETCH_ASSOC);
			
			$result = [
				'final' => FALSE,
				'items' => [],
			];
			foreach ($res as $item) {
				$result['items'][] = [
					'address' => $item['address'],
				];
			}
		}
	} else {
		$res = NULL;
		while (!$res) {
			$sql = <<<SQL
SELECT * FROM addrobj WHERE regioncode = :region AND address % :str
ORDER BY similarity(address, :str) DESC LIMIT :limit;
SQL;
			$prepare = $db->prepare($sql);
			$prepare->bindValue(':region', $region);
			$prepare->bindValue(':limit', $limit, PDO::PARAM_INT);
			$prepare->bindValue(':str', $str);
			$prepare->execute();
			if (!($res = $prepare->fetchAll(PDO::FETCH_ASSOC))) {
				$sql = <<<SQL
SELECT set_limit(show_limit() - 0.1);
SQL;
				$db->exec($sql);
			}
		}

		$result = [
			'final' => FALSE,
			'items' => [],
		];
		foreach ($res as $item) {
			$result['items'][] = [
				'address' => $item['address'],
			];
		}
	}
	
	$result['success'] = TRUE;

} catch (Exception $e) {
	$result = [
		'success' => FALSE,
		'error' => $e->getMessage(),
	];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($result);
