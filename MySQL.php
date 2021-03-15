<?php
/**
 * MySQL: Una clase para ejecutar consultas SQL de una manera fácil y segura.
 * 
 * Dado que su única funcionalidad es ejecutar consultas, es una excelente herramienta
 * para proyectos pequeños, aunque también es un módulo clave para facilitar la implementación
 * de los componentes en un modelo a capas (MVC por ejemplo).
 * 
 * @author Christopher Bryan Padilla-Vallejo <christopher240596@gmail.com>
 * @copyright GNU GPLv3 [http://www.gnu.org/licenses/gpl-3.0.html]
 * @version 2021.03.15.1
 */

// Especificar un espacio de nombres:
//namespace Models\Tools\Databases;

// Una vez en producción, habilitar la siguiente línea:
//error_reporting(0);

class MySQL
{
    private $host;
    private $port;
    private $database;
    private $user;
    private $password;
    
    private $connection;

    private $last_error_message;
    private static $error_messages = array(
        'BAD_ARGUMENT' => 'Argumento de conexión no válido o fuera de orden: ',
        'NOT_CONNECTED' => 'No se pudo conectar con la base de datos',
        'PREPARE_STATEMENT' => 'No se pudo preparar la consulta con la instrucción dada',
        'BAD_BINDING' => 'Los parámetros dados no coinciden con sus marcadores',
        'EXECUTE' => 'Fallo al intentar ejecutar la consulta'
    );
    
    /**
     * Method __construct
     *
     * Establece las credenciales de conexión con la base de datos.
     * 
     * @param string $_host Dirección IP del servidor.
     * @param int $_port Puerto de acceso.
     * @param string $_database Nombre de la base de datos.
     * @param string $_user Nombre de usuario para autenticarse.
     * @param string $_password Contraseña del usuario.
     *
     * @return void
     */
    function __construct($_host='127.0.0.1', $_port=3306, $_database='mysql', $_user='root', $_password='')
    {
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
        $this->last_error_message = '';
        $this->connection = null;
    }

        
    /**
     * Method errorMessage
     *
     * Obtener el último mensaje de error generado.
     * 
     * @return string Descripción del error.
     */
    public function errorMessage()
    {
        return $this->last_error_message.'.'.PHP_EOL;
    }

    /**
     * Method open
     *
     * Abrir una nueva conexión con la base de datos.
     * 
     * @return boolean Indica si el intento de conexión tuvo éxito.
     */
    public function open()
    {
        if(strcmp($this->last_error_message, '') != 0)
            return false;
        $this->connection = mysqli_connect($this->host, $this->user, $this->password, $this->database, $this->port);
        if(mysqli_connect_errno()) {
            $this->connection = null;
            $this->last_error_message = self::$error_messages['NOT_CONNECTED'].mysqli_connect_error();
            return false;
        }
        @mysqli_set_charset($this->connection, "utf8mb4");
        return true;
    }
    
    /**
     * Method clear
     *
     * Limpia la variable especificada para su uso seguro.
     * 
     * Vale la pena mencionar que al parametrizar las consultas, ésta función es aplicada
     * a cada parámetro de manera automática. Además, se debe tener en consideración que
     * por el hecho de usar htmlentities(), los caracteres especiales van a ser codificados.
     * 
     * @param mixed $variable Variable a ser sanitizada.
     *
     * @return mixed Variable sanitizada.
     */
    public function clear($variable)
    {
        return htmlentities(strip_tags(trim($variable)), ENT_QUOTES|ENT_HTML5|ENT_SUBSTITUTE, 'UTF-8');
    }
    
    /**
     * Method getDataTypes
     *
     * Constuir la "cadena de tipos de dato" para hacer la vinculación con los parámetros.
     * 
     * @param array $params Arreglo con los parámetros de la consulta.
     *
     * @return string Cadena de tipos de dato.
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
    
    /**
     * Method prepareStatement
     *
     * Código factorizado para hacer la preparación de consulta.
     * 
     * @param string $sql Instrucción SQL parametrizada a ser ejecutada.
     * @param array $params Arreglo de parámetros a ser vinculados con los marcadores.
     *
     * @return mysqli_stmt Consulta preparada lista para ser ejecutada (NULL en caso de error).
     */
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

        
    /**
     * Method execute
     *
     * Ejecutar una consulta parametrizada que no genera un dataset.
     * 
     * @param string $sql Instrucción SQL parametrizada a ser ejecutada.
     * @param array $params Arreglo de parámetros a ser vinculados con los marcadores.
     *
     * @return int Número de registros afectados por la instrucción (-1 en caso de error).
     */
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
    
    /**
     * Method query
     *
     * Ejecutar una consulta parametrizada que genera un dataset.
     * 
     * @param string $sql Instrucción SQL parametrizada a ser ejecutada.
     * @param array $params Arreglo de parámetros a ser vinculados con los marcadores.
     *
     * @return mysqli_result Dataset generado por la instrucción SQL (NULL en caso de error).
     */
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
    
    /**
     * Method getConnection
     *
     * Obtener el canal de conexión abierto (para usarlo fuera de la clase).
     * 
     * @return object Enlace de conexión mysqli
     */
    public function getConnection()
    {
        return $this->connection;
    }
	    
    /**
     * Method close
     *
     * Cerrar la conexión con la base de datos.
     * 
     * @return void
     */
    public function close()
    {
        if($this->connection) {
            mysqli_close($this->connection);
            $this->connection = null;
        }
    }
            
    /**
     * Method __destruct
     *
     * Destructor de la clase y encargado de cerrar la conexión en caso de olvidarlo.
     * 
     * @return void
     */
    function __destruct()
    {
        $this->close();
        foreach($this as &$property)
            $property = null;
    }
}
?>