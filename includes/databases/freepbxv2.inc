<?PHP
class endpointmanager_database {
	function query_all($query) {
		global $db;
		$out = $db->getAll($query, DB_FETCHMODE_ASSOC);
		return($out);
	}
	function query_row($query) {
		global $db;
		$out = $db->getAll($query, DB_FETCHMODE_ASSOC);
		return($out[0]);
	}
}
?>