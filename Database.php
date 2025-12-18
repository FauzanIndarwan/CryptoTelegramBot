<?php
/**
 * Database Connection Class with Singleton Pattern
 * 
 * Provides a single database connection instance throughout the application
 * lifecycle to improve performance and resource management.
 */

class Database {
    private static $instance = null;
    private $connection = null;

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        require_once __DIR__ . '/config.php';
        $this->connect();
    }

    /**
     * Get the singleton instance
     * 
     * @return Database
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Establish database connection
     */
    private function connect() {
        try {
            $this->connection = new mysqli(
                DB_HOST,
                DB_USER,
                DB_PASS,
                DB_NAME
            );

            if ($this->connection->connect_error) {
                throw new Exception("Connection failed: " . $this->connection->connect_error);
            }

            $this->connection->set_charset('utf8mb4');
            
        } catch (Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get the database connection
     * 
     * @return mysqli
     */
    public function getConnection() {
        // Check if connection is still alive
        if (!$this->connection || !$this->connection->ping()) {
            $this->connect();
        }
        return $this->connection;
    }

    /**
     * Execute a prepared statement
     * 
     * @param string $query SQL query with placeholders
     * @param array $params Parameters to bind
     * @param string $types Parameter types (e.g., 'ssi' for string, string, int)
     * @return mysqli_stmt|false
     */
    public function prepare($query, $params = [], $types = '') {
        $conn = $this->getConnection();
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            return false;
        }

        if (!empty($params) && !empty($types)) {
            $stmt->bind_param($types, ...$params);
        }

        return $stmt;
    }

    /**
     * Execute a query and return the result
     * 
     * @param string $query SQL query
     * @return mysqli_result|bool
     */
    public function query($query) {
        $conn = $this->getConnection();
        $result = $conn->query($query);
        
        if (!$result) {
            error_log("Query failed: " . $conn->error);
        }

        return $result;
    }

    /**
     * Escape a string for safe SQL usage
     * 
     * @param string $string String to escape
     * @return string
     */
    public function escape($string) {
        return $this->getConnection()->real_escape_string($string);
    }

    /**
     * Get the last insert ID
     * 
     * @return int
     */
    public function getLastInsertId() {
        return $this->getConnection()->insert_id;
    }

    /**
     * Begin a transaction
     */
    public function beginTransaction() {
        $this->getConnection()->begin_transaction();
    }

    /**
     * Commit a transaction
     */
    public function commit() {
        $this->getConnection()->commit();
    }

    /**
     * Rollback a transaction
     */
    public function rollback() {
        $this->getConnection()->rollback();
    }

    /**
     * Prevent cloning of the instance
     */
    private function __clone() {}

    /**
     * Prevent unserialization of the instance
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }

    /**
     * Close the database connection
     */
    public function close() {
        if ($this->connection) {
            $this->connection->close();
            $this->connection = null;
        }
    }

    /**
     * Destructor
     */
    public function __destruct() {
        $this->close();
    }
}
