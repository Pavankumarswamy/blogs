<?php
/**
 * PostgreSQL Database Connection
 * This file handles connection to the PostgreSQL database
 */

function getDbConnection() {
    try {
        // Get environment variables for database connection
        $host = getenv('PGHOST');
        $port = getenv('PGPORT');
        $dbname = getenv('PGDATABASE');
        $user = getenv('PGUSER');
        $password = getenv('PGPASSWORD');
        
        // Create DSN string
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password";
        
        // Create PDO instance
        $pdo = new PDO($dsn);
        
        // Set error mode to exception
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        return $pdo;
    } catch (PDOException $e) {
        error_log('Database connection error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Execute a query with parameters and return the result
 * 
 * @param string $sql SQL query
 * @param array $params Parameters for prepared statement
 * @param bool $fetchAll Whether to fetch all results or just one
 * @return mixed Query result or false on failure
 */
function executeQuery($sql, $params = [], $fetchAll = true) {
    try {
        $pdo = getDbConnection();
        if (!$pdo) {
            return false;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        if ($fetchAll) {
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log('Query execution error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Execute a query without returning results (INSERT, UPDATE, DELETE)
 * 
 * @param string $sql SQL query
 * @param array $params Parameters for prepared statement
 * @return bool Success or failure
 */
function executeNonQuery($sql, $params = []) {
    try {
        $pdo = getDbConnection();
        if (!$pdo) {
            return false;
        }
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    } catch (PDOException $e) {
        error_log('Query execution error: ' . $e->getMessage());
        return false;
    }
}