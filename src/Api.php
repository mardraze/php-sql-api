<?php

/**
 * Features:
 * 1. Test connection and encrypt credentials as JWT Token - action "login"
 * 
 * PHP Version 7.4
 *
 * @category Sqladmin
 * @package  Mardraze\SqlApi
 * @author   Marcin Drążek <marcindr1@gmail.com>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     https://github.com/mardraze/php-sql-api
 */

namespace Mardraze\SqlApi;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Api
{

    protected $secret;
    protected $http = false;
    protected $expireTime = 1440;
    protected $error;
    protected $cfg;
    protected $currentServer;
    
    /**
     * Constructor
     * 
     * @param array $cfg Optional parameters, key description
     *                       - secureToken
     *                       - http
     */
    public function __construct($cfg = [])
    {
        $this->http = isset($cfg['http']) && $cfg['http'];
        $this->secret = isset($cfg['blowfish_secret']) ? $cfg['blowfish_secret'] : '';
        $this->cfg = $cfg;
    }

    /**
     * Process http input from clients
     * 
     * @param array $input Input to process optional  Parse http input if not set.
     * @return mixed
     */
    public function processInput($input = null)
    {
        if (null === $input) {
            $content = file_get_contents('php://input');

            if($content){
                $input = json_decode($content, true);
            }
            
            if (empty($input)) {
                $input = $_POST;
            }
            
            if (empty($input)) {
                $input = $_GET;
            }
            
            if (empty($input)) {
                return $this->fail('no-input', 'Please POST json');
            }
            
        }

        if ($input) {
            if(!isset($input['server'])){
                $input['server'] = 1;
            }
            
            if(isset($this->cfg['Servers'][$input['server']])){
                $this->currentServer = $this->cfg['Servers'][$input['server']];
                if (isset($input['action'])) {
                    if ($input['action'] === 'login') {
                        return $this->login($input);
                    } else {
                        if ($this->isValidMd5($input)) {
                            if (isset($input['token'])) {
                                try {
                                    switch ($input['action']) {
                                        case "query":
                                            return $this->query($input);
                                        case "query-xml":
                                            return $this->queryXml($input);
                                        default:
                                            return $this->fail('unknown-action', 'Please set valid action parameter');
                                    }
                                } catch (\Exception $ex) {
                                    if ($this->error) {
                                        return $this->fail($this->error['code'], $this->error['message']);
                                    } else {
                                        return $this->fail('exception', $ex->getMessage());
                                    }
                                }
                            }
                        } else {
                            return $this->fail('invalid-token', 'Token is invalid or expired');
                        }
                    }
                } else {
                    return $this->fail('action-not-set', 'Please set action parameter');
                }
            } else {
                return $this->fail('invalid-server', 'Please set valid server parameter');
            }
        } else {
            return $this->fail('invalid-input', 'Please POST valid json');
        }
    }

    /**
     * 
     * @param string $token
     * @return \PDO
     */
    protected function connect($input)
    {
        $authType = $this->currentServer['auth_type'];
        
        if($authType == 'session'){
            if(isset($_SESSION['auth_'.$input['server']])){
                $decoded = $_SESSION['auth_'.$input['server']];
                $decoded['iat'] = time();
            } else {
                $this->error = [
                    'code' => 'session-not-set',
                    'message' => 'Session is not set, please login again'
                ];
            }
        }else if($authType == 'token'){
            if(isset($input['token'])){
                $token = $input['token'];
                $decoded = (array) \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($this->secret, 'HS256'));
            } else {
                $this->error = [
                    'code' => 'token-not-set',
                    'message' => 'Token is not set, please login to fetch token'
                ];
            }
        }
        
