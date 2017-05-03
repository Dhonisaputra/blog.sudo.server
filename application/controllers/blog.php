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
				'blog_description' => isset($post['data']['blog']['blog_description'])? $post['data']['blog']['blog_description'] : '',
				'blog_key' => '',
				'blog_key_A' => $key['key_A'],
				'blog_key_B' => $key['key_B'],
				'blog_owner' => $post['owner_id'],
				'remote_server' => base_url(),
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

		$where = array('blog_key' => $token, 'blog_owner' => $owner_id);
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

	public function component_json_client()
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
					"blog_server" 		=> $data['blog_server'],
					"processing_server" => rtrim($data['processing_server'], '/').'/',
					"handling_server" 	=> rtrim($data['handling_server'], '/').'/',
					"trends" 			=> $data['trends'],
				);
			
			echo json_encode($json);
		}else
		{
			header('http/1.0 500 error no blog found!');
		}
	}

	public function component_download_json_client()
	{
		header('Content-disposition: attachment; filename=configuration.json');
		header('Content-type: application/json');
		$this->component_json_client();
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

	public function save_blog_settings()
	{
		$this->load->library('curl');
		$post = $this->input->post();
		$post['settings']['remote_server'] = base_url();
		$post['settings']['version'] = '1.0';

		$settings = $post['settings'];
		
		$web = rtrim($settings['processing_server'], '/').'/';
		$ping = $web.'blog/ping?remote';
		$web .= 'install/process_save_settings_database?remote';

		$isUp = $this->curl->simple_post($ping);
		if($isUp !== FALSE || $isUp['code'] == 500)
		{
			$isUp = json_decode($isUp,true);
			$setDB = json_decode($this->curl->simple_post($web, $post['settings']),true);
			if($setDB == FALSE || $setDB['code'] == 500)
			{
				echo $setDB;
			}else
			{
				$this->blog_model->update_blog(
					array(
						'processing_server' 	=> $post['settings']['processing_server'],
						'blog_server' 			=> $post['settings']['blog_server'],
						'is_installed' 			=> 1,
						'processing_server_ip' 	=> $isUp['REMOTE_ADDR'],
						'remote_server' 		=> base_url(),
					),
					array('blog_key' => $post['settings']['blog_key']) 
				);
				echo json_encode(array('code'=>200));
			}
		}else
		{
			echo json_encode(array('code'=>500, 'message' => 'cant find GoBlog directory in '.$post['settings']['blog_server'].' please check your domain!'));
		}

	}
	public function uninstall()
	{
		$where = $this->input->post('where');
		$this->blog_model->update_blog(
			array(
				'is_installed' => 0,
			),
			$where
		);
		echo json_encode(array('code'=>200));
	}

	public function is_blog_available($u = '', $sys = FALSE)
	{
		$this->load->library('curl');
		$web = isset($_GET['u'])? $this->input->get('u') : $u;
		if($web == ''){
			if($this->isAjax)
			{
				header('http/1.0 500 Error. insufficient parameters');
			}else
			{
				show_error('insufficient parameters');
			}
			return false;
		}
		$web = rtrim($web, '/').'/';
		$web .= 'blog/ping';

		$isUp = $this->curl->simple_post($web);
		if($sys == TRUE)
		{
			echo $isUp;
		}else if($this->isAjax || $sys == FALSE)
		{
			return json_decode($isUp,true);
		}
	}

	public function insert_user()
	{
		$this->load->library('curl');
		$post = $this->input->post();
		$data = $this->blog_model->get_blog('*', $post['where']);

		if(count($data->result_array()) > 0)
		{
			$data = $data->row_array();
			$web = $data['processing_server'];
			$web = rtrim($web, '/').'/';
			$web .= 'users/curl_create_new_users';
			$token = $this->authentication->create_new_token($data['blog_id']);

			// $post['user']['token'] = $token['token'];
			$post = array(
					'token' => $token,
					'user' => $post['user']
				);
			// print_r($post['user']);
			echo $this->curl->simple_post($web, $post);

			// echo $this->curl->simple_post($web, $post), true);
			/*if($isDone === FALSE || $isDone['code'] == 500)
			{
				echo json_encode(array('code'=>500));
			}else
			{
				echo json_encode(array('code'=>200));

			}*/
		}
	}

}