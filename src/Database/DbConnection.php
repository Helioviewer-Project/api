<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
/**
 * Database connection helper class
 *
 * @category Database
 * @package  Helioviewer
 * @author   Jeff Stys <jeff.stys@nasa.gov>
 * @author   Patrick Schmiedel <patrick.schmiedel@gmx.net>
 * @author   Keith Hughitt <keith.hughitt@nasa.gov>
 * @author   Serge Zahniy <serge.zahniy@nasa.gov>
 * @license  http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License 1.1
 * @link     https://github.com/Helioviewer-Project/
 */

class Database_DbConnection {

    private $_host     = HV_DB_HOST;
    private $_dbname   = HV_DB_NAME;
    private $_user     = HV_DB_USER;
    private $_password = HV_DB_PASS;
    public  $link;

    /**
     * Create a DbConnection instance
     *
     * @param string $dbname   [Optional] Database name
     * @param string $user     [Optional] Database user
     * @param string $password [Optional] Database password
     * @param string $host     [Optional] Database hostname
     *
     * @return void
     */
    public function __construct($dbname=null, $user=null, $password=null,
        $host=null) {

        if ($user) {
            $this->_user = $user;
        }
        if ($password) {
            $this->_password = $password;
        }
        if ($host) {
            $this->_host = $host;
        }
        if ($dbname) {
            $this->_dbname = $dbname;
        }
        $this->connect();
    }

    /**
     * Connect to database and sets timezone to UTC
     *
     * @return void
     */
    public function connect() {
        $this->link = mysqli_connect(
            $this->_host,
            $this->_user,
            $this->_password);

        if ( !$this->link ) {
            throw new Exception("Failed to connect to database. Please " +
            "verify the contents of the database configuration file.", 1);
        }
        mysqli_select_db($this->link, $this->_dbname);
        mysqli_query($this->link, "SET @@session.time_zone = '+00:00'");
    }

    /**
     * Get the id returned from an auto-increment INSERT query.
     *
     * @return int Insert id
     */
    public function getInsertId() {
        return $this->link->insert_id;
    }

    /**
     * Execute database query.
     *
     * @param string $query SQL query
     *
     * @return mixed Query result
     */
    public function query($query) {
        $result = mysqli_query($this->link, $query);

        if ($result === false) {
            throw new Exception(
                sprintf("Error executing database query (%s): %s",
                        $query,
                        mysqli_error($this->link)), 2);
        }

        return $result;
    }

    /**
     * Set the encoding to use for the response
     *
     * @param string $encoding Encoding to use
     *
     * @return void
     */
    public function setEncoding($encoding) {
        mysqli_set_charset($this->link, $encoding);
    }

    /**
     * Deconstructor should be executed when this class instance is not referenced
     *
     * @return void
     */
    public function __destruct() {
        if ($this->link) {
            mysqli_close($this->link);
        }
    }

}
?>
