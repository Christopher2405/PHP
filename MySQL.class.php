<?php
/*
*   MYSQL: A basic PHP class/template to interact with a MySQL/MariaDB database.
*
*   Author: Christopher Bryan Padilla-Vallejo
*   
*   You are completely free to use, copy, modify and distribute this code for any purposes (commercial or not)
*   as long as this header is left intact.
*/

// Disable error messages (for security reasons). Enable the line in production
//error_reporting(0);

class MySQL
{
    // Connection credentials
    private $host;
    private $port;
    private $database;
    private $user;
    private $password;
    // Useful variables for the use of this class
    private $connection;
    private $last_error_message;
    public $affected_rows; // Note that this is PUBLIC (for being used outside of this class)
    // Common errors and descriptions
    private $errors = array(
        'INVALID_CONNECTION' => 'Unable to connect with the database, verify your parameters.',
        'BAD_CONSTRUCTOR' => 'Wrong order or number of parameters.',
        'SET_ENCODING' => 'Cannot set character encoding.',
        'PREPARE_STATEMENT' => 'Error while trying to prepare statement.',
        'BAD_BINDING' => 'Bad parameter binding.',
        'EXECUTE' => 'Query was not executed.'
    );

    /*  ===# CONSTRUCTOR
    *   [ Returns ]
    *       + A new instance of the class
    */
    function __construct()
    {
        $this->last_error_message = '';
        // Simulating constructor polymorphism
        switch(func_num_args())
        {
            case 0: // Default credentials
                $this->host = "**********";
                $this->port = **********;
                $this->database = "**********";
                $this->user = "**********";
                $this->password = "**********";
            break;

            case 5: // Use custom credentials (follows the same order as they're declared)
                $args = func_get_args();
                $this->MySQL($args[0], $args[1], $args[2], $args[3], $args[4]);
            break;

            default: // Invalid number or order of credentials
                $this->last_error_message = $this->errors['BAD_CONSTRUCTOR'];
            break;
        }
    }

    /*  ===# SET CUSTOM CREDENTIALS TO CONNECT WITH THE DATABASE
    *   [ Parameters ]
    *       + IP of the server (String)
    *       + Port of the server (Int)
    *       + Name of the database (String)
    *       + User (String)
    *       + Password (String)
    */
    private function MySQL($_host, $_port, $_database, $_user, $_password)
    {
        $this->host = $_host;
        $this->port = $_port;
        $this->database = $_database;
        $this->user = $_user;
        $this->password = $_password;
    }

    /*  ===# GET THE DETAILS ABOUT THE LAST GENERATED ERROR
    *   [ Returns ]
    *       + Error description (String)
    */
    public function getErrorDescription()
    {
        return $this->last_error_message.PHP_EOL;
    }

    /*  ===# OPEN A NEW CONNECTION WITH THE DATABASE USING THE SPECIFIED CREDENTIALS
    *   [ Returns ]
    *       + Could the connection be stablished? (Boolean)
    */
    public function open()
    {
        // Check if there's problems with the constructor
        if(strcmp($this->last_error_message, '') != 0)
            return false;
        // Attempt to start the connection
        $this->connection = mysqli_connect($this->host, $this->user, $this->password, $this->database, $this->port);
        if(!$this->connection) {
            $this->connection = null;
            $this->last_error_message = $this->errors['INVALID_CONNECTION'].mysqli_connect_error();
            return false;
        }
        // Set character encoding
        if(mysqli_set_charset($this->connection, "utf8mb4") !== true) {
            mysqli_close($this->connection);
            $this->connection = null;
            $this->last_error_message = $this->errors['SET_ENCODING'];
            return false;
        }
        return true;
    }

    /*  ===# SANATIZES A TEXT VARIABLE FOR SAFE USE
    *   [ Parameters ]
    *       + Variable to be sanitized (String)
    *   [ Returns ]
    *       + Sanitized variable
    */
    public function clear($variable)
    {
        $variable = filter_var(addslashes(strip_tags(trim($variable))), FILTER_SANITIZE_STRING);
        return $variable;
    }

