<?php

namespace Phresto;

class View {

	private static $instances = [];
	public static $lang = null;

	public $elements = [];
	public $config   = [];
	public $style    = [];
	public $javascript = [];
    private $flushed = false;
    private $flush_no = 0;

    public static function jsonResponse( $response ) {
    	$debug = ob_get_contents();
		ob_clean();
		$conf = Config::getConfig('app');
		if ( !empty( $debug ) && !empty( $conf['app'] ) && !empty( $conf['app']['debug'] ) && $conf['app'] = 'on' ) {
			$debug = explode( "\n", trim( $debug ) );
			if ( Utils::is_assoc_array( $response ) ) {
				$response['_debug_'] = $debug;
			} else if ( is_object( $response ) ) {
				$response->_debug_ = $debug;
			} else if ( is_array( $response ) ) {
				$response[] = [ '_debug_' => $debug ];
			}
		}

    	return [ 'body' => json_encode( $response, JSON_PRETTY_PRINT ), 'content-type' => 'application/json' ];
    }

	public static function getView( $name, $module = null ) {
		if ( isset( self::$instances[$name] ) ) return self::$instances[$name];
		self::$instances[$name] = new View( $module );
		return self::$instances[$name];
	}

	public function __construct( $module = null ) {
		$this->config = Config::getConfig( 'view', $module );
		if ( isset( $this->config['view'] ) && is_array( $this->config['view'] ) && isset( $this->config['view']['inherit'] ) && $this->config['view']['inherit'] == 'yes') {
			$viewconf = Config::getConfig( 'view' );
			$this->config = Config::mergeConfigs( $viewconf, $this->config );
		}
		if ( empty( self::$lang ) ) {
			self::$lang = $this->config['page']['lang'];
		}
	}
	
	public function addJs( $data ) {
		$this->addScript( 'inline_js', $data );
	}

	public function add( $template, $data, $module = null ) {

		if ( $template == '' ) return false;
		if ( ( !is_array( $data ) /*|| !is_object( $data )*/ ) && $template != 'inline' ) return false;
		if ( $data == '' && $template == 'inline' ) return false;

		if ( !empty( $module ) ) {
			require_once( 'modules/' . $module . '/lang/' . self::$lang . '.php' );
		} else {
			require_once( 'lang/' . self::$lang . '.php' );
		}
		
		if ( $template == 'inline' ) {
			array_push( $this->elements, [ 'inline' => true, 'content' => $data ] );
			return true;
		}

		$path = 'view/';

		if ( !empty( $module ) ) {
			$path = 'modules/' . $module . '/view/';
		}
		
		if ( !is_file( $path . $template . '.htm' ) ) {
			if ( empty( $module ) ) return false;
			$path = 'view/';
			if ( !is_file( $path . $template . '.htm' ) ) return false;
		}
		
		array_push( $this->elements, [ 'file' => $path . $template . '.htm', 'data' => $data, 'path' => $path ] );
		
		return true;
	}
	
	function addScript( $file, $data = '', $module = null ) {
		if ( empty( $file ) && empty( $data ) ) return false;
		
		if ( $file == 'inline_js' ) {
			if ( empty( $data ) ) return false;
		
			$data="\n<script type='text/javascript'>\n<!--\n{$data}\n//-->\n</script>\n";
			$this->add( 'inline', $data );
			return true;
		}

		if ( $file == 'inline_url' ) {
			if ( empty( $data ) ) return false;
		
			$this->add( 'inline', "\n<script type='text/javascript' src='//{$data}'></script>\n" );
			return true;
		}

		if ( empty( $module ) ) {
			$path = 'static/js/';
		} else {
			$path = 'modules/' . $module . '/static/';
		}

		if ( $file == 'inline_file' ) {
			
			if ( empty( $data ) || !is_file( $path . $data ) )  return false;
			
			$data = "\n<script type='text/javascript' src='/{$path}{$data}'></script>\n";
			
			$this->add( 'inline', $data );
			return true;
		}
		
		if ( !is_file( $path . $file ) )  return false;
	
		array_push( $this->javascript, array( 'file' => $path . $file, 'content' => '' ) );
		
		return true;
	}
	
	
	public function addCss( $file, $data='', $module = null ) {
		if ( empty( $file ) && empty( $data ) ) return false;
		
		if ( $file == 'inline_css' ) {
			if ( $data == '' ) return false;
		
			$this->add( 'inline', "\n<style>\n{$data}\n</style>\n" );
			return true;
		}

		if ( $file == 'inline_url' ) {
			if ( empty( $data ) ) return false;

			$this->add( 'inline', "\n<script type='text/javascript' src='//{$data}'></script>\n" );
			return true;
		}

		if ( empty( $module ) ) {
			$path = 'static/js/';
		} else {
			$path = 'modules/' . $module . '/static/';
		}

		if ( $file == 'inline_file' ) {
			
			if ( empty( $data ) || !is_file( $path . $data ) ) return false;
			
			$this->add( 'inline',"\n<link rel='stylesheet' href='/{$path}{$data}'>\n" );
			return true;
		}

		
		if ( !is_file( $path . $file ) )  return false;
	
		array_push( $this->style, array( 'file' => $path . $file, 'content' => '' ) );
		
		return true;
	
	}
	
