<?php

/**
* 
*/
class Files_model extends CI_Model
{
	
	function __construct()
	{
		parent::__construct();
		# code...
	}

	/*
	|---------------------
	| Get Data files
	|---------------------
	*/
	public function data_files($select = '*', $where = array())
	{
		/*$this->db->select($select);
		$this->db->from('master_files');
		if(count($where) > 0)
		{
			$this->db->where($where);
		}
		$data = $this->db->get();
		return $data;*/
	}

	public function get_file($file_id = 0)
	{
		/*$query = 'SELECT * FROM master_files ';
		if($file_id > 0)
		{
			$query .= 'where file_id = ?';
			return $this->dataakses->SQL($query, 'i', $file_id);
		}elseif($file_id == 0)
		{
			return $this->dataakses->SQL($query);
		}*/
	}
	public function remove_files($where)
	{
		$this->db->delete('master_files', $where); 
	}

	public function save_file($data)
	{
		$this->db->query('INSERT INTO master_files(file_name, file_type, file_path, raw_name, original_name, client_name, file_ext, file_size) values(?,?,?,?,?,?,?,?)',  array($data['file_name'], $data['file_type'], $data['file_path'], $data['raw_name'], $data['orig_name'], $data['client_name'], $data['file_ext'], $data['file_size']));
		return $this->db->insert_id();
	}

	public function upload($config = array(), $files)
	{
		$file_uploaded = array();
		$this->load->library('upload');

		foreach ($files as $key => $value) {
			
			$this->upload->initialize($config);

			if ( ! $this->upload->do_upload($key))
			{
				$error = array('error' => $this->upload->display_errors());
				echo header('http/1.0 '.$error.' 500');
			}
			else
			{
				$file = $this->upload->data();
				$insert_id = $this->save_file($file);
				$file['id_files'] = $insert_id;
				array_push($file_uploaded, $file);
			}
		}

		return $file_uploaded;
	}
}