    /*  ===# PRIVATE FUNCTION TO BUILD THE "PARAMS-STRING" TO BE BINDED
    *   [ Parameters ]
    *       + Statement parameters (Array)
    *   [ Returns ]
    *       + String to be binded with the provided parameters (String)
    */
    private function getDataTypes($params)
    {
        $result = '';
        foreach($params as $i) {
            if(is_integer($i)) $result .= 'i';
            else if(is_double($i)) $result .= 'd';
            else if(is_string($i)) $result .= 's';
            else $result .= 'b';
        }
        return $result;
    }

    /*  ===# FACTORIZED CODE TO PREPARE STATEMENTS (PRIVATE FUNCTION FOR USE OF THIS CLASS ONLY)
    *   [ Parameters ]
    *       + SQL statement (String)
    *       + (Optional) Parameters of the SQL statement (Array)
    *   [ Returns ]
    *       + Prepared statement (Object)
    */
    private function prepareStatement($sql, &$params)
    {
        $prepared_statement = mysqli_prepare($this->connection, $sql);
        if(!$prepared_statement) {
            $this->last_error_message = $this->errors['PREPARE_STATEMENT'].mysqli_error($this->connection);
            return null;
        }
        if($params != null) {
            foreach($params as &$i)
                $i = $this->clear($i);
            $bind = $this->getDataTypes($params);
            if(!mysqli_stmt_bind_param($prepared_statement, $bind, ...$params)) {
                $this->last_error_message = $this->errors['BAD_BINDING'].mysqli_error($this->connection);
                return null;
            }
        }
        return $prepared_statement;
    }

    /*  ===# EXECUTE A QUERY WHICH DON'T RETURNS RECORDS
    *   [ Parameters ]
    *       + SQL statement (String)
    *       + (Optional) Parameters of the SQL statement (Array)
    *   [ Returns ]
    *       + Was the statement executed successfuly? (Boolean)
    *
    *   > This function also updates the "affected_rows" class attribute
    */
    public function execute($sql, $params=null)
    {
        $statement = $this->prepareStatement($sql, $params);
        if(!$statement)
            return false;
        $this->affected_rows = 0;
        if(!mysqli_stmt_execute($statement)) {
            $this->last_error_message = $this->errors['EXECUTE'].mysqli_error($this->connection);
            return false;
        }
        $this->affected_rows = mysqli_affected_rows($this->connection);
        mysqli_stmt_close($statement);
        return true;
    }

    /*  ===# PERFORM A QUERY WHICH RETURNS RECORDS
    *   [ Parameters ]
    *       + SQL statement (String)
    *       + (Optional) Parameters of the SQL statement (Array)
    *   [ Returns ]
    *       + Result set (Object)
    */
    public function query($sql, $params=null)
    {
        $statement = $this->prepareStatement($sql, $params);
        if(!$statement)
            return null;
        $result = null;
        if(!mysqli_stmt_execute($statement)) {
            $this->last_error_message = $this->errors['EXECUTE'].mysqli_error($this->connection);
            return null;
        }
        $result = mysqli_stmt_get_result($statement);
        mysqli_stmt_close($statement);
        return $result;
    }

    /*  ===# GET THE CONNECTION INSTANCE (TO USE IT OUTSIDE OF THIS CLASS)
    *   [ Returns ]
    *       + Connection (Object)
    */
    function getConnection()
    {
        return $this->connection;
    }

    /*  ===# CLOSE THE CONNECTION */
    public function close()
    {
        if($this->connection) {
            mysqli_close($this->connection);
            $this->connection = null;
        }
    }
    
    /*  ===# CLEAN IT ALL */
    function __destruct()
    {
        $this->close();
        foreach($this as &$property)
            $property = null;
    }
}
?>
