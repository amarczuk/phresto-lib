<?php

namespace Phresto;
use Phresto\Interf\ModelInterface;

class Model implements ModelInterface, \JsonSerializable {

    const CLASSNAME = __CLASS__;

    const DB = 'maindb';
	const NAME = 'model';
	const INDEX = 'id';
    const COLLECTION = 'model';

    protected $_properties = [];
    protected $_calculated_properties = [];
    protected $_initial = [];
    protected $_debug = '';
    protected $_new = true;

    /**
    * array of the model field names (as in db) and types
    * [
    *   'id' => 'int',
    *   'name' => [type: 'string', db: 'TEXT'],
    *   'created' => 'DateTime'
    * ]
    */
    protected static $_fields = [];
    /**
     * array of the model field names and types that are not in database
     * [
     *   'fullName' => 'string'
     * ]
     */
    protected static $_calculated_fields = [];

    /**
    * array of calculated default field values (['field_name' => 'default value'])
    * if value should be determined during runtime leave value empty and
    * add protected function `default_field_name()` returning default value
    */
	protected static $_defaults = [];

    /**
    * array describing model relations:
    * 'model_name' => [ // key is the related model name
    *        'type' => '1:n', // 1:1, 1>1, 1<1, 1:n, n:1, n:n - first model second related model
    *        'model' => 'model_name', // related model name
    *        'field' => 'field_in_related_model',  // name of the FK in related model
    *        'index' => 'id', // index (FK) in the model
    *        'dbactions' => 'ON UPDATE CASCADE ON DELETE CASCADE', // relation actions if applicable
    *        'junction' => [ // junction table description for n:n relations
    *           'collection' => 'junction_table', // junction table name
    *           'field' => 'related_model_fk', // related model FK
    *           'index' => 'model_fk' // model FK
    *        ]
    *    ]
    */
    protected static $_relations = [];

    /**
    * array describing database indexes
    * 'index_name' => [  // key is the name of the index in the database
    *   'fields' => ['field1', 'field2'], // field names
    *   'unique' => true|false // (false by default)
    * ]
    */
	protected static $_indexes = []; // TODO

    public function __construct( $option = null, $checkIfNew = true ) {
        $this->_new = true;

        if ( empty( $option ) ) {
            $this->getEmpty();
        } else if ( is_array( $option ) && isset( $option['where'] ) ) {
            $option['limit'] = 1;
            $result = static::find( $option );
            if ( !empty( $result ) && !empty( $result[0] ) ) {
                $this->setObject( $result[0], $checkIfNew );
            } else {
                $this->getEmpty();
            }
        } else if ( is_array( $option ) ) {
            $this->set( $option, $checkIfNew );
        } else if ( is_object( $option ) ) {
            $this->setObject( $option, $checkIfNew );
        } else if ( is_string( $option ) && !is_numeric( $option ) && $json = json_decode( $option, true ) ) {
            $this->set( $json, $checkIfNew );
        } else {
            $this->setById( $option );
        }

        $this->_initial = $this->_properties;
    }

    public static function getIndexField() {
        return static::INDEX;
    }

    public static function getName() {
        return static::NAME;
    }

    public static function getCollection() {
        return static::COLLECTION;
    }

    public static function isRelated( $modelName ) {
        return array_key_exists( $modelName, static::$_relations );
    }

    public static function getRelation( $modelName ) {
        return static::$_relations[$modelName];
    }

    public function setIndex( $id ) {
        $this->_properties[static::INDEX] = $id;
        $this->_new = false;
    }

    public static function getFields() {
        return array_keys( static::$_fields );
    }

    public static function getFieldsAndTypes() {
        return static::$_fields;
    }

    public static function getCreationCode() {
        return '';
    }

    public static function getRelationCode() {
        return '';
    }

    protected function getEmpty() {
    	$this->_properties = [];
    	foreach( static::$_fields as $field => $type ) {
    		$this->_properties[$field] = '';
    	}
    	$this->_new = true;
    }

    public function setById( $id ) {
        $obj = static::find( [ 'where' => [ static::INDEX => $id ], 'limit' => 1 ] );
        if ( isset( $obj[0] ) ) {
            $this->setObject( $obj[0], false );
        }
    }

    public function setRelatedById( $model, $id ) {
        $related = static::findRelated( $model, [ 'where' => [ static::INDEX => $id ], 'limit' => 1 ] );
        if ( isset( $related[0] ) ) {
            $this->setObject( $related[0], false );
        }
    }

    public function update( $modelArray ) {
        foreach( static::$_fields as $field => $type ) {
            if ( isset( $modelArray[$field] ) ) {
                $this->$field = $modelArray[$field];
            }
        }
        foreach (static::$_calculated_fields as $field => $type) {
            if (isset($modelArray[$field])) {
                $this->$field = $modelArray[$field];
            }
        }
    }

    protected function set( $modelArray, $checkIfNew = true ) {
    	$this->_properties = [];
        $this->update( $modelArray, $checkIfNew );

        $this->setNew($checkIfNew);
    }

    protected function setNew( $checkIfNew = true ) {
        if ( !empty( $this->_properties[static::INDEX] ) && !$checkIfNew ) {
            $this->_new = false;
            return;
        }

        if ( !empty( $this->_properties[static::INDEX] ) ) {
            $obj = static::find( [ 'where' => [ static::INDEX => $this->getIndex() ], 'limit' => 1 ] );
            if ( isset( $obj[0] ) ) {
                $this->_new = false;
            }
        }
    }

