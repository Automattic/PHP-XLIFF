<?php
/**
 * PHP XLIFF Parser
 * https://github.com/automattic/PHP-XLIFF
 *
 * A PHP library designed to help with reading and writing XLIFF documents. For more information on the current
 * (as of April 2005) XLIFF specification, see:
 *
 * http://docs.oasis-open.org/xliff/xliff-core/v2.0/os/xliff-core-v2.0-os.html
 *
 * <3,
 * Automattic
 * http://automattic.com
 */

/*
	The MIT License (MIT)

	Copyright (c) 2015 Automattic, Inc

	Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated
	documentation files (the "Software"), to deal in the Software without restriction, including without limitation
	the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software,
	and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

	The above copyright notice and this permission notice shall be included in all copies or substantial portions
	of the Software.

	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING
	BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
	NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
	DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
	OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

/**
 * Parent class for nodes in the XLIFF document
 */
class Xliff_Node{

	static protected $tag_name_to_class_mapping = array(
		'xliff'         => 'Xliff_Document',
		'file'          => 'Xliff_File',
		'skeleton'      => 'Xliff_Skeleton',
		'group'         => 'Xliff_Group',
		'unit'          => 'Xliff_Unit',
		'segment'       => 'Xliff_Segment',
		'ignorable'     => 'Xliff_Ignorable',
		'notes'         => 'Xliff_Notes',
		'note'          => 'Xliff_Note',
		'originalData'  => 'Xliff_originalData',
		'data'          => 'Xliff_Data',
		'source'        => 'Xliff_Node',
		'target'        => 'Xliff_Node',
	);


	/**
	 * Holds element's attributes
	 * @var Array
	 */
	protected $attributes = array();

	/**
	 * Holds child nodes that can be repeated inside this node.
	 * For example, an xliff document can have multiple "file" nodes
	 * @var Array[tag-name][0..n]=XliffNode
	 */
	protected $containers = array();

	/**
	 * Indicate which child nodes are supported
	 * @var Array[tag-name]=>Xliff Class
	 */
	protected $supported_containers = array();

	/**
	 * Holds child nodes that can be presented only once inside this node.
	 * For example, "trans-unit" element can have only one "source" node
	 * @var Array[tag-name]=XliffNode
	 */
	protected $leaf_nodes = array();

	/**
	 * Indicate which child nodes are supported
	 * @var Array[tag-name]=>Xliff Class
	 */
	protected $supported_leaf_nodes = array();

	/**
	 * Node's text, NULL if none
	 * @var String|NULL
	 */
	protected $text_content;

	/**
	 * Node's tag name
	 * @var string
	 */
	protected $tag_name = '';

	function __construct( $tag_name = null ) {
		if ( is_string( $tag_name ) ) {
			$this->set_tag_name( $tag_name );
		}

		foreach( $this->supported_containers as $name => $class ) {
			$this->containers[$name] = array();
		}
	}

	/**
	 * @return string
	 */
	public function get_tag_name() {
		return isset( $this->tag_name ) ? $this->tag_name : false;
	}

	/**
	 * @param string $tag_name
	 * @return XliffNode
	 */
	public function set_tag_name( $tag_name ) {
		$this->tag_name = $tag_name;
		return $this;
	}


	/**
	 * Returns the attribute value, FALSE if attribute missing
	 * @param string $name
	 * @return Ambigous <boolean, string> -
	 */
	function get_attribute( $attribute_name ) {
		return isset( $this->attributes[$attribute_name] ) ? $this->attributes[$attribute_name] : false;
	}

	/**
	 * Sets an attribute
	 * @param string $name
	 * @param string $value
	 * @throws Exception
	 * @return XliffNode
	 */
	function set_attribute( $attribute_name, $value ) {
		if ( ! is_string( $value ) && ! is_numeric( $value ) ) {
			throw new Exception( 'Attribute must be text!' );
		}
		$this->attributes[$attribute_name] = trim( (string) $value );
		return $this;
	}

	/**
	 * Set multiple attributes from a key=>value array
	 * @param Array $attr_array
	 * @return XliffNode
	 */
	function set_attributes( $attribute_array ) {
		foreach( $attribute_array as $key => $value ) {
			$this->set_attribute( $key, $value );
		}
		return $this;
	}

	/**
	 * @return Ambigous <string, NULL>
	 */
	public function get_text_content() {
		return isset( $this->text_content ) ? $this->text_content : false;
	}

