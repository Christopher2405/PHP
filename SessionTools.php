<?php
define('DEFAULT_SESSION_NAME', 'PHPSESSID');
/**
 * SessionTools: Funciones comúnes para manejo seguro de sesiones.
 * 
 * @author Christopher Bryan Padilla-Vallejo <christopher240596@gmail.com>
 * @copyright GNU GPLv3 <http://www.gnu.org/licenses/gpl-3.0.html>
 * @version 2021.03.17.1
 */
class SessionTools
{
    private $is_active;
    private $referer_cookie;
        
    /**
     * Method iniSetRecommendedSettings
     *
     * Aplica las configuraciones de seguridad recomendadas.
     * 
     * @return void
     * @link https://www.php.net/manual/es/session.security.php
     */
    private function iniSetRecommendedSettings()
    {
        $prefix = 'session.';
        $settings = array(
            'cookie_lifetime' => '0',
            'use_cookies' => 'on',
            'use_only_cookies' => 'on',
            'use_strict_mode' => 'on',
            'cookie_httponly' => 'on',
            'use_trans_sid' => 'off',
            'cache_limiter' => 'nocache'
        );
        foreach($settings as $key => $value) {
            if(false === ini_set($prefix.$key, $value))
                die('Error al configurar '.strtoupper($key));
        }
    }
    
    /**
     * Method __construct
     *
     * @param string $session_name Nombre que se le va a asignar a la sesión.
     * @return void
     */
    function __construct($session_name=DEFAULT_SESSION_NAME)
    {
        $this->is_active = false;
        $this->referer_cookie = 'REFERER_INFO';
        $this->iniSetRecommendedSettings();
        session_name($session_name);
        $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on';
        session_set_cookie_params(0, '/', $_SERVER['HTTP_HOST'], $https, true);
    }

    /**
     * Method start
     * 
     * Inciar la sesión y generar un token aleatorio.
     * 
     * @return void
     */
    public function start()
    {
        $this->is_active = session_start();
        if(!isset($_SESSION['ST_request_token'])) {
            $_SESSION['ST_request_token'] = $this->getNewToken();
        }
    }
 
    /**
     * Method isValid
     *
     * Aquí se implementan las validaciones PERSONALIZADAS para validar la sesión.
     * 
     * @return boolean Indica si la sesión está iniciada y es válida.
     */
    public function isValid()
    {
        if($this->is_active) {
            if(isset($_SESSION['ST_request_token']) && !empty($_SESSION['ST_request_token']))
                return true;
        }
        return false;
    }
    
    /**
     * Method getFingerprint
     *
     * Obtener la "huella" del cliente: sha256(IP+UserAgent+Port).
     * 
     * @return string Regresa la huella del usuario como un hash de 64 caracteres.
     */
    public function getFingerprint()
    {
        return hash('sha256', $_SERVER['REMOTE_ADDR'].$_SERVER['HTTP_USER_AGENT'].$_SERVER['REMOTE_PORT']);
    }
    
    /**
     * Method getNewToken
     *
     * Crear un nuevo token para solicitudes.
     * 
     * @return string Un token de 96 caracteres de largo.
     */
    public function getNewToken()
    {
        $token = bin2hex(random_bytes(16)).hash('sha256', microtime(true).$this->getFingerprint());
        for($i=1; $i<96; $i++) {
            $token[$i] = (rand(0,100)<50) ? strtoupper($token[$i]) : $token[$i];
            $token[$i-1] = (rand(0,100)<5) ? '_' : $token[$i-1];
        }
        return $token;
    }

    /**
     * Method printTokenField
     *
     * Imprimir el código HTML para el campo de token de solicitud.
     * 
     * @return void
     */
    public function printTokenField()
    {
        echo '<input type="hidden" name="request_token" value="'.$_SESSION['ST_request_token'].'" required />';
    }
    
    /**
     * Method validateToken
     *
     * Validar que el token del formulario enviado coincida con el asignado a la sesión.
     * 
     * @param string $user_submission Token de solicitud enviado por el cliente.
     * @return boolean Indica si la comprobación de tokens es válida.
     */
    public function validateToken($user_submission)
    {
        return 0===strcmp($user_submission, $_SESSION['ST_request_token']);
    }
        
    /**
     * Method markAsLegitimateUser
     *
     * Almacenar una cookie que indique que la visita es legítima.
     * 
     * En la medida de lo posible, queremos evitar que se hagan peticiones
     * a nuestros scripts desde fuera al sitio. Dado que $_SERVER[HTTP_REFERER]
     * puede ser facilmente modificado, la alternativa más viable es colocar una cookie
     * en algún punto estratégico para validar que las peticiones no son automatizadas.
     * 
     * @return boolean Indica si la cookie pudo ser creada con éxito.
     */
    public function markAsLegitimateUser()
    {
        $value = base64_encode(json_encode(array(
            'IsAvailable' => array_key_exists('HTTP_REFERER', $_SERVER),
            'Content' => empty($_SERVER['HTTP_REFERER']) ? '' : $_SERVER['HTTP_REFERER']
        ))); 
        $expire = 0;
        $path = "/";
        $domain = $_SERVER['HTTP_HOST'];
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on';
        $httopnly = true;
        return setcookie($this->referer_cookie, $value, $expire, $path, $domain, $secure, $httopnly);
    }
    
    /**
     * Method isLegitimateRequest
     *
     * Validar que la petición al script en cuestión es de un usuario legítimo.
     * 
     * @return boolean Indica si la petición no fue automatizada.
     */
    public function isLegitimateRequest()
    {
        if(!isset($_COOKIE[$this->referer_cookie]) || empty($_COOKIE[$this->referer_cookie]))
            return false;
        $data = json_decode(base64_decode($_COOKIE[$this->referer_cookie]), true);
        if(!isset($data['IsAvailable'], $data['Content']))
            return false;
        // Aunque es verdad que sobre HTTPS algunos navegadores no envian el REFERER,
        // de cualquier modo debería desconfiarse de ese cliente.
        if(false === $data['IsAvailable']) {
            // Implementar aquí mecanismos de seguimiento preventivo
            return true;
        }
        $expected = strtolower($_SERVER['HTTP_HOST']);
        $given = strtolower(parse_url($data['Content'], PHP_URL_HOST));
        return 0 === strcmp($expected, $given);
    }

    /**
     * Method destroy
     *
     * Destruir la sesión.
     * 
     * @return void
     */
    public function destroy()
    {
        if($this->is_active) {
            $_SESSION = array();
            if(ini_get("session.use_cookies")) {
                $one_year_ago = $_SERVER['REQUEST_TIME'] - 31536000;
                $params = session_get_cookie_params();
                setcookie(session_name(), '', $one_year_ago,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
                setcookie($this->referer_cookie, '', $one_year_ago);
            }
            session_destroy();
            $this->is_active = false;
        }
    }
}
?>