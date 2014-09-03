<?php

	//backup.php]pB
	//NucleusV[XAXt@CAiEXAbackup.phpnj
	
class DBBACKUP
{
	//
	//
	//
	function dumpBackup($tables)
	{
		reset($tables);
		array_walk($tables, array(&$this, '_backup_dump_table')); //Nucleus v3.3`
		return $this->contents;
	}
	
	
	/**
	  * Creates a dump for a single table
	  * ($tablename and $key are filled in by array_walk)
	  */
	function _backup_dump_table($tablename, $key) {

		$e = "#\n";
		$e.= "# TABLE: " . $tablename . "\n";
		$e.= "#\n";
			$this->contents .= $e;
		// dump table structure
		$this->_backup_dump_structure($tablename);

		// dump table contents
		$this->_backup_dump_contents($tablename);
	}

	function _backup_dump_structure($tablename) {

		// add command to drop table on restore
		$e = "DROP TABLE IF EXISTS $tablename;\n";
		$e.= "CREATE TABLE $tablename(\n";

		//
		// Ok lets grab the fields...
		//
		$result = mysql_query("SHOW FIELDS FROM $tablename");
		$row = mysql_fetch_array($result);
		while ($row) {

			//<satona>
			//echo '	' . $row['Field'] . ' ' . $row['Type'];
			$e.= '	`' . $row['Field'] . '` ' . $row['Type'];
			//</satona>

			if(isset($row['Default']))
				$e.= ' DEFAULT \'' . $row['Default'] . '\'';

			if($row['Null'] != "YES")
				$e.= ' NOT NULL';

			if($row['Extra'] != "")
				$e.= ' ' . $row['Extra'];

			$row = mysql_fetch_array($result);

			// add comma's except for last one
			if ($row)
				$e.= ",\n";
		}

		//
		// Get any Indexed fields from the database...
		//
		$result = mysql_query("SHOW KEYS FROM $tablename");
		while($row = mysql_fetch_array($result)) {
			$kname = $row['Key_name'];

			if(($kname != 'PRIMARY') && ($row['Non_unique'] == 0))
				$kname = "UNIQUE|$kname";
			if(($kname != 'PRIMARY') && ($row['Index_type'] == 'FULLTEXT'))
				$kname = "FULLTEXT|$kname";

			if(!is_array($index[$kname]))
				$index[$kname] = array();

			$index[$kname][] = '`'.$row['Column_name'].'`' . ( ($row['Sub_part']) ? ' (' . $row['Sub_part'] . ')' : '');
		}

		while(list($x, $columns) = @each($index)) {
			$e.= ", \n";

			//<satona>
			/*
			if($x == 'PRIMARY')
				echo '	PRIMARY KEY (' . implode($columns, ', ') . ')';
			elseif (substr($x,0,6) == 'UNIQUE')
				echo '	UNIQUE KEY ' . substr($x,7) . ' (' . implode($columns, ', ') . ')';
			elseif (substr($x,0,8) == 'FULLTEXT')
				echo '	FULLTEXT KEY ' . substr($x,9) . ' (' . implode($columns, ', ') . ')';
			elseif (($x == 'ibody') || ($x == 'cbody'))			// karma 2004-05-30 quick and dirty fix. fulltext keys were not in SQL correctly.
				echo '	FULLTEXT KEY ' . substr($x,9) . ' (' . implode($columns, ', ') . ')';
			else
				echo "	KEY $x (" . implode($columns, ', ') . ')';
			*/
			if     ($x == 'PRIMARY')                    $e.= '	PRIMARY KEY '    .                  '(' . implode(', ', $columns) . ')';
			elseif (substr($x,0,6) == 'UNIQUE')         $e.= '	UNIQUE KEY `'    . substr($x,7) . '` (' . implode(', ', $columns) . ')';
			elseif (substr($x,0,8) == 'FULLTEXT')       $e.= '	FULLTEXT KEY `'  . substr($x,9) . '` (' . implode(', ', $columns) . ')';
			elseif (($x == 'ibody') || ($x == 'cbody')) $e.= '	FULLTEXT KEY `'  . substr($x,9) . '` (' . implode(', ', $columns) . ')'; // karma 2004-05-30 quick and dirty fix. fulltext keys were not in SQL correctly.
			else                                        $e.= '	KEY `'           .        $x    . '` (' . implode(', ', $columns) . ')';
			//</satona>
		}

		$e.= "\n);\n\n";
			$this->contents .= $e;
	}

	/**
	 * Returns the field named for the given table in the 
	 * following format:
	 *
	 * (column1, column2, ..., columnn)
	 */
	function _backup_get_field_names($result, $num_fields) {

		if (function_exists('mysqli_fetch_fields') ) {
			
			$fields = mysqli_fetch_fields($result);
			for ($j = 0; $j < $num_fields; $j++)
				//<satona>
				//$fields[$j] = $fields[$j]->name;
				$fields[$j] = '`' . $fields[$j]->name . '`';
				//</satona>

		} else {

			$fields = array();
			for ($j = 0; $j < $num_fields; $j++) {
				//<satona>
				//$fields[] = mysql_field_name($result, $j);
				$fields[] = '`' . mysql_field_name($result, $j) . '`';
				//</satona>
			}

		}
		
		return '(' . implode(', ', $fields) . ')';	
	}

	function _backup_dump_contents($tablename) {
			$e = '';
		//
		// Grab the data from the table.
		//
		$result = mysql_query("SELECT * FROM $tablename");

		if(mysql_num_rows($result) > 0)
			$e.= "\n#\n# Table Data for $tablename\n#\n";
			
		$num_fields = mysql_num_fields($result);
		
		//
		// Compose fieldname list
		//
		$tablename_list = $this->_backup_get_field_names($result, $num_fields);
			
		//
		// Loop through the resulting rows and build the sql statement.
		//
		while ($row = mysql_fetch_array($result))
		{
			// Start building the SQL statement.

			$e.= "INSERT INTO $tablename $tablename_list VALUES(";

			// Loop through the rows and fill in data for each column
			for ($j = 0; $j < $num_fields; $j++) {
				if(!isset($row[$j])) {
					// no data for column
					$e.= ' NULL';
				} elseif ($row[$j] != '') {
					// data
					//<satona>
					//echo " '" . addslashes($row[$j]) . "'";
					$search  = array("\x00", "\x0a", "\x0d", "\x1a");
					$replace = array('\0',   '\n',   '\r',   '\Z');
					$e.= " '" . str_replace($search, $replace, addslashes($row[$j])) . "'";
					//</satona>
				} else {
					// empty column (!= no data!)
					$e.= "''";
				}

				// only add comma when not last column
				if ($j != ($num_fields - 1))
					$e.= ",";
			}

			$e.= ");\n";

		}


		$e.= "\n";
			$this->contents .= $e;

	}

}
?>
