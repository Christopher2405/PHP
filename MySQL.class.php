<?php
// Una vez en producción, habilitar la siguiente línea
//error_reporting(0);

class MySQL
{
    // Credenciales para realizar la conexión
    private $host;
    private $port;
    private $database;
    private $user;
    private $password;
    private $connection;
    // Mensajes de error
    private $last_error_message;
	private static $error_messages = array(
        'BAD_CONSTRUCTOR' => 'Número de parámetros de conexión incorrecto. Se esperaban 5 pero se han dado ',
		'BAD_ARGUMENT' => 'Argumento de conexión no válido o fuera de orden: ',
		'NOT_CONNECTED' => 'No se pudo conectar con la base de datos',
        'PREPARE_STATEMENT' => 'No se pudo preparar la consulta con la sentencia dada',
        'BAD_BINDING' => 'Los parámetros dados no coinciden con sus tokens',
        'EXECUTE' => 'Hubo un error al intentar ejecutar la consulta',
		'QUERY_DATA' => 'La información proporcionada debe ser un array asociativo'
    );

    /*--------------------------------------------------------------------------------------------------
	*	CONSTRUCTOR DE LA CLASE
	*---------------------------------------------------------------------------------------------------*/
    function __construct()
    {
		$this->connection = null;
		$this->last_error_message = '';
        // Simulando polimorfismo del constructor
        switch(func_num_args())
        {
			// Credenciales por defecto
            case 0:
                $this->host = '127.0.0.1';
                $this->port = 3306;
                $this->database = 'mysql';
                $this->user = 'root';
                $this->password = '';
            break;
			
			// Usar credenciales alternativas (siguen el mismo orden en que se declararon en la clase)
            case 5:
                $args = func_get_args();
                $this->MySQL($args[0], $args[1], $args[2], $args[3], $args[4]);
            break;
			
			// Número incorrecto de parámetros
            default:
                $this->last_error_message = self::$error_messages['BAD_CONSTRUCTOR'].func_num_args();
            break;
        }
    }

    /*--------------------------------------------------------------------------------------------------
	*	ESTABLECER CREDENCIALES DE CONEXIÓN PERSONALIZADAS
	*---------------------------------------------------------------------------------------------------*/
    private function MySQL($_host, $_port, $_database, $_user, $_password)
    {
		// Validaciones de cada uno de los parámetros, se dejan las llaves por si se agregan más instrucciones
		if(filter_var($_host, FILTER_VALIDATE_IP)) {
			$this->host = $_host;
		} else {
			$this->last_error_message = self::$error_messages['BAD_ARGUMENT'].'Host';
		}
        if(is_integer($_port)) {
			$this->port = $_port;
		} else {
			$this->last_error_message = self::$error_messages['BAD_ARGUMENT'].'Port';
		}
		if(strlen(trim($_database))>0 && strpos($_database, ' ')===false) {
			$this->database = $_database;
		} else {
			$this->last_error_message = self::$error_messages['BAD_ARGUMENT'].'Database';
		}
		if(strlen(trim($_user))>0 && strpos($_user, ' ')===false) {
			$this->user = $_user;
		} else {
			$this->last_error_message = self::$error_messages['BAD_ARGUMENT'].'User';
		}
        $this->password = $_password;
    }

    /*--------------------------------------------------------------------------------------------------
	*	OBTENER EL ÚLTIMO ERROR GENERADO
	*---------------------------------------------------------------------------------------------------*/
    public function errorMessage()
    {
        return $this->last_error_message.'.'.PHP_EOL;
    }

    /*--------------------------------------------------------------------------------------------------
	*	ABRIR UNA CONEXIÓN USANDO LAS CREDENCIALES PROPORCIONADAS
	*---------------------------------------------------------------------------------------------------*/
    public function open()
    {
        // Validamos que no haya problemas en el constructor
        if(strcmp($this->last_error_message, '') != 0)
            return false;
        // Intento de conexión
        $this->connection = mysqli_connect($this->host, $this->user, $this->password, $this->database, $this->port);
        if(mysqli_connect_errno()) {
            $this->connection = null;
            $this->last_error_message = self::$error_messages['NOT_CONNECTED'].mysqli_connect_error();
            return false;
        }
        // Establecer la codificación UTF-8
        @mysqli_set_charset($this->connection, "utf8mb4");
        return true;
    }

