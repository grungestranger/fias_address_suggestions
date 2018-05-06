<?php

require_once __DIR__ . '/config.php';

const URL = 'http://fias.nalog.ru/Public/Downloads/Actual/fias_xml.rar';
const ARCHIVE = __DIR__ . '/uploads/fias_xml.rar';
const EXTRACT_DIR = __DIR__ . '/uploads/fias_xml';
const CREATE_TABLES_SQL = __DIR__ . '/sql/createTables.sql';
const MODIFY_TABLES_SQL = __DIR__ . '/sql/modifyTables.sql';
const ROWS_PER_INSERT = 1000;
const MAX_PLAIN_CODE_LENGTH = 15;
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
		'filter' => [
			[
				'field' => 'CURRSTATUS',
				'type' => 'equal',
				'value' => 0,
			],
		],
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
		'filter' => [
			[
				'field' => 'ENDDATE',
				'type' => 'dateUnder',
				'days' => 14,
			],
		],
	],
];

define('TIME', time());

try {

	/*
	 * Download archive.
	 */

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

	/*
	 * Create extract dir.
	 */

	if (!mkdir(EXTRACT_DIR)) {
		throw new Exception('Unable to create extract dir: ' . EXTRACT_DIR);
	}

	/*
	 * Unpack the archive.
	 */

	exec('unrar e ' . ARCHIVE . ' ' . EXTRACT_DIR . ' 2>&1', $output, $result);
	if ($result !== 0) {
		throw new Exception('Unarchiving error: ' . implode("\n", $output));
	}
	if (!unlink(ARCHIVE)) {
		throw new Exception('Unable to delete archive: ' . ARCHIVE);
	}

	/*
	 * Search for needed files.
	 */

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
				throw new Exception('Unable to delete file:' . $file);
			}
		}
	}
	foreach (TABLES as $table) {
		if (!isset($files[$table['name']])) {
			throw new Exception('File not found: ' . $table['name']);
		}
	}

	/*
	 * DB connection.
	 */

	$db = new PDO(DBSTRING);
	$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	/*
	 * Create tables.
	 */

	if (!($sql = file_get_contents(CREATE_TABLES_SQL))) {
		throw new Exception('Unable to open file: ' . CREATE_TABLES_SQL);
	}
	$db->exec($sql);

	foreach (TABLES as $table) {

		/*
		 * Opening and processing an xml file.
		 */

		$file = $files[$table['name']];
		$reader = new XMLReader();
		if (!$reader->open($file)) {
			throw new Exception('Unable to open xml file: ' . $file);
		}

		$result = TRUE;
		while ($result) {
			$result = [];
			$i = 0;
			while ($i < ROWS_PER_INSERT && $reader->read()) {
				if ($reader->name == $table['node']) {

					/*
					 * Filter.
					 */

					foreach ($table['filter'] as $rule) {
						$field = $reader->getAttribute($rule['field']);
						switch ($rule['type']) {
							case 'equal':
								$filter = $field == $rule['value'] ? TRUE : FALSE;
								break;
							case 'dateUnder':
								$filter = $field > date('Y-m-d', TIME - 3600 * 24 * $rule['days'])
									? TRUE : FALSE;
								break;
						}
						if (!$filter) {
							break;
						}
					}

					if ($filter) {
						$item = [];
						foreach ($table['fields'] as $v) {
							$attribute = $reader->getAttribute(mb_strtoupper($v));

							/*
							 * Plaincode condition.
							 */

							if ($v == 'plaincode' && $attribute) {
								$attribute = str_pad($attribute, MAX_PLAIN_CODE_LENGTH, '0');
							}

							$item[$v] = $attribute;
						}
						$result[] = $item;
						$i++;
					}
				}
			}

			/*
			 * Writing to the database.
			 */

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

		/*
		 * Deleting an xml filee.
		 */

		if (!unlink($file)) {
			throw new Exception('Unable to delete file: ' . $file);
		}
	}

	/*
	 * Remove extract dir.
	 */

	if (!rmdir(EXTRACT_DIR)) {
		throw new Exception('Unable to remove extract dir: ' . EXTRACT_DIR);
	}
	
	/*
	 * Modifying tables.
	 */

	if (!($sql = file_get_contents(MODIFY_TABLES_SQL))) {
		throw new Exception('Unable to open file: ' . MODIFY_TABLES_SQL);
	}
	$db->exec($sql);

} catch (Exception $e) {
	echo $e->getMessage();
}
