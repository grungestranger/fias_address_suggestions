<?php

require_once __DIR__ . '/config.php';

const URL = 'http://fias.nalog.ru/Public/Downloads/Actual/fias_xml.rar';
const ARCHIVE = __DIR__ . '/uploads/fias_xml.rar';
const EXTRACT_DIR = __DIR__ . '/uploads/fias_xml';
const SQL_DIR = __DIR__ . '/sql';

const ROWS_PER_INSERT = 1000;

const MAX_PLAIN_CODE_LENGTH = 15;

const CREATE_TABLES_SQL = 'createTables.sql';
const MODIFY_TABLES_SQL = 'modifyTables.sql';
const TABLES = [
	[
		'name' => 'addrobj',
		'fields' => [
			'plaincode',
			'aoguid',
			'parentguid',
			'aolevel',
			'formalname',
			'postalcode',
			'shortname',
			'regioncode',
		],
		'node' => 'Object',
		'file' => 'AS_ADDROBJ_',
	],
	[
		'name' => 'house',
		'fields' => [
			'houseguid',
			'aoguid',
			'housenum',
			'eststatus',
			'buildnum',
			'strucnum',
			'strstatus',
			'postalcode',
		],
		'node' => 'House',
		'file' => 'AS_HOUSE_',
	],
];

try {
	// Загрузка файла
	$file = fopen(ARCHIVE, 'w');
	$curl = curl_init(URL);
	curl_setopt($curl, CURLOPT_FILE, $file);
	curl_exec($curl);
	$curlError = curl_error($curl);
	curl_close($curl);
	fclose($file);

	if ($curlError) {
		throw new Exception($curlError);
	}

	// Распаковка файла
	exec('unrar e ' . ARCHIVE . ' ' . EXTRACT_DIR . ' 2>&1', $output, $result);
	if ($result !== 0) {
		throw new Exception('Ошибка разархивации: ' . implode("\n", $output));
	}
	if (!unlink(ARCHIVE)) {
		throw new Exception('Не удалось удалить архив: ' . ARCHIVE);
	}

	// Находим нужные файлы
	$files = [];
	foreach (scandir(EXTRACT_DIR) as $i) {
		if (is_file($file = EXTRACT_DIR . '/' . $i)) {
			$flag = FALSE;
			foreach (TABLES as $table) {
				if (!isset($files[$table['name']])) {
					if (mb_strpos($i, $table['file']) === 0) {
						$files[$table['name']] = $file;
						$flag = TRUE;
						break;
					}
				}
			}
			if (!$flag && !unlink($file)) {
				throw new Exception('Не удалось удалить файл:' . $file);
			}
		}
	}
	foreach (TABLES as $table) {
		if (!isset($files[$table['name']])) {
			throw new Exception('Нет файла: ' . $table['name']);
		}
	}

	// Соединение с бд
	$db = new PDO(DBSTRING);
	$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	
	// Создание таблиц
	$sql_file = SQL_DIR . '/' . CREATE_TABLES_SQL;
	if (!($sql = file_get_contents($sql_file))) {
		throw new Exception('Не удается открыть файл: ' . $sql_file);
	}
	$db->exec($sql);

	foreach (TABLES as $table) {
		// Открываем и обрабатываем XML
		$file = $files[$table['name']];
		$reader = new XMLReader();
		if (!$reader->open($file)) {
			throw new Exception('Ошибка открытия xml файла: ' . $file);
		}

		$result = TRUE;
		while ($result) {
			$result = [];
			$i = 0;
			while ($i < ROWS_PER_INSERT && $reader->read()) {
				switch ($table['name']) {
					case 'addrobj':
						$filter = $reader->getAttribute('CURRSTATUS') == 0 ? TRUE : FALSE;
						break;
					case 'house':
						$filter = $reader->getAttribute('ENDDATE')
							> date('Y-m-d', time() - 3600 * 24 * 14) ? TRUE : FALSE;
						break;
					default:
						$filter = TRUE;
				}
				if ($reader->name == $table['node'] && $filter) {
					$item = [];
					foreach ($table['fields'] as $v) {
						$attribute = $reader->getAttribute(mb_strtoupper($v));
						if ($v == 'plaincode' && $attribute) {
							$attribute = str_pad($attribute, MAX_PLAIN_CODE_LENGTH, '0');
						}
						$item[$v] = $attribute;
					}
					$result[] = $item;
					$i++;
				}
			}
			// Запись в бд
			if ($result) {
				$count = count($result);
				$sql = 'INSERT INTO ' . $table['name'] . '_tmp' . ' (' . implode(', ', $table['fields']) . ') VALUES ';
				
				for ($i = 0; $i < $count; $i++) {
					$sql .= '(:' . implode($i . ', :', $table['fields']) . $i . ')' . ($i == $count - 1 ? ' ' : ', ');
				}
				$sql .= 'ON CONFLICT DO NOTHING;';
				$prepare = $db->prepare($sql);
				foreach ($result as $ind => $row) {
					foreach ($row as $k => $v) {
						$prepare->bindValue(':'.$k.$ind, $v);
					}
				}
				$prepare->execute();
			}
		}

		// Удаление файла
		if (!unlink($file)) {
			throw new Exception('Не удалось удалить файл: ' . $file);
		}
	}
	
	// Модификация таблиц
	$sql_file = SQL_DIR . '/' . MODIFY_TABLES_SQL;
	if (!($sql = file_get_contents($sql_file))) {
		throw new Exception('Не удается открыть файл: ' . $sql_file);
	}
	$db->exec($sql);

} catch (Exception $e) {
	echo $e->getMessage();
}