        if (isset($decoded['iat'])) {
            $iat = $decoded['iat'];
            if ($iat > time() - $this->expireTime) {

                $username = null;

                if (isset($decoded['username'])) {
                    $username = $decoded['username'];
                }

                $password = null;
                if (isset($decoded['password'])) {
                    $password = $decoded['password'];
                }

                return new \PDO($this->currentServer['dsn'], $username, $password);
            } else {
                $this->error = [
                    'code' => 'token-expired',
                    'message' => 'Token expired, please login again'
                ];
            }
        } else {
            $this->error = [
                'code' => 'token-invalid',
                'message' => 'Token invalid, no iat field'
            ];
        }
    }

    protected function isValidMd5($input)
    {
        if(!isset($this->currentServer['check_md5'])){
            return true;
        }

        if($this->currentServer['check_md5']){
            return true;
        }

        if($this->currentServer['auth_type'] == 'session'){
            return true;
        }

        if($this->currentServer['auth_type'] == 'token'){
            if (isset($input['token']) && isset($input['md5'])) {
                $md5 = $input['md5'];
                unset($input['md5']);
                return md5(json_encode($input)) === $md5;
            }
            return false;
        }
    }

    protected function queryFetch($input)
    {
        $pdo = $this->connect($input);

        $stm = $pdo->prepare($input['sql']);

        $params = [];
        if (isset($input['params'])) {
            $params = $input['params'];
        }

        if ($stm && $stm->execute($params)) {

            $rowCount = $stm->rowCount();

            $rows = $stm->fetchAll(\PDO::FETCH_ASSOC);
            $lastInsertId = $pdo->lastInsertId();
            $result = ['updatedRowsCount' => $rowCount, 'lastInsertId' => $lastInsertId, 'rows' => $rows];
            
            return $result;
        } else {
            $this->error = [
                'code' => 'query-error',
                'message' => $pdo->errorInfo()
            ];
            throw new \ErrorException('Query Exception');
        }
    }

    protected function query($input)
    {
        $result = $this->queryFetch($input);
        return $this->success($result);
    }
    
    protected function queryXml($input)
    {
        $result = $this->queryFetch($input);
        $rows = '';
        
        if(isset($result['rows'][0])){
            $colArray = array_keys($result['rows'][0]);
            $columns = '';
            foreach ($colArray as $col){
                $columns .= '<column>'. htmlentities($col).'</column>';
            }
            
            $rows .= '<columns>'.$columns.'</columns><rows>';
            foreach ($result['rows'] as $row){
                $cols = '';
                foreach ($row as $col => $val){
                    $cols .= '<v>'. htmlentities($val).'</v>';
                }
                
                $rows .= '<row>'. $cols.'</row>';
            }
            $rows .= '</rows>';
        }
        
        $xml = '<result><success>1</success><updatedRowsCount>'.$result['updatedRowsCount'].'</updatedRowsCount><lastInsertId>'.$result['lastInsertId'].'</lastInsertId><result>'.$rows.'</result></result>';
        if($this->http){
            header('Content-Type: application/xml; charset=UTF-8');
            echo $xml;
            exit;
        }
        
        return $xml;
    }

    protected function login($input)
    {
        $username = null;

        if (isset($input['username'])) {
            $username = $input['username'];
        }

        $password = null;
        if (isset($input['password'])) {
            $password = $input['password'];
        }

        try {
            new \PDO($this->currentServer['dsn'], $username, $password);
            
            if($this->currentServer['auth_type'] == 'session'){
                $_SESSION['auth_'.$input['server']] = $input;
                return $this->success();
            }else if($this->currentServer['auth_type'] == 'token'){
                $input['iat'] = time();
                $token = JWT::encode($input, $this->secret, 'HS256');
                return $this->success(['token' => $token]);
            }
        } catch (\Exception $ex) {
            return $this->fail('login-pdo-exception', $ex->getMessage());
        }
    }

    protected function fail($errorCode, $errorMsg)
    {
        $data['errorCode'] = $errorCode;
        $data['errorMsg'] = $errorMsg;
        $data['success'] = false;
        return $this->jsonResponse($data);
    }

    protected function success($data = [])
    {
        $data['success'] = true;
        return $this->jsonResponse($data);
    }

    protected function jsonResponse($data)
    {
        if ($this->http) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode($data);
            exit;
        }
        return $data;
    }

}
