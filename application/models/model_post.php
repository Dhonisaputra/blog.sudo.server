<?php
/**
* 
*/
class Model_post extends CI_Model
{
	
	public function __construct()
	{
		parent::__construct();
	}
	public function get_post($select='*', $where, $db)
	{
		$db->select($select, false);
		$db->from('posts');
		$db->join('post_categories', 'post_categories.id_post = posts.id_post');
		$db->join('categories', 'post_categories.id_category = categories.id_category');
		if(isset($where) && ( (is_array($where) && count($where) > 0) || (is_string($where) && $where != '') ) )
		{
			$db->where($where);
		}
		$db->group_by('posts.id_post');
		return $db->get();
	}

	public function insert_post($data,$db)
	{
		$db->insert('posts', $data);
		return $this->db;
	}

	public function insert_category($data,$db)
	{
		$db->insert('categories', $data);
		return $this->db;
	}

	public function remove_posts($table, $where, $db)
	{
		$db->delete('posts', $where); 
	}
	public function update_post($update, $where, $db){

		$db->where($where);
		$db->update('posts', $update); 

	}

	public function get_post_categories($where = array(), $db)
	{
		$db->select('*');
		$db->from('post_categories');
		$db->join('categories', 'post_categories.id_category = categories.id_category');
		if(is_array($where) && count($where) > 0)
		{
			$db->where($where);
		}
		return $db->get();
	}
	public function get_categories($select='*', $where = array(), $db)
	{
		$db->select('*');
		$db->from('categories');
		if(is_array($where) && count($where) > 0)
		{
			$db->where($where);
		}
		return $db->get();
	}
	public function remove_post_categories($where, $db)
	{
		$db->where($where);
		$db->delete('post_categories'); 

	}
}