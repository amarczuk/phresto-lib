<?php

namespace Phresto;
use Phresto\MySQLModel;

define( 'PHRESTO_ROOT', __DIR__ . '/../temp/' );
require_once __DIR__ . '/../src/Utils.php';

Utils::registerLibAutoload();
Utils::registerAutoload();

class TestModel extends MySQLModel {
    const CLASSNAME = __CLASS__;

    const DB = 'mockmysql';
    const NAME = 'test';
    const INDEX = 'id';
    const COLLECTION = 'test';

    protected static $_currentUser = null;
    protected static $_fields = [ 'id' => 'int',
                                  'field1' => 'string',
                                  'field2' => 'string',
                                  'field3' => 'int',
                                ];
}

class MockMySQLConnector extends DBConnector
{
    public static $queries = [];

    public static function reset() {
        static::$queries = [];
    }

    public function connect( $options ) {
        return true;
    }

    public function escape( $var ) {
        return $var;
    }

    public function bind( $query, $variables ) {
        return $query;
    }

    public function query( $query, $bindings = [] ) {
        static::$queries[] = [ 'query' => $query, 'bindings' => $bindings ];
        return 'result';
    }

    public function getNext( $r ) {
        return false;
    }
}

$mockDb = new MockMySQLConnector( 'mockmysql', [] );

function test1() {
    MockMySQLConnector::reset();
    $query = ['where' => [ 'field1' => 'abc', 'field2' => 'bda']];
    TestModel::find($query);
    var_dump(MockMySQLConnector::$queries);
}

function test2() {
    MockMySQLConnector::reset();
    $query = ['where' => [
        'field1' => 'abc',
        'or' => [
            'field1' => 'bda',
            'field2' => 'ggg'
        ]
    ]];
    TestModel::find($query);
    var_dump(MockMySQLConnector::$queries);
}

function test3() {
    MockMySQLConnector::reset();
    $query = ['where' => [
        'field1' => 'abc',
        'or' => [
            ['field1' => 'bda'],
            ['field1' => 'ggg']
        ]
    ]];
    TestModel::find($query);
    var_dump(MockMySQLConnector::$queries);
}

function test4() {
    MockMySQLConnector::reset();
    $query = ['where' => [
        'field1' => 'abc',
        'or' => [
            ['field1' => ['!=', 'bda']],
            ['field1' => 'ggg'],
            'field2' => 'ccc',
            'and' => [
                'field1' => ['<>', 'sss'],
                'field2' => ['in', ['x', 'y', 'z']]
            ]
        ]
    ]];
    TestModel::find($query);
    var_dump(MockMySQLConnector::$queries);
}

test4();
