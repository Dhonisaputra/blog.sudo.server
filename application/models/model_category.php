<?php
/**
* 
*/
class Model_category extends CI_Model
{
	
	public function __construct()
	{
		parent::__construct();
	}
	public function get_category($select='*', $where)
	{
		$this->db->select($select);
		$this->db->from('categories');
		if(isset($where) && (is_array($where) || is_string($where)) )
		{
			$this->db->where($where);
		}
		return $this->db->get();
	}

	public function insert_category($data)
	{
		$this->db->insert('categories', $data);
		return $this->db;
	}

	public function remove_category($table, $where)
	{
		$this->db->delete('categories', $where); 
	}
}