    /*--------------------------------------------------------------------------------------------------
	*	LIMPIA UNA VARIABLE PARA USARLA DE MANERA SEGURA (SE APLICA AUTOMÁTICAMENTE)
	*---------------------------------------------------------------------------------------------------*/
    public function clear($variable)
    {
        return htmlentities(strip_tags(trim($variable)), ENT_QUOTES|ENT_HTML5|ENT_SUBSTITUTE, 'UTF-8');
    }

    /*--------------------------------------------------------------------------------------------------
	*	CONSTRUIR LA "CADENA DE TIPOS DE DATO" PARA CONSULTAS PARAMETRIZADAS
	*---------------------------------------------------------------------------------------------------*/
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

    /*--------------------------------------------------------------------------------------------------
	*	PREPARAR UNA CONSULTA PARAMETRIZADA
	*---------------------------------------------------------------------------------------------------*/
    private function prepareStatement($sql, &$params)
    {
        $prepared_statement = mysqli_prepare($this->connection, $sql);
        if(!$prepared_statement) {
            $this->last_error_message = self::$error_messages['PREPARE_STATEMENT'].mysqli_error($this->connection);
            return null;
        }
        if($params != null) {
            foreach($params as &$i)
                $i = $this->clear($i);
            $bind = $this->getDataTypes($params);
            if(!mysqli_stmt_bind_param($prepared_statement, $bind, ...$params)) {
                $this->last_error_message = self::$error_messages['BAD_BINDING'].mysqli_error($this->connection);
                return null;
            }
        }
        return $prepared_statement;
    }

    /*--------------------------------------------------------------------------------------------------
	*	CONSULTA PARAMETRIZADA QUE DEVUELVE EL NÚMERO DE REGISTROS AFECTADOS
	*---------------------------------------------------------------------------------------------------*/
    public function execute($sql, $params=null)
    {
        $statement = $this->prepareStatement($sql, $params);
        if(!$statement)
            return -1;
        if(!mysqli_stmt_execute($statement)) {
            $this->last_error_message = self::$error_messages['EXECUTE'].mysqli_error($this->connection);
            return -1;
        }
        mysqli_stmt_close($statement);
        return mysqli_affected_rows($this->connection);
    }

    /*--------------------------------------------------------------------------------------------------
	*	CONSULTA PARAMETRIZADA QUE DEVUELVE UN DATASET
	*---------------------------------------------------------------------------------------------------*/
    public function query($sql, $params=null)
    {
        $statement = $this->prepareStatement($sql, $params);
        if(!$statement)
            return null;
        $result = null;
        if(!mysqli_stmt_execute($statement)) {
            $this->last_error_message = self::$error_messages['EXECUTE'].mysqli_error($this->connection);
            return null;
        }
        $result = mysqli_stmt_get_result($statement);
        mysqli_stmt_close($statement);
        return $result;
    }

    /*--------------------------------------------------------------------------------------------------
	*	OBTENER EL CANAL DE CONEXIÓN (PARA USARLO FUERA DE LA CLASE)
	*---------------------------------------------------------------------------------------------------*/
    public function getConnection()
    {
        return $this->connection;
    }
	
    /*--------------------------------------------------------------------------------------------------
	*	CERRAR LA CONEXIÓN
	*---------------------------------------------------------------------------------------------------*/
    public function close()
    {
        if($this->connection) {
            mysqli_close($this->connection);
            $this->connection = null;
        }
    }
    
    /*--------------------------------------------------------------------------------------------------
	*	DESTRUCTOR
	*---------------------------------------------------------------------------------------------------*/
    function __destruct()
    {
        $this->close();
        foreach($this as &$property)
            $property = null;
    }
}
?>