	/**
	 * @param string $textContent
	 * @return XliffNode
	 */
	public function set_text_content( $text_content ) {
		$this->text_content = $text_content;
		return $this;
	}

	/**
	 * Append a new node to this element
	 * @param XliffNode $node - node to append
	 * @return XliffNode - this node
	 */
	public function append_node( Xliff_Node $node ) {
		$tag_name = $node->get_tag_name();
		if ( isset( $this->supported_containers[$tag_name] ) ) {
			$this->containers[$tag_name][] = $node;
		} elseif ( isset( $this->supported_leaf_nodes[$tag_name] ) ) {
			$this->leaf_nodes[$tag_name] = $node;
		} else {
			return false;
		}
		return $this;
	}



	/**
	 * Allow calling $node->tag_name($new=FALSE)
	 * Supports the following methods:
	 *
	 * 1. $node->tag_name(TRUE) - create a new node for "tag_name" and return the new node
	 * 2. $node->tag_name() - fetch the last added node for "tag_name", FALSE if none
	 *
	 * //On the following, notice that tag names are in plural formation...
	 * 3. $node->tag_names() - return an array of tag_name nodes
	 */
	function __call( $tag_name, $args ) {
		$append = is_array( $args ) && $args[0] === true ? true : false;

		if ( ! empty( $this->supported_containers[$tag_name] ) ) {
			if ( $append ) {
				$class = $this->supported_containers[$tag_name];
				$this->containers[$tag_name][] = new $class( $tag_name );
			}
			return end( $this->containers[$tag_name] );
		} elseif ( ! empty( $this->supported_leaf_nodes[$tag_name] ) ) {
			if ($append){
				$class = $this->supported_leaf_nodes[$tag_name];
				$this->leaf_nodes[$tag_name] = new $class();
				$this->leaf_nodes[$tag_name]->set_tag_name( $tag_name );
			}
			return ! empty( $this->leaf_nodes[$tag_name] ) ? $this->leaf_nodes[$tag_name] : false;
		}
		throw new Exception( get_class( $this ) . ' does not support ' . $tag_name . ' elements.' );
	}

	/**
	 * Export this node to a DOM object
	 * @param DOMDocument $doc - parent DOMDocument must be provided
	 * @return DOMElement
	 */
	function to_DOM_element( DOMDocument $dom ){
		$element = $dom->createElement( $this->get_tag_name() );

		// set attributes on the new element
		foreach ( $this->attributes as $name => $value ) {
			$element->setAttribute( $name, $value );
		}

		// build its immediate (leaf) children
		foreach ( $this->leaf_nodes as $node ) {
			$element->appendChild( $node->to_DOM_element( $dom ) );
		}

		// build tree structures below
		foreach ( $this->containers as $container ){
			foreach ( $container as $node ) {
				$element->appendChild( $node->to_DOM_element( $dom ) );
			}
		}

		// insert any text content into the element
		$text = $this->get_text_content();
		if ( is_string( $text ) ) {
			$text_node = $dom->createTextNode( $text );
			$element->appendChild( $text_node );
		}

		return $element;
	}

	/**
	 * Convert DOM element to Xliff_Node structure
	 * @param DOMNode $element
	 * @throws Exception
	 * @return string|Xliff_Node
	 */
	public static function from_DOM_element( DOMNode $element ){
		if ( $element instanceof DOMText ){
			return $element->nodeValue;
		}

		// check if tag is supported
		if ( static::$tag_name_to_class_mapping[$element->tagName] ) {
			$class = static::$tag_name_to_class_mapping[$element->tagName];
		} else {
			$class = 'Xliff_Node';
		}

		$xliff_node = new $class( $element->tagName );

		// import attributes
		foreach ( $element->attributes as $attrNode ) {
			$xliff_node->set_attribute( $attrNode->nodeName, $attrNode->nodeValue );
		}

		// continue to nested nodes
		foreach ( $element->childNodes as $child ) {
			$result = self::from_DOM_Element( $child );
			if ( is_string( $result ) ) {
				$xliff_node->set_text_content( $result );
			} else {
				$xliff_node->append_node( $result );
			}
		}

		return $xliff_node;
	}
}

/**
 * Wrapper class for Xliff documents.
 * Externally, you'll want to use this class.
 */
class Xliff_Document extends Xliff_Node {
	const XMLNS = 'urn:oasis:names:tc:xliff:document:';
	const XLIFF_VER = '2.0';

