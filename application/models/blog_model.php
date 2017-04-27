<?php
/**
* 
*/
class Blog_model extends CI_Model
{
	
	public function __construct()
	{
		parent::__construct();
	}
	public function get_blog($select='*', $where)
	{
		$this->db->select($select, false);
		$this->db->from('blogs');
		if(isset($where) && ( (is_array($where) && count($where) > 0) || (is_string($where) && $where != '') ) )
		{
			$this->db->where($where);
		}
		return $this->db->get();
	}

	public function insert_post($data)
	{
		$this->db->insert('blogs', $data);
		return $this->db;
	}

	public function insert_category($data)
	{
		$this->db->insert('categories', $data);
		return $this->db;
	}

	public function remove_posts($table, $where)
	{
		$this->db->delete('blogs', $where); 
	}
	public function update_blog($update, $where){

		$this->db->where($where);
		$this->db->update('blogs', $update); 

	}

	public function get_post_categories($where = array())
	{
		$this->db->select('*');
		$this->db->from('post_categories');
		$this->db->join('categories', 'post_categories.id_category = categories.id_category');
		if(is_array($where) && count($where) > 0)
		{
			$this->db->where($where);
		}
		return $this->db->get();
	}
	public function get_categories($select='*', $where = array())
	{
		$this->db->select('*');
		$this->db->from('categories');
		if(is_array($where) && count($where) > 0)
		{
			$this->db->where($where);
		}
		return $this->db->get();
	}
	public function remove_post_categories($where)
	{
		$this->db->where($where);
		$this->db->delete('post_categories'); 

	}
}