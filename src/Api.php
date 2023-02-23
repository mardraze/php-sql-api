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

    protected $secureToken;
    protected $cli = false;
    protected $expireTime = 1440;
    protected $error;

    /**
     * Constructor
     * 
     * @param array $options Optional parameters, key description
     *                       - secureToken
     *                       - cli
     */
    public function __construct($options = [])
    {
        if (isset($options['secureToken'])) {
            $this->secureToken = $options['secureToken'];
        } else if (defined('SECURE_TOKEN') && SECURE_TOKEN) {
            $this->secureToken = SECURE_TOKEN;
        }

        if (isset($options['expireTime'])) {
            if ($options['expireTime'] > 0) {
                $this->expireTime = (int) $options['expireTime'];
            } else {
                throw new \ErrorException('expireTime option should be greater than zero');
            }
        }

        $this->cli = isset($options['cli']) && $options['cli'];
    }

    /**
     * Process http input from clients
     * 
     * @param array $input Input to process optional  Parse http input if not set.
     * @return mixed
     */
    public function processInput($input = null)
    {
        if (!$this->secureToken) {
            return $this->fail('invalid-config', 'config.inc.php file is invalid, please define SECURE_TOKEN constant or remove config.inc.php file to generate it automatically');
        }

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
            return $this->fail('invalid-input', 'Please POST valid json');
        }
    }

    /**
     * 
     * @param string $token
     * @return \PDO
     */
    protected function connect($token)
    {
        if ($token) {
            $decoded = (array) \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($this->secureToken, 'HS256'));
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

                    return new \PDO($decoded['dsn'], $username, $password);
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
        } else {
            $this->error = [
                'code' => 'token-not-set',
                'message' => 'Token is not set, please login to fetch token'
            ];
        }
    }

    protected function isValidMd5($input)
    {
        if (isset($input['token']) && isset($input['md5'])) {
            $md5 = $input['md5'];
            unset($input['md5']);
            return md5(json_encode($input)) === $md5;
        }
        return false;
    }

    protected function queryFetch($input)
    {
        $pdo = $this->connect($input['token']);

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
        if($this->cli){
            return $xml;
        }
        
        header('Content-Type: application/xml; charset=UTF-8');
        echo $xml;
        exit;
    }

    protected function login($input)
    {
        if (!isset($input['dsn'])) {
            return $this->fail('no-dsn-parameter', 'Please set dsn parameter, see https://www.php.net/manual/en/pdo.construct.php');
        }

        $username = null;

        if (isset($input['username'])) {
            $username = $input['username'];
        }

        $password = null;
        if (isset($input['password'])) {
            $password = $input['password'];
        }

        try {
            new \PDO($input['dsn'], $username, $password);
            $input['iat'] = time();
            $token = JWT::encode($input, $this->secureToken, 'HS256');
            return $this->success(['token' => $token]);
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
        if ($this->cli) {
            return $data;
        }
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data);
        exit;
    }

}