	protected $tag_name = 'xliff';
	protected $version;
	protected $srcLang;
	protected $trgLang;
	protected $supported_containers = array(
		'file' => 'Xliff_File'
	);


	function __construct(){
		parent::__construct();
		$this->version = self::XLIFF_VER;
	}

	public function set_source_locale( $locale ) {
		$this->srcLang = $locale;
		return $this;
	}

	public function set_target_locale( $locale ) {
		$this->trgLang = $locale;
		return $this;
	}

	/**
	 * Convert in-memory XLIFF representation to DOMDocument
	 * @return DOMDocument
	 */
	public function to_DOM(){
		$dom = new DOMDocument();
		$dom->formatOutput = true;

		// create the root xliff element w/all children
		$xliff_dom_element = $this->to_DOM_element( $dom );

		// set some attributes on the xliff element
		$xliff_dom_element->setAttribute( 'xmlns', self::XMLNS . $this->version );
		$xliff_dom_element->setAttribute( 'version', $this->version );
		$xliff_dom_element->setAttribute( 'srcLang', $this->srcLang );
		$xliff_dom_element->setAttribute( 'trgLang', $this->trgLang );

		// append the whole enchilada to the DOM
		$dom->appendChild( $xliff_dom_element );

		return $dom;

	}

	/**
	 * Build in-memory XLIFF representation from DOMDocument
	 *
	 * @param DOMDocument $dom
	 * @throws Exception
	 * @return Xliff_Document
	 */
	public static function from_DOM( DOMDocument $dom ) {
		if ( ! isset( $dom->documentElement ) || $dom->documentElement->tagName !== 'xliff' ) {
			throw new Exception( "Not an XLIFF document" );
		}
		return static::from_DOM_element( $dom->documentElement );
	}
}


/**
 * Concrete class for file tag
 */
class Xliff_File extends Xliff_Node {
	protected $tag_name = 'file';
	protected $supported_containers = array(
		'notes'     => 'Xliff_Notes',
		'unit'      => 'Xliff_Unit',
		'group'     => 'Xliff_Group',
	);
	protected $supported_leaf_nodes = array(
		'skeleton'  => 'Xliff_Skeleton',
	);
}

/**
 * Concrete class for skeleton tag
 */
class Xliff_Skeleton extends Xliff_Node {
	protected $tag_name = 'skeleton';
}

/**
 * Concrete class for Notes tag
 */
class Xliff_Notes extends Xliff_Node {
	protected $tag_name = 'body';
	protected $supported_containers = array(
		'note'	=> 'Xliff_Note',
	);
}

/**
 * Concrete class for note tag
 */
class Xliff_Note extends Xliff_Node {
	protected $tag_name = 'note';
}

/**
 * Concrete class for group tag
 */
class Xliff_Group extends Xliff_Node {
	protected $tag_name = 'group';
	protected $supported_containers = array(
		'notes'  => 'Xliff_Notes',
		'group'  => 'Xliff_Group',
		'unit'   => 'Xliff_Unit',
	);
}

/**
 * Concrete class for unit tag
 */
class Xliff_Unit extends Xliff_Node {
	protected $tag_name = 'unit';
	protected $supported_containers = array(
		'notes'         => 'Xliff_Notes',
		'originalData'  => 'Xliff_originalData',
		'segment'       => 'Xliff_Segment',
		'ignorable'     => 'Xliff_Ignorable',
	);
}

/**
 * Concrete class for segment tag
 */
class Xliff_Segment extends Xliff_Node {
	protected $tag_name = 'segment';
	protected $supported_leaf_nodes = array(
		'source'   => 'Xliff_Node',
		'target'   => 'Xliff_Node',
	);
}

/**
 * Concrete class for ignorable tag
 */
class Xliff_Ignorable extends Xliff_Node {
	protected $tag_name = 'ignorable';
	protected $supported_leaf_nodes = array(
		'source'   => 'Xliff_Node',
		'target'   => 'Xliff_Node',
	);
}

/**
 * Concrete class for originalData tag
 */
class Xliff_originalData extends Xliff_Node {
	protected $tag_name = 'originalData';
	protected $supported_leaf_nodes = array(
		'data'   => 'Xliff_Data',
	);
}

/**
 * Concrete class for data tag
 */
class Xliff_Data extends Xliff_Node {
	protected $tag_name = 'data';
}