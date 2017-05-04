<?php

class Authentication extends CI_Controller
{

	function __construct()
	{
		
		parent::__construct();
		require_once(APPPATH.'libraries/profiling/Pengguna.php');
		$this->auth = new Pengguna;
	}

	public function token()
	{

		$post = $this->input->post();
		$where = array('token' => $post['token']);
		$token = $this->db->get_where('generated_token', $where);
		$blogs = $this->db->get_where('blogs', array('blog_key' => $post['key']));
		if(count($token->result_array()) > 0 && count($blogs->result_array()) > 0)
		{
			$token = $token->row_array();
			$blogs = $blogs->row_array();
			$result = $this->auth->decrypt($post['token'], $blogs['blog_key_B'], $token['api_key'], true);
			echo json_encode($result);
		}else
		{
			echo json_encode(array('code' => 404, 'message' => 'unindentified token!'));
		}
	}

}
