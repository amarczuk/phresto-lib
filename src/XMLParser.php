<?php

namespace Phresto;

class XMLParser {

    public $result = array();
    public $xml = '';

    public function Parse($url, $out = 'UTF-8', $get_attr = 1) {

        if ($content = file_get_contents($url)) {
            $this->result = $this->xml2array($content, $get_attr);

            if ($out!='UTF-8') {
                $this->result = $this->Conv($out, $this->result);
            }

            return $this->result;

        } else {
            return false;
        }
     }

    /**
     * xml2array() will convert the given XML text to an array in the XML structure.
     * Link: http://www.bin-co.com/php/scripts/xml2array/
     * Arguments : $contents - The XML text
     *                $get_attributes - 1 or 0. If this is 1 the function will get the attributes as well as the tag values - this results in a different array structure in the return value.
     *                $priority - Can be 'tag' or 'attribute'. This will change the way the resulting array sturcture. For 'tag', the tags are given more importance.
     * Return: The parsed XML in an array form. Use print_r() to see the resulting array structure.
     * Examples: $array =  xml2array(file_get_contents('feed.xml'));
     *              $array =  xml2array(file_get_contents('feed.xml', 1, 'attribute'));
     */

    private function xml2array($contents, $get_attributes = 1, $priority = 'tag')
    {
        if(!$contents) return array();

        if(!function_exists('xml_parser_create'))
        {
            return array();
        }

        //Get the XML parser of PHP - PHP must have this module for the parser to work
        $parser = xml_parser_create('');
        xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, "UTF-8"); # http://minutillo.com/steve/weblog/2004/6/17/php-xml-and-character-encodings-a-tale-of-sadness-rage-and-data-loss
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
        xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
        xml_parse_into_struct($parser, trim($contents), $xml_values);
        xml_parser_free($parser);

        if(!$xml_values) return;//Hmm...

        //Initializations
        $xml_array = array();
        $parents = array();
        $opened_tags = array();
        $arr = array();

        $current = &$xml_array; //Refference

        //Go through the tags.
        $repeated_tag_index = array();//Multiple tags with same name will be turned into an array
        foreach($xml_values as $data) {
            unset($attributes,$value);//Remove existing values, or there will be trouble

            //This command will extract these variables into the foreach scope
            // tag(string), type(string), level(int), attributes(array).
            extract($data);//We could use the array by itself, but this cooler.

            $result = array();
            $attributes_data = array();

            if(isset($value)) {
                if($priority == 'tag') $result = $value;
                else $result['value'] = $value; //Put the value in a assoc array if we are in the 'Attribute' mode
            }

            //Set the attributes too.
            if(isset($attributes) and $get_attributes) {
                foreach($attributes as $attr => $val) {
                    if($priority == 'tag') $attributes_data[$attr] = $val;
                    else $result['attr'][$attr] = $val; //Set all the attributes in a array called 'attr'
                }
            }

            //See tag status and do the needed.
            if($type == "open") {//The starting of the tag '<tag>'
                $parent[$level-1] = &$current;
                if(!is_array($current) or (!in_array($tag, array_keys($current)))) { //Insert New tag
                    $current[$tag] = $result;
                    if($attributes_data) $current[$tag. '_attr'] = $attributes_data;
                    $repeated_tag_index[$tag.'_'.$level] = 1;

                    $current = &$current[$tag];

                } else { //There was another element with the same tag name

                    if(isset($current[$tag][0])) {//If there is a 0th element it is already an array
                        $current[$tag][$repeated_tag_index[$tag.'_'.$level]] = $result;
                        $repeated_tag_index[$tag.'_'.$level]++;
                    } else {//This section will make the value an array if multiple tags with the same name appear together
                        $current[$tag] = array($current[$tag],$result);//This will combine the existing item and the new item together to make an array
                        $repeated_tag_index[$tag.'_'.$level] = 2;

                        if(isset($current[$tag.'_attr'])) { //The attribute of the last(0th) tag must be moved as well
                            $current[$tag]['0_attr'] = $current[$tag.'_attr'];
                            unset($current[$tag.'_attr']);
                        }

                    }
                    $last_item_index = $repeated_tag_index[$tag.'_'.$level]-1;
                    $current = &$current[$tag][$last_item_index];
                }

            } elseif($type == "complete") { //Tags that ends in 1 line '<tag />'
                //See if the key is already taken.
                if(!isset($current[$tag])) { //New Key
                    $current[$tag] = $result;
                    $repeated_tag_index[$tag.'_'.$level] = 1;
                    if($priority == 'tag' and $attributes_data) $current[$tag. '_attr'] = $attributes_data;

                } else { //If taken, put all things inside a list(array)
                    if(isset($current[$tag][0]) and is_array($current[$tag])) {//If it is already an array...

                        // ...push the new element into that array.
                        $current[$tag][$repeated_tag_index[$tag.'_'.$level]] = $result;

                        if($priority == 'tag' and $get_attributes and $attributes_data) {
                            $current[$tag][$repeated_tag_index[$tag.'_'.$level] . '_attr'] = $attributes_data;
                        }
                        $repeated_tag_index[$tag.'_'.$level]++;

                    } else { //If it is not an array...
                        $current[$tag] = array($current[$tag],$result); //...Make it an array using using the existing value and the new value
                        $repeated_tag_index[$tag.'_'.$level] = 1;
                        if($priority == 'tag' and $get_attributes) {
                            if(isset($current[$tag.'_attr'])) { //The attribute of the last(0th) tag must be moved as well

                                $current[$tag]['0_attr'] = $current[$tag.'_attr'];
                                unset($current[$tag.'_attr']);
                            }

                            if($attributes_data) {
                                $current[$tag][$repeated_tag_index[$tag.'_'.$level] . '_attr'] = $attributes_data;
                            }
                        }
                        $repeated_tag_index[$tag.'_'.$level]++; //0 and 1 index is already taken
                    }
                }

            } elseif($type == 'close') { //End of tag '</tag>'
                $current = &$parent[$level-1];
            }
        }