    protected function setObject( $model, $checkIfNew = true ) {
        $this->_properties = [];
        $this->_calculated_properties = [];
        foreach( static::$_fields as $field => $type ) {
            if ( isset( $model->$field ) ) {
                $this->$field = $model->$field;
            }
        }
        foreach( static::$_calculated_fields as $field => $type ) {
            if ( isset( $model->$field ) ) {
                $this->$field = $model->$field;
            }
        }

        $this->setNew($checkIfNew);
    }

    public static function find( $query ) {
        $class = static::CLASSNAME;
        return [ new $class() ];
    }

    public static function findOne($query)
    {
        $class = static::CLASSNAME;
        return new $class();
    }

    public static function count() {
        return 0;
    }

    public static function findRelated( Model $model, $query = null ) {
    	$class = static::CLASSNAME;
        return [ new $class() ];
    }

    protected function saveFilter() {
        return true;
    }

    protected function saveValidate() {
        return true;
    }

    protected function saveRecord() {
        return true;
    }

    protected function saveAfter() {
        return true;
    }

    protected function saveSetDefaults() {
        if ( !$this->_new ) return true;
        foreach ( static::$_defaults as $key => $value ) {
            if ( empty( $this->_properties[$key] ) ) {
                if ( empty( $value ) && method_exists( $this, 'default_' . $key ) ) {
                    $this->$key = $this->{'default_' . $key}();
                } else {
                    $this->$key = $value;
                }
            }
        }
    }

    public function save() {
        $this->saveSetDefaults();
    	$this->saveFilter();
        $this->saveValidate();
        $this->saveRecord();
        $this->_initial = $this->_properties;
        $this->saveAfter();
    }

    protected function deleteValidate() {
        return true;
    }

    protected function deleteRecord() {
        return true;
    }

    protected function deleteAfter() {
        return true;
    }

    public function delete() {
    	$this->deleteValidate();
        $this->deleteRecord();
        $this->_properties[static::INDEX] = null;
        $this->_initial = $this->_properties;
        $this->_new = true;
        $this->deleteAfter();
    }

    public function getIndex() {
        if ( isset( $this->_properties[static::INDEX] ) ) {
            return $this->_properties[static::INDEX];
        }

        return null;
    }

    public function __set( $name, $value ) {
        if ( $name == '_debug_' ) {
            return $this->_debug = $value;
        }

    	if ( array_key_exists( $name, static::$_fields ) ) {
    		$this->_properties[$name] = $this->getTyped( $name, $value );
    	}

        if (array_key_exists($name, static::$_calculated_fields)) {
            $this->_calculated_properties[$name] = $this->getTyped($name, $value);
        }
    }

    public function __get( $name ) {
        if ( $name == '_debug_' ) {
            return $this->_debug;
        }

        if ( array_key_exists( $name, static::$_fields ) ) {
    	   return $this->getTyped( $name );
        }

        if (array_key_exists($name, static::$_calculated_fields)) {
            return $this->getTyped($name);
        }

        if ( method_exists( $this, "{$name}_value" ) ) {
            $method = "{$name}_value";
            return $this->$method();
        }
    }

    public function __isset( $name ) {
        $debug = ( $name == '_debug_' && !empty( $this->_debug ) );
    	return ( $debug ||
            ( array_key_exists( $name, static::$_fields ) && isset( $this->_properties[$name] ) ) ||
            ( array_key_exists( $name, static::$_calculated_fields ) && isset( $this->_calculated_properties[$name] ) )
        );

    }

    protected function filterJson( $fields ) {
        return $fields;
    }

    protected function getUniType( $typeDef ) {
        $type = is_array($typeDef) ? $typeDef['type'] : $typeDef;
        switch ( $type ) {
            case 'bool':
                return 'boolean';
            case 'int':
                return 'integer';
            case 'float':
                return 'double';
            default:
                return $type;
        }
    }

    protected function getTyped( $name, $value = null ) {
        $field = array_key_exists( $name, static::$_fields ) ? static::$_fields[$name] : false;
        $properties = &$this->_properties;
        if ( !$field ) {
            $field = array_key_exists( $name, static::$_calculated_fields ) ? static::$_calculated_fields[$name] : false;
            $properties = &$this->_calculated_properties;
        }

        if ( $field ) {
            if ( $value === null && isset( $properties[$name] ) ) {
                $value = $properties[$name];
            }

            $type = $this->getUniType( $field );

            if ( class_exists( $type ) ) {
                if ( !is_a( $value, $type ) ) $value = new $type( $value );
            } else if ( class_exists( '\\' . $type ) ) {
                if ( !is_a( $value, '\\' . $type ) ) {
                    $type = '\\' . $type;
                    $value = new $type( $value );
                }
            } else if ( $type == 'boolean' && $value === 'false' ) {
                $value = false;
            } else if ( $this->getUniType( gettype( $value ) ) != $type ) {
                settype( $value, $type );
            }
        }

        return $value;
    }

    public function jsonSerialize() {
        $fields = $this->filterJson( array_merge($this->_properties, $this->_calculated_properties) );
        foreach ( $fields as $key => $value ) {
            if ( $value instanceof \DateTime ) {
                $fields[$key] = $value->format( \DateTime::ISO8601 );
            }
        }
        if ( !empty( $this->_debug ) ) {
            $fields['_debug_'] = $this->_debug;
        }
        return $fields;
    }

}
