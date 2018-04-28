<?php

require_once realpath(__DIR__ . '/../config.php');

try {
	if (!isset($_GET['aoguid']) || !isset($_GET['str'])) {
		throw new Exception('No data');
	}

	$aoguid = $_GET['aoguid'];
	$str = $_GET['str'];
	
	if (!is_string($str)) {
		throw new Exception('Wrong data');
	}

	// Заменяем все подряд идущие пробельные знаки на один пробел + trim
	$str = trim(mb_ereg_replace('\s+', ' ', $str));

	if (mb_strlen($str) < MIN_LENGTH) {
		throw new Exception('Trim string must be min length: ' . MIN_LENGTH);
	}
	
	// К нижнему регистру
	$str = mb_strtolower($str);

	// Соединение с бд
	$db = new PDO(DBSTRING);
	$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	// Находим родительский объект
	$sql = 'SELECT * FROM addrobj WHERE aoguid = :aoguid LIMIT 1;';
	$prepare = $db->prepare($sql);
	$prepare->bindValue(':aoguid', $aoguid);
	$prepare->execute();
	if (!($res = $prepare->fetchAll(PDO::FETCH_ASSOC))) {
		throw new Exception('Wrong parent fias aoguid');
	}
	$parentObject = $res[0];
	
	$parentAddress = mb_strtolower($parentObject['address']) . ', ';
	
	$fullStr = $parentAddress . $str;
	
	// Проверяем, содержит ли фраза полный адрес финального адресного объекта
	// Отсекаем части с конца по пробелам. Убираем зяпятые в конце.
	$sql = 'SELECT * FROM (
	WITH RECURSIVE r AS (
		SELECT * FROM addrobj WHERE aoguid = :aoguid
		UNION ALL
		SELECT ao.* FROM addrobj ao
			JOIN r
				ON ao.parentguid = r.aoguid
	)
	SELECT * FROM r
) as tmp
WHERE lower(address) = :str
	AND final = \'t\'
LIMIT 1;';
	$prepare = $db->prepare($sql);
	$prepare->bindValue(':aoguid', $aoguid);
	$prepare->bindParam(':str', $part);

	$finalObject = NULL;
	$part = $fullStr;
	$i = 0;
	while ($part) {
		if (mb_substr($part, -1) == ',') {
			$part = mb_substr($part, 0, mb_strlen($part) - 1);
		}
		$prepare->execute();
		if ($res = $prepare->fetchAll(PDO::FETCH_ASSOC)) {
			$finalObject = $res[0];
			break;
		}
		$part = mb_strrchr($part, ' ', TRUE);
		$i++;
	}
	
	// Если финальный объект - ищем дома
	if ($finalObject) {
		if ($i) {
			$exp = explode(' ', $fullStr);
			$countExp = count($exp);
			$houseStr = '';
			for ($i = $countExp - $i; $i <= $countExp - 1; $i++) {
				$houseStr .= $exp[$i];
			}
		} else {
			$houseStr = NULL;
		}
		
		$result = NULL;
		// Проверяем на полное соответствие
		if ($houseStr) {
			$sql = 'SELECT * FROM house WHERE aoguid = :aoguid AND lower(number) = :str LIMIT 1;';
			$prepare = $db->prepare($sql);
			$prepare->bindValue(':aoguid', $finalObject['aoguid']);
			$prepare->bindValue(':str', $houseStr);
			$prepare->execute();
			if ($res = $prepare->fetchAll(PDO::FETCH_ASSOC)) {
				$result = [
					'final' => TRUE,
				];
			}
		}

		if (!$result) {
			$sql = 'SELECT * FROM house WHERE aoguid = :aoguid' .
				($houseStr ? ' ORDER BY similarity(number, :str) DESC' : '') . ' LIMIT 10;';
			$prepare = $db->prepare($sql);
			$prepare->bindValue(':aoguid', $finalObject['aoguid']);
			if ($houseStr) {
				$prepare->bindValue(':str', $houseStr);
			}
			$prepare->execute();
			if ($res = $prepare->fetchAll(PDO::FETCH_ASSOC)) {
				$result = [
					'final' => FALSE,
					'items' => [],
				];
				foreach ($res as $item) {
					$result['items'][] = $finalObject['address'] . ', ' . $item['number'];
				}
			// Если у финального объекта нет домов
			} else {
				// Если поисковая строка равна полному адресу финального объекта
				if ($fullStr == mb_strtolower($finalObject['address'])) {
					$result = [
						'final' => TRUE,
					];
				} else {
					$result = [
						'final' => FALSE,
						'items' => [
							$finalObject['address']
						],
					];
				}
			}
		}
	// Иначе выводим подсказки по адресам
	} else {
		$sql = 'SELECT * FROM (
	WITH RECURSIVE r AS (
		SELECT * FROM addrobj WHERE aoguid = :aoguid
		UNION ALL
		SELECT ao.* FROM addrobj ao
			JOIN r
				ON ao.parentguid = r.aoguid
	)
	SELECT * FROM r
) as tmp
ORDER BY similarity(address, :str) DESC LIMIT 10;';
		$prepare = $db->prepare($sql);
		$prepare->bindValue(':aoguid', $aoguid);
		$prepare->bindValue(':str', $str);
		$prepare->execute();
		$res = $prepare->fetchAll(PDO::FETCH_ASSOC);
		
		$result = [
			'final' => FALSE,
			'items' => [],
		];
		foreach ($res as $item) {
			$result['items'][] = $item['address'];
		}
	}
	
	// Обрезаем адрес родительского объекта у подсказок
	if (isset($result['items'])) {
		foreach ($result['items'] as &$item) {
			$pAStrLen = mb_strlen($parentAddress);
			$item = mb_substr($item, $pAStrLen);
		}
		unset($item);
	}
	
	$result['success'] = TRUE;

} catch (Exception $e) {
	//echo $e->getMessage();
	$result = [
		'success' => FALSE,
		'error' => $e->getMessage(),
	];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($result);