	public function get( $continue = true ) {
		if ( !$this->flushed || !$continue ) {
		    $out = $this->getHead();
        }
		
        $elements = [];
        $c = count( $this->elements );
        $j = ( $continue ) ? 0 : $this->flush_no;
        
        for ( $i = $j; $i < $c; $i++ ) {
            $elements[$i] = $this->elements[$i];
        }
		
		foreach ( $elements as $element ) {
			$out .= self::render( $element );
		}
		
		$out .= $this->closingJS();

		$debug = ob_get_contents();
		ob_clean();
		$conf = Config::getConfig('app');

		if ( !empty( $debug ) && !empty( $conf['app'] ) && !empty( $conf['app']['debug'] ) && $conf['app'] = 'on' ) {
			$debug = str_replace( '<', '&lt;', $debug );
			$out .= "\n\n<div class='phrestoDebug'>{$debug}</div>\n\n";
		}

		$out .= "\n\n</body>\n\n</html>";
		$out = $this->remove_utf8_bom( $out );

		if ( !$this->flushed || !$continue ) {
			$out = [ 'body' => $out, 'content-type' => "text/html; charset={$this->config['page']['charset']}" ];
		}
		return $out;
	}
	
	private function closingJS() {
		if ( !is_array( $this->config['closingjs'] ) ) return '';
		$out = '';
		foreach ($this->config['closingjs'] as $js) {
			$out .= "\n<script type='text/javascript' src=\"{$js}\"></script>";
		}

		return $out;
	}

	private function remove_utf8_bom( $text ) {
		$bom = pack( 'H*','EFBBBF' );
		$text = str_replace( $bom, '', $text );
		return $text;
	}
    
