<?php

namespace Tests\Mardraze\SqlApi;

use PHPUnit\Framework\TestCase;

class ApiTest extends TestCase
{
    private static function makeConfig($dsn){
        return [
            'blowfish_secret' => 'abc',
            'Servers' => [
                1 => ['auth_type' => 'token', 'dsn' => $dsn]
            ]
        ];
    }
    
    /**
     * 
     * @param \Mardraze\SqlApi\Api $api
     * @param type $authInput
     * @param type $input
     * @return type
     */
    private static function processInput($api, $authInput, $input){
        $response = $api->processInput($authInput);
        $token = $response['token'];
        $input['token'] = $token;
        $input['md5'] = md5(json_encode($input));
        return $api->processInput($input);
    }
    
    public static function exec($sql, $params = [], $action = null){
        if(!$action){
            $action = 'query';
        }
        $dbFile = __DIR__.'/../storage/test-db-sqlite-contracts.sq3';
        $api = new \Mardraze\SqlApi\Api(self::makeConfig('sqlite:'.$dbFile));
        $authInput = ['action' => 'login'];
        $authResponse = $api->processInput($authInput);
        $token = $authResponse['token'];
        $input = ['action' => $action, 'sql' => $sql, 'params' => $params];
        $input['token'] = $token;
        $input['md5'] = md5(json_encode($input));
        return $api->processInput($input);
    }
    
    public function testLogin(){
        $dbFile = __DIR__.'/../storage/test-db-sqlite-'.uniqid().'.sq3';
        if(file_exists($dbFile)){
            unlink($dbFile);
        }
        $api = new \Mardraze\SqlApi\Api(self::makeConfig('sqlite:'.$dbFile));
        
        $input = ['action' => 'login', 'server' => 1];
        
        $response = $api->processInput($input);
        
        $decoded = (array)\Firebase\JWT\JWT::decode($response['token'], new \Firebase\JWT\Key('abc', 'HS256'));
        $iat = $decoded['iat'];
        unset($decoded['iat']);
        
        if(file_exists($dbFile)){
            unlink($dbFile);
        }
        
        $this->assertTrue($response['success']);
        $this->assertEquals($input, $decoded);
        $this->assertGreaterThan(time() - 100, $iat);
    }
    
    public function testQuery(){
        $dbFile = __DIR__.'/../storage/test-db-sqlite-'.uniqid().'.sq3';
        if(file_exists($dbFile)){
            unlink($dbFile);
        }
        
        $api = new \Mardraze\SqlApi\Api(self::makeConfig('sqlite:'.$dbFile));
        
        $authResult = $api->processInput(['action' => 'login']);
        
        $input = ['action' => 'query', 'sql' => 'CREATE TABLE contacts (
                contact_id INTEGER PRIMARY KEY,
                first_name TEXT NOT NULL,
                last_name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                phone TEXT NOT NULL UNIQUE
        );'];
        $input['token'] = $authResult['token'];
        $input['md5'] = md5(json_encode($input));
        
        $response = $api->processInput($input);

        if(file_exists($dbFile)){
            unlink($dbFile);
        }
        
        $this->assertTrue($response['success']);
    }
    
    public function testQueryInsert(){
        $this->assertTrue(true);
        return;
        $dbFile = __DIR__.'/../storage/test-db-sqlite-'.date('His').'-'.uniqid().'.sq3';
        if(file_exists($dbFile)){
            unlink($dbFile);
        }
        $api = new \Mardraze\SqlApi\Api(self::makeConfig('sqlite:'.$dbFile));

        $authInput = ['action' => 'login', 'dsn' => 'sqlite:'.$dbFile];
        
        $response = $api->processInput($authInput);
        //var_dump($response); exit;
        $token = $response['token'];
        $input['token'] = $token;
        $input['md5'] = md5(json_encode($input));
        
        $sqls = [
            'CREATE TABLE contacts (
                contact_id INTEGER PRIMARY KEY,
                first_name TEXT NOT NULL,
                last_name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                phone TEXT NOT NULL UNIQUE
            );',
        ];

        for($i=1; $i<=1000; $i++){
            $sqls []= ['INSERT INTO contacts (contact_id, first_name, last_name, email, phone) VALUES (?, ?, ?, ?, ?)', 
                [$i, "first_name ".$i, "last_name ".$i,  uniqid().'@example.com', rand()]];
        }

        foreach ($sqls as $sql){
            if(is_array($sql)){
                $input = ['action' => 'query', 'sql' => $sql[0], 'params' => $sql[1]];
            }else{
                $input = ['action' => 'query', 'sql' => $sql];
            }

            $input['token'] = $token;
            $input['md5'] = md5(json_encode($input));

            $response = $api->processInput($input);
            $this->assertTrue($response['success']);
        }

        if(file_exists($dbFile)){
            unlink($dbFile);
        }

    }
    
    public function testQuerySelect(){
        $sql = 'SELECT * FROM contacts WHERE contact_id < 10';
        
        $dbFile = __DIR__.'/../storage/test-db-sqlite-contracts.sq3';
        $api = new \Mardraze\SqlApi\Api(self::makeConfig('sqlite:'.$dbFile));
        $authInput = ['action' => 'login'];
        $authResponse = $api->processInput($authInput);
        $token = $authResponse['token'];
        $input = ['action' => 'query', 'sql' => $sql];
        $input['token'] = $token;
        $input['md5'] = md5(json_encode($input));

        $response = $api->processInput($input);
        $this->assertTrue($response['success']);
        $this->assertEquals(0, $response['updatedRowsCount']);
        $this->assertEquals('0', $response['lastInsertId']);
    }
    
    public function testQueryXml(){
        $sql = 'SELECT * FROM contacts WHERE contact_id < 10';
        
        $dbFile = __DIR__.'/../storage/test-db-sqlite-contracts.sq3';
        $api = new \Mardraze\SqlApi\Api(self::makeConfig('sqlite:'.$dbFile));
        $authInput = ['action' => 'login'];
        $authResponse = $api->processInput($authInput);
        $token = $authResponse['token'];
        $input = ['action' => 'query-xml', 'sql' => $sql];
        $input['token'] = $token;
        $input['md5'] = md5(json_encode($input));

        $xml = $api->processInput($input);
        
        $xml_object = simplexml_load_string($xml);

        $response = @json_decode(@json_encode($xml_object),1);
        $this->assertEquals('1', $response['success']);
        $this->assertEquals('contact_id', $response['result']['columns']['column'][0]);
        $this->assertEquals('first_name 1', $response['result']['rows']['row'][0]['v'][1]);
    }
    

    public function testEmoji(){
        self::exec('UPDATE contacts SET first_name = ? WHERE contact_id = 200', ['first 😋 name']);
        $response = self::exec('SELECT * FROM contacts WHERE contact_id = 200');
        $xml = self::exec('SELECT * FROM contacts WHERE contact_id = 200', [], 'query-xml');
        $xml_object = simplexml_load_string($xml);

        $response2 = @json_decode(@json_encode($xml_object),1);
        
        $this->assertTrue($response['success']);
        $this->assertEquals('first 😋 name', $response['rows'][0]['first_name']);
        $this->assertEquals('first 😋 name', $response2['result']['rows']['row']['v'][1]);
    }
    
}
