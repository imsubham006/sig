<?

class WordpressPage_Node extends Folder_Node {
   var $struct = array(
                       "node_type" => "WordpressPage_Node",
                       "title"     => array(
                                            "prop_type" => "callback",
                                            "name"    => "set_title"),
                       "page_id"   => array(
                                            "prop_type" => "callback",
                                            "name"    => "set_page_id"),
                       "import_nodes"     => array(
                                            "prop_type" => "callback",
                                            "name" => "import_nodes"
                                           )
                       );

   var $allowed_children_types = array ();

   static function insert_id ($id = NULL)
   {
      static $insert_id;

      if (!isset($insert_id) && $id != NULL) {
         $insert_id = $id;
      }

      return $insert_id;
   }

   function create ($nodeValues, $newStruct)
   {
      $query = sprintf(
           "INSERT INTO `wp_posts` "
         . "( `ID` , `post_author` , `post_date` , `post_date_gmt` , "
         . "`post_content` , `post_title` , `post_category` , `post_excerpt` , "
         . "`post_status` , `comment_status` , `ping_status` , `post_password` , "
         . "`post_name` , `to_ping` , `pinged` , `post_modified` , `post_modified_gmt` , "
         . "`post_content_filtered` , `post_parent` , `guid` , `menu_order` ) "
         . "VALUES "
         . "('', '1', 'NOW()', '', "
         . "'', '', '0', '', "
         . "'static', 'open', 'open', '', "
         . "'', '', '', 'NOW()', '', "
         . "'', '0', '', '0');"
      );

      $result = new Query($query);
      self::insert_id($result->insertid);

      //add_post_meta(self::insert_id(), 'SiG Meta Tag', 'This Page was created by the SiG Plugin, do not edit content', true);
      //add_post_meta(self::insert_id(), '_wp_page_template', 'sig-page-template.php', true);

      return parent::create ($nodeValues, $newStruct);
   }

   function delete ()
   {
      $query = sprintf("DELETE FROM wp_posts WHERE ID='%d'",
                       mysql_real_escape_string($this->page_id->value));

      $results = new Query($query);
      return parent::delete();
   }

   function set_page_id ($name, $value)
   {
      if ($this->new) {
         $value = self::insert_id();
         $this->$name->set_value($value, $this->new);
      }

      $p = new Tag('p');
      if ($value) {
         $p->AddElement($value);
      } else {
         $p->AddElement('Page Id is autogenerated upon creation!');
      }

      return $p;
   }


   function set_title ($name, $value)
   {
      if ($this->id) {
         if ($this->new) {
            $page_id = self::insert_id();
         } else {
            $page_id = $this->page_id->value;
         }

         //$query = sprintf("UPDATE `wp_posts` SET post_title='%s', post_name='%s' WHERE ID='%d'",
         //                 mysql_real_escape_string($value),
         //                 mysql_real_escape_string($value),
         //                 mysql_real_escape_string($page_id));
         //$result = new Query($query);
      }

      $inputElement = new Tag('input', array('type'=>'text', 'name'=>'struct['.$name.']', 'value'=>$value));

      return $inputElement;

   }

   function import_nodes ($name, $value)
   {
      if ($this->id) {
         $div = new Tag('div');
         $childrenIds = array();

         if ($this->new) {
            $page_id = self::insert_id();
         } else {
            $page_id = $this->page_id->value;
         }
         //$query = sprintf("UPDATE `wp_posts` SET post_content='%s' WHERE ID='%d'",
         //                 mysql_real_escape_string(serialize($this->id)),
         //                 mysql_real_escape_string($page_id));
         //$results = new Query($query);

         $importedNodesElement = new Tag('select', array('name'=>'nodes_to_detach[]', 'multiple'=>'multiple', 'size'=>'10'));
         foreach ($this->get_array_of_children() as $child) {
            $option = new Tag('option', array('value'=>$child->id));
            $option->AddElement($child->title->value);
            $importedNodesElement->AddElement($option);
            $childrenIds[] = $child->id;
         }

         $div = new Tag('div');
         $selectElement = new Tag('select', array('name'=>'nodes_to_attach[]', 'multiple'=>'multiple', 'size'=>'10'));
         $rootNode = new Node(0);
         $rootNode->RecursiveOptionElement(array(), $childrenIds, $selectElement, 0, 
                                           array('Thread_Node', 
                                                 'Vote_Node', 
                                                 'WordpressPageSystem_Node', 
                                                 'DeleteSystem_Node'));
         $attachButton = new Tag('input', array('type'=>'submit', 'name'=>'action', 'value'=>'Attach'));
         $detachButton = new Tag('input', array('type'=>'submit', 'name'=>'action', 'value'=>'Detach'));
         $div->AddElement($selectElement);
         $div->AddElement($attachButton);
         $div->AddElement($detachButton);
         $div->AddElement($importedNodesElement);
         return $div;
      } else {
         $p = new Tag('p');
         $p->AddElement('Create Page before importing nodes');
         return $p;
      }
   }

   function doAttach ($container)
   {
      $nodesToAttach = SiG_Session::Instance()->Request('nodes_to_attach', NULL);
      if ($nodesToAttach) {
         foreach ($nodesToAttach as $nodeId) {
            $node = Node::new_instance($nodeId);
            $node->new = TRUE; //$this->new;
            $node->AttachTo($this->id);
         }
      }

      $this->doEdit($container);
   }

   function doDetach ($container)
   {
      $nodesToDetach = SiG_Session::Instance()->Request('nodes_to_detach', NULL);
      if ($nodesToDetach) {
         foreach ($nodesToDetach as $nodeId) {
            $node = Node::new_instance($nodeId);
            $node->new = TRUE; //$this->new;
            $node->DetachFrom($this->id);
         }
      }

      $this->doEdit($container);
   }

   function SetOrderBy ($parentId, $orderBy)
   {
      parent::SetOrderBy($parentId, $orderBy);
      $query = sprintf("UPDATE `wp_posts` SET menu_order='%d' WHERE ID='%d'",
                          mysql_real_escape_string($orderBy),
                          mysql_real_escape_string($this->page_id->value));
      $results = new Query($query);
   }

   function DefaultHtmlData ($container, $parentNode = NULL, $activeNode = NULL)
   {
      foreach ($this->get_array_of_children() as $child) {
         if ($activeNode->IsAncestor($child->id)) {
            $activeNode->ActiveHtmlData($container, $child);
         } else {
            $child->DefaultHtmlData($container, $this, $activeNode);
         }
      }
   }
}

?>