        return($xml_array);
    }


    private function Conv($out, $arr, $in = 'utf-8')
    {

        if (!is_array($arr))
        {
            return iconv($in, $out, $arr);
        }

        foreach ($arr as $key=>$var)
        {
         $arr[$key]=$this->Conv($out, $var, $in);
        }

        return $arr;

    }

    /**
    * creating xml content from array
    *
    * array {
    *   [rss] = array {
    *       [version] => '2.0',
    *       [0] => array {
    *           [title] => 'title',
    *           [link] => 'http://sample.com'
    *       },
    *       [1] => array {
    *           [channel] => array {
    *               [type] => 'channel',
    *               [0] => array {
    *                   [item] => 'item';
    *               }
    *           }
    *       }
    *   }
    * }
    *
    * create:
    *
    * <?xml version="1.0" encoding="utf-8"?>
    * <rss version = "2.0">
    *     <title>title</title>
    *     <link>http://sample.com</link>
    *     <channel>
    *         <item type = "channel">item</item>
    *     </channel>
    * </rss>
    *
    *
    * @param array $data
    * @param string $encoding
    * @param string $xml_ver
    */
    public function array2xml($data, $encoding = 'utf-8', $xml_ver = '1.0')
    {
        if (!is_array($data)) $data = array();

        $this->xml = '<?xml version="'.$xml_ver.'" encoding="'.$encoding.'"?>';

        if (count($data) == 0)
        {
            $this->xml .= "\n<empty></empty>\n";
            return $this->xml;
        }

        $this->xml .= $this->getTag($data, 0, $encoding);

        return $this->xml;

    }


    private function getTag($data, $level, $encoding = 'utf-8')
    {
        $xml = "\n";
        for ($i = 0; $i < $level; $i++) $xml .= "\t";

        if (is_array($data))
        {

            foreach ($data as $key => $val)
            {
                $inner_xml = '';
                $value = '';
                $params = '';

                if (!is_array($val))
                {
                    $value = $val;

                }else
                {

                    foreach ($val as $key1 => $val1)
                    {
                        if (!is_numeric($key1))
                        {
                            $params .= ' '.$key1.' = "'.str_replace('"', "'", $val1).'"';
                        }else
                        {
                            $inner_xml .= $this->getTag($val1, $level + 1, $encoding);
                        }
                    }

                }

                if ($inner_xml != '')
                {
                    $inner_xml .= "\n";
                    for ($i = 0; $i < $level; $i++) $inner_xml .= "\t";
                }else
                {
                    $cd = false;
                    if (strpos($value, '<![CDATA[') !== false) $cd = true;

                    $inner_xml = str_replace(array('<![CDATA[', ']]>', '&nbsp;'), array('', '', ' '), $value);
                    if (!$cd) $inner_xml = @htmlspecialchars($inner_xml, ENT_QUOTES, $encoding, false);
                    if ($cd) $inner_xml = '<![CDATA['.$inner_xml.']]>';
                }

                $xml .= '<'.$key.$params.'>'.$inner_xml.'</'.$key.'>';
            }

        }else
        {
            if ($data != '')
            {
                $xml .= '<'.strip_tags($data).' />';
            }else
            {
                $xml = '';
            }
        }

        return $xml;

    }

}