    public function getFlush() {
		
        if ( !$this->flushed ) {
            
            header("Content-Type: text/html; charset={$this->config['page']['charset']}");
        
            $out = $this->getHead();
            $this->flushed = true;
        }
        
        $elements = [];
        $c = count( $this->elements );
        for ( $i = $this->flush_no; $i < $c; $i++ ) {
            $elements[$i] = $this->elements[$i];
        }
        
        $this->flush_no = $i;
        
        foreach ( $elements as $element ) {
            $out .= self::render( $element );
        }
                                                                
        echo $out;
                        
        ob_flush();
        flush();     
    }
    
	
	public static function render( $element ) {
		
		if ( !empty( $element['inline'] ) ) {
			$data = $element['content'];			
		} else {
			$data = file_get_contents( $element['file'] );
		}
		
		// insert other views {? insert(xxxxx) ?}
		
		$tochange = [];
		$pattern = '#\{\?[\s]*insert\((?P<insert>[^\)]*)\)[\s]*\?\}#iU';
		$cnt = preg_match_all( $pattern, $data, $tochange );
	
		for ( $i = 0; $i < $cnt; $i++ ) {
			$file = $tochange['insert'][$i];
			if ( mb_strpos( $file, '$' ) === 0 && isset( $element['data'][ trim( $file, '$ ' ) ] ) ) {
				$file = $element['data'][ trim( $file, '$ ' ) ];
			}
			$data = str_replace( $tochange[0][$i], "\n" . trim( self::insert( $file, $element['path'], 0, $element['data'] ) )."\n", $data );
		}
		
		
		// replace constants {? CONSTANT ?}
		
		$tochange = [];
		$pattern = '#\{\?[\s]*(?P<const>[\w\d_]*)[\s]*\?\}#i';
		$cnt = preg_match_all( $pattern, $data, $tochange );
		
		for ( $i = 0; $i < $cnt; $i++ ) {
			$data = str_replace( $tochange[0][$i], @constant( $tochange['const'][$i] ), $data );
		}
		
		// add loops {? repeat($variable) ?} i {? /repeat ?}
		
		$data = preg_replace( '#\{\?([\s]*)\/repeat([\s]*)\?\}#i', '{?/repeat?}', $data );
		
		$tochange = [];
		$pattern = '#\{\?[\s]*repeat\(\$(?P<variable>[^\)]*)\)[\s]*\?\}#isU';
		preg_match_all( $pattern, $data, $tochange );
       
       	$cnt = count( $tochange[0] );
		$last_poz = 0;
		
		for ( $i = 0; $i < $cnt; $i++ ) {
			$from = mb_strpos( $data, $tochange[0][$i] );
			$to1 = mb_strpos( $data, '{?/repeat?}' ) + 11;

			$k = $i + 1;
			if ( $k < $cnt ) {
				$next = mb_strpos( $data, $tochange[0][$k], $from   + 1 );
			} else {
				$k = $i;
				$next = $to1;
			}
            
            if ($k <= $cnt && $next > $to1 && $next > $from ) {
                $k--;
            }
				
			while ( $k < $cnt && $next < $to1 && $next > $from ) {
				$data = "\n" . trim( self::repeats( $data, $k, $cnt, $tochange, $next, $element ) ) . "\n";
				
				$to1 = mb_strpos( $data, '{?/repeat?}' ) + 11;
				$to = $to1 - $from;
				
				$k++;
				if ($k <= $cnt) {
					$next = mb_strpos( $data, $tochange[0][$k] );
				} else {
					$k--;
					$next = $to1;
				}
                
                if ( $k <= $cnt && $next > $to1 && $next > $from ) {
                    $k--;
                }
			}
			
			$to = $to1 - $from;
			
			$repeat = str_replace( [ '{?/repeat?}', $tochange[0][$i] ], '', mb_substr( $data, $from, $to ) );
			
			$tochange2 = [];
			$pattern = '#\{\?[\s]*\$' . $tochange['variable'][$i] . '\[(?P<variable>[^\]]*)\][\s]*\?\}#i';
			$cnt2 = preg_match_all($pattern, $repeat, $tochange2);
			
			$variable = $element['data'][$tochange['variable'][$i]];
			$full_data = '';
			
			if ( count( $variable ) > 0 ) {
				foreach ( $variable as $key => $var ) {
					
					$full_data1 = $repeat;
					
					for ( $j = 0; $j < $cnt2; $j++ ) {
						$full_data1 = str_replace( $tochange2[0][$j], $var[$tochange2['variable'][$j]], $full_data1 );	
					}
					
					$full_data .= $full_data1;
				}
			}
			
			$data = mb_substr( $data, 0, $from) . $full_data . mb_substr( $data, $to1 );
			
			$i = $k;
		}
		
		// replace variables {? $variable ?}
		
		$tochange = [];
		$pattern = '#\{\?[\s]*\$(?P<variable>[\w\d_]*)[\s]*\?\}#i';
		$cnt = preg_match_all( $pattern, $data, $tochange );
		
		for ( $i = 0; $i < $cnt; $i++ ) {
			$data = str_replace( $tochange[0][$i], $element['data'][$tochange['variable'][$i]], $data );
		}

		// replace conditions {? if(text1=text) ?} i {? /if ?}
		
		$data = preg_replace( '#\{\?([\s]*)\/if([\s]*)\?\}#i', '{?/if?}', $data );
		
		$tochange = [];
		$pattern = '#\{\?[\s]*if[\s]*\((?P<left>[^=]*)=(?P<right>[^\)]*)\)[\s]*\?\}(?P<content>.*)\{\?/if\?\}#isU';
		$cnt = preg_match_all( $pattern, $data, $tochange );
      
		for ( $i = 0; $i < $cnt; $i++ ) {
			$left = trim( $tochange['left'][$i], '$ ' );
			if ( ( isset( $element['data'][$left] ) && $element['data'][$left] == trim( $tochange['right'][$i] ) )
				||
			     ( $tochange['left'][$i] == $tochange['right'][$i] ) 
			   ) {
				$data = str_replace( $tochange[0][$i], $tochange['content'][$i], $data );
			} else {
				$data = str_replace( $tochange[0][$i], '', $data );
			}
		}

		return $data;
	}

