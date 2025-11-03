<?php
// Prevent direct access
if (!defined('APP_ACCESS')) {
    die('Direct access not permitted');
}

class Database {
    private static $instance = null;
    private $connection;
    private $stmt;
    
    // Private constructor to prevent direct instantiation
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => false
            ];
            
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
            
        } catch (PDOException $e) {
            // Log error (in production, don't show actual error to user)
            error_log("Database Connection Error: " . $e->getMessage());
            die("Database connection failed. Please contact system administrator.");
        }
    }
    
    // Singleton pattern - ensures only one database connection
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    // Get the PDO connection object
    public function getConnection() {
        return $this->connection;
    }
    
    // Prepare SQL statement
    public function query($sql) {
        $this->stmt = $this->connection->prepare($sql);
        return $this;
    }
    
    // Bind parameters to prepared statement
    public function bind($param, $value, $type = null) {
        if (is_null($type)) {
            switch (true) {
                case is_int($value):
                    $type = PDO::PARAM_INT;
                    break;
                case is_bool($value):
                    $type = PDO::PARAM_BOOL;
                    break;
                case is_null($value):
                    $type = PDO::PARAM_NULL;
                    break;
                default:
                    $type = PDO::PARAM_STR;
            }
        }
        $this->stmt->bindValue($param, $value, $type);
        return $this;
    }
    
    // Execute the prepared statement
    public function execute() {
        return $this->stmt->execute();
    }
    
    // Fetch all results as associative array
    public function fetchAll() {
        $this->execute();
        return $this->stmt->fetchAll();
    }
    
    // Fetch single record
    public function fetch() {
        $this->execute();
        return $this->stmt->fetch();
    }
    
    // Get row count
    public function rowCount() {
        return $this->stmt->rowCount();
    }
    
    // Get last inserted ID
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
    
    // Begin transaction
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    // Commit transaction
    public function commit() {
        return $this->connection->commit();
    }
    
    // Rollback transaction
    public function rollback() {
        return $this->connection->rollBack();
    }
    
    // Prevent cloning of instance
    private function __clone() {}
    
    // Prevent unserializing of instance
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

// ====================================================
// Simple helper function to get database instance
// ====================================================
function getDB() {
    return Database::getInstance();
}

