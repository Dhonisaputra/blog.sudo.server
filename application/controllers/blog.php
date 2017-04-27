<?php

class Blog extends CI_Controller
{

	function __construct()
	{
		
		parent::__construct();
		$this->load->model('blog_model');
		$this->load->model('owner_model');
		$this->load->model('authentication');
		$this->isAjax = $this->input->is_ajax_request();
		$post = $this->input->post();
		$this->authentication->do_authorize($post);
		require_once(APPPATH.'libraries/profiling/Pengguna.php');
		$this->auth = new Pengguna;
		
	}

	public function create_blog()
	{
		$this->authentication->must_ajax_call();
		$post = $this->input->post();
		$post['owner_id'] = $this->authentication->authorize['owner_id'];

		$key = $this->auth->handshakeKey();
		$this->db->insert('blogs', array(
				'blog_name' => $post['data']['blog']['blog_name'],
				'blog_description' => $post['data']['blog']['blog_description'],
				'blog_key' => '',
				'blog_key_A' => $key['key_A'],
				'blog_key_B' => $key['key_B'],
				'blog_owner' => $post['owner_id'],
				'sudo_server' => base_url(),
			)
		);
		$insert_id = $this->db->insert_id();
		$blog_key = $this->auth->password_hash(
			array(
				'password' => $post['owner_id'].$insert_id,
				'hash_options' => array()
				)
		)['raw_password'];
		$this->blog_model->update_blog(array('blog_key' => $blog_key), array('blog_id' => $insert_id));
		echo json_encode(array(
				'blog_owner' => $post['owner_id'],
				'blog_id' => $insert_id
			)
		);
	}

	public function get_blog()
	{
		$this->authentication->must_ajax_call();
		$post = $this->input->post();
		$post['where'] = isset($post['where'])? $post['where'] : array();
		$where = array_merge(array('blog_owner' => $this->authentication->authorize['owner_id']), $post['where']);
		$data = $this->blog_model->get_blog('*', $where)->result_array();
		echo json_encode($data);
	}

	public function setting_db()
	{
		$get = $this->input->get();
		$token = $get['token'];		
		$time = $get['time'];		
		$owner_id = $get['id'];	

		$where = array('blog_key' => $token, 'owner_id' => $owner_id);
		$data = $this->blog_model->get_blog('*', $where)->result_array();

		if(count($data) > 0)
		{
			$this->blog_model->update_blog(array('processing_server' => urldecode($get['u'])), $where);
			echo json_encode(array(
				'code' => 200
				)
			);
		}else
		{
			echo json_encode(array(
				'code' => 500
				)
			);
		}
	}

	public function component_download_json_client()
	{
		$token = $this->input->get('token');
		$where = array('blog_key' => $token);
		$data = $this->blog_model->get_blog('*', $where);
		if(count($data->result_array()) > 0)
		{
			$data = $data->row_array();
			$owner = $this->owner_model->get_owner('*', array('owner_id' => $data['blog_owner'] ) )->row_array();
			
			$json = array(
					"owner_key" 	=> $owner['owner_key'],
					"blog_key" 	=> $data['blog_key'],
					"double_server" => $data['double_server'] == 0? false : true,
					"web_url" 		=> $data['web_url'],
					"processing_server" => $data['processing_server'],
					"handling_server" 	=> $data['handling_server'],
					"trends" 			=> $data['trends'],
				);
			header('Content-disposition: attachment; filename=configuration.json');
			header('Content-type: application/json');
			echo json_encode($json);
		}
	}

	public function component_download_sql_client()
	{
		$this->load->helper('download');
		$token = $this->input->get('token');
		$where = array('blog_key' => $token);
		$data = $this->blog_model->get_blog('*', $where);
		if(count($data->result_array()) > 0)
		{
			$data = $data->row_array();
			$owner = $this->owner_model->get_owner('*', array('owner_id' => $data['blog_owner'] ) )->row_array();
			
			$file = file_get_contents(base_url('locker/sql/default.sql')); // Read the file's contents
			$name = $owner['owner_key'].$data['blog_id'].'.sql';

			force_download($name, $file);
			/*header('Content-disposition: attachment; filename='.$owner['owner_key'].$data['blog_id'].'.sql');
			echo json_encode($sql);*/
		}
	}

}