	private static function repeats( $data, &$k, $cnt, $change, $next, $element )	{
			
		$from = $next;
		$to1 = mb_strpos( $data, '{?/repeat?}' ) + 11;
		
		$to1 = $k;
		
		$k++;
		if ( $k < $cnt ) {
			$next = mb_strpos( $data, $change[0][$k], $from );
		} else {
			$k = $i;
			$next = $to1;
		}
		
		while ( $k < $cnt && $next < $to1 && $next > 0 ) {
			$data = "\n" . trim( self::repeats( $data, $k, $cnt, $change, $next, $element ) ) . "\n";
			$to1 = mb_strpos( $data, '{?/repeat?}' ) + 11;
			$to = $to1 - $from;
			
			$k++;
			if ( $k <= $cnt ) {
				$next = mb_strpos( $data, $change[0][$k] );
			} else {
				$k--;
				$next = $to1;
			}
		}
		
		$to = $to1 - $from;
		
		$repeat = str_replace( ['{?/repeat?}', $change[0][$i]], '', mb_substr( $data, $from, $to ) );
		
		$change2 = [];
		$pattern = '#\{\?[\s]*\$' . $change['variable'][$i] . '\[(?P<variable>[^\]]*)\][\s]*\?\}#i';
		$cnt2 = preg_match_all( $pattern, $repeat, $change2 );
		
		$variable = $element['dane'][$change['variable'][$i]];
		$full_data = '';
		
		if ( count( $variable ) > 0 ) {
			foreach ( $variable as $key => $var ) {
				
				$full_data1 = $repeat;
				
				for ( $j = 0; $j < $cnt2; $j++ ) {
					$full_data1 = str_replace( $change2[0][$j], $var[$change2['variable'][$j]], $full_data1 )."\n";	
				}
				
				$full_data .= $full_data1;
				
			}
		}
		
		$data = mb_substr( $data, 0, $from ) . $full_data . mb_substr( $data, $to1 );

		return $data;
	}

	private static function insert( $template, $path, $z, $data )
	{
		if ( $z == 100 ) return '';
	
		$file = $path . $template . '.htm';
		if ( !is_file( $file ) ) 
		{
			$path = 'view/';
			$file = $path . $template . '.htm';
			if ( !is_file( $file ) ) {
				$tmp = explode( '/', $template );
				$path = 'modules/' . array_shift( $tmp ) . '/view/';
				$file = $path . implode( '/', $tmp ) . '.htm';
				if ( !is_file( $file ) ) return '';
			}
		}
		
		$data = file_get_contents( $file );
		
		$tochange = [];
		$pattern = '#\{\?[^i]*insert\((?P<insert>[^\)]*)\)[^\?]*\?\}#iU';
		$cnt = preg_match_all( $pattern, $data, $tochange );
		
		for ( $i = 0; $i < $cnt; $i++ )	{
			$file = $tochange['insert'][$i];
			if ( mb_strpos( $file, '$' ) === 0 && isset( $data[ trim( $file, '$ ' ) ] ) ) {
				$file = $data[ trim( $file, '$ ' ) ];
			}
			$data = str_replace( $tochange[0][$i], "\n".trim( self::insert( $tochange['insert'][$i], $path, $z+1, $data ) )."\n", $data );
		}
		
		return $data;
	
	}

