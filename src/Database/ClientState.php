<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
/**
 * Client State class for managing client_states table
 *
 * @category Database
 * @package  Helioviewer
 * @author   Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 * @license  http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License 1.1
 * @link     https://github.com/Helioviewer-Project/
 */

require_once HV_ROOT_DIR.'/../src/Database/DbConnection.php';

class ClientState extends Database_DbConnection 
{
    /**
     * This function creates or updates the state in database.
     *
     * @return string
     */
    public function upsert(array $state): string 
    {
        $state_json = json_encode($state); 
        $state_key = hash('sha256',$state_json);

        $create_sql = "REPLACE INTO client_states(id, state) VALUES ('%s','%s')";
        $create_state_sql = sprintf($create_sql, $state_key, $this->link->real_escape_string($state_json));

        // intentionally let exception thrown
        $result = $this->query($create_state_sql);

        return $state_key;
    }

    /**
     * This function finds the client state in db, with our given id
     * @param string, state_key is the id of the client state in database 
     * @return array?
     */
    public function find(string $state_key): ?array 
    {
        $find_sql = "SELECT * FROM client_states WHERE id = '%s' LIMIT 1";

        $find_state_sql = sprintf($find_sql, $this->link->real_escape_string($state_key));

        $query_result = $this->query($find_state_sql);

        $res = null;

        while ($row = $query_result->fetch_array(MYSQLI_ASSOC)) {
             $res = json_decode($row['state'], true);
        }

        $query_result->close();

        return $res;

    }

}