	private function getHead() {
	
		$head = "<!DOCTYPE {$this->config['page']['doctype']}>\n";
		$head .= "<html>\n";
		$head .= "<head>\n";
		
		$head .= "\t<title>{$this->config['page']['title']}</title>\n";
		$head .= "\t<meta http-equiv=\"Content-Type\" content=\"text/html; charset={$this->config['page']['charset']}\">\n";
		
		if ( isset( $this->config['cache'] ) && is_array( $this->config['cache'] ) ) {
			foreach( $this->config['cache'] as $key => $var ) {
				$head .= "\t<meta http-equiv=\"{$key}\" content=\"{$var}\">\n";
			}
		}
		
		if ( isset( $this->config['headers'] ) && is_array( $this->config['headers'] ) ) {
			foreach( $this->config['headers'] as $key => $var ) {
				$head.="\t<meta name=\"{$key}\" content=\"{$var}\">\n";
			}
		}
		
		if ( isset( $this->config['rss'] ) && is_array( $this->config['rss'] ) ) {
		    foreach ( $this->config['rss'] as $file => $title ) {
                $head.="\t<link rel=\"alternate\" type=\"application/rss+xml\" title=\"{$title}\" href=\"/{$file}\">\n";
		    }
		}
		
		if ( !empty( $this->config['page']['favicon'] ) ) {
			$head.="\t<link rel=\"icon\" href=\"/{$this->config['page']['favicon']}\" type=\"image/x-icon\">\n";
		}

		if ( isset( $this->config['css'] ) && is_array( $this->config['css'] ) ) {
		    foreach ( $this->config['css'] as $key => $val ) {
				$head.="\t<link rel=\"stylesheet\" href=\"{$val}\" type=\"text/css\">\n";
			}
		}

		foreach( $this->style as $key => $var ) {
			if ( !empty( $var['file'] ) ) {
				$head.="\t<link rel=\"stylesheet\" href=\"{$var['file']}\" type=\"text/css\">\n";
			} else if ( !empty( $var['content'] ) )	{
				$head.="\t<style type=\"text/css\">\n";
				
				$tmp = explode( "\n", $var['content'] );
				
				foreach ( $tmp as $v ) {
					$head .= "\t\t{$v}\n";
				}
				
				$head .= "\t</style>\n";
			}
		}
	
		if ( isset( $this->config['js'] ) && is_array( $this->config['js'] ) ) {
		    foreach ( $this->config['js'] as $key => $val ) {
				$head.="\t<script type='text/javascript' src=\"{$val}\"></script>\n";
			}
		}
		
		foreach( $this->javascript as $key => $var) {
			if ( !empty( $var['file'] ) ) {
				$head .= "\t<script type='text/javascript' src=\"/{$var['file']}\"></script>\n";
			} else if ( !empty( $var['content'] ) ) {
				$head .= "\t<script type=\"text/javascript\">\n\t<!--\n";
				
				$tmp = explode("\n", $var['content'] );
				
				foreach ( $tmp as $v ) {
					$head .= "\t\t{$v}\n";
				}
				
				$head.="\t//-->\n\t</style>\n";
			}
		}
		
		if ( isset( $this->config['customhead'] ) && is_array( $this->config['customhead'] ) ) {
			foreach( $this->config['customhead'] as $key => $var)
			{
				$head .= "\n{$var}\n";
			}
		}
		
		$head .= "\n</head>\n<body";
		if ( isset( $this->config['body'] ) && is_array( $this->config['body'] ) ) {
			foreach( $this->config['body'] as $key => $var)
			{
				$head .= "\n\t{$key}=\"{$var}\"";
			}
		}
		$head .= ">\n\n";
	
		return $head;
	}
	
	public function debug( $data ) {
		$this->add( 'inline', '<div class="debug-output">'.str_replace('<', '&lt;', print_r($data, true)).'</div>' );
	}

    public static function closeTags( $html, $tags ) {
    	$excluded = ['br','img','hr','meta','link'];
    	
    	foreach( $tags as $tag ) {
    		if ( in_array( $tag, $excluded ) ) continue;

    		$start = '#\<' . $tag . '.*>#isU';
    		$stop = '#\</' . $tag . '.*>#isU';
    		$match = [];

    		preg_match_all( $start, $html, $match );
    		$cnt = count( $match[0] );
    		preg_match_all( $stop, $html, $match );
    		$cnt -= count( $match[0] );

    		for( $i = 0; $i < $cnt; $i++ ) {
    			$html .= "</{$tag}>";
    		}
    	}

    	return $html;
    }
}