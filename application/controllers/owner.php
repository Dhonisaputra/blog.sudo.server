<?php

class Owner extends CI_Controller
{

	function __construct()
	{
		# code...
		parent::__construct();
		$this->isAjax = $this->input->is_ajax_request();
		$this->load->model('owner_model');
	}

	public function config($owner_key)
	{
		$data = $this->db->query('SELECT * FROM owner where owner_key = ?', array($owner_key));
		if(count($data->result_array()) > 0)
		{
			$data = $data->row_array();
			echo json_encode(
					array(
							"double_server" => $data['double_server'] == 0? FALSE : TRUE,
							"prefix" => $data['prefix'],
							"web_url" => $data['web_url'],
							"processing_server" => base_url(),
							"handling_server" => $data['handling_server'],
							"trends" => $data['trends'],

						)
				);
		}
	}
	private function encrypt($data)
	{
		require_once(APPPATH.'libraries/profiling/Pengguna.php');
		$auth = new Pengguna;
		return $auth->create_account($data, array('password_hash' => 'password', 'exception' => 'email' ));

		/*$redecode = $auth->password_verify(array(
				'password' => $post['password'],
				'encrypted_password' => $passhash['password'],
				'key' => array($passhash['key_A'], $passhash['key_B']),
			));	*/
	}
	private function save_new_owner($data)
	{
		$this->owner_model->new_owner($data);
	}
	public function create_new_owner()
	{
			/*$_POST = array(
				'name' 		=> 'Tanaka Kousei',
				'password' 	=> 'admin',
				'email' 	=> 'a@c.com',
			);*/
		if( !isset($_POST['name']) 	||
			!isset($_POST['email']) ||
			!isset($_POST['password'])
			){
			show_error('Error insuficient data.', '500');
			return false;
		}

		$post = $this->input->post();
		if($this->is_owner_exist())
		{
			show_error('Email has been registered!', '500', 'Error on create new owner');
		}
		$e = $this->encrypt($post);
		$this->owner_model->new_owner(array(
				'owner_name' 	=> $e['name'],
				'owner_address' => isset($e['address'])? $e['address'] : '',
				'owner_email' 	=> $e['email'],
				'owner_password'=> $e['password'],
				'key_A' 		=> $e['key_A'],
				'key_B' 		=> $e['key_B'],
				'prefix' 		=> 'blog_',
			)
		);
	}

	public function is_owner_exist($email = '')
	{
		$post = $this->input->post();
		$owner = $this->owner_model->get_owner('*', array('owner_email' => $post['email']))->result_array();
		if(count($owner) > 0)
		{
			header('http/1.0 500 user exist');
			return false;
		}
		return count($owner) > 0? true : false;
	}
	public function login()
	{
		require_once(APPPATH.'libraries/profiling/Pengguna.php');
		$auth = new Pengguna;
		/*$_POST = array(
				'name' => 'Tanaka Kousei',
				'password' => 'admin',
				'email' => 'a@b.com',
			);*/
		$post = $this->input->post();
		$get = $this->input->get();
		if(!isset($post['email']) || !isset($post['password']))
		{
			echo json_encode(array('status'=>500, 'message'=> 'insuficient data!'));
			return false;
		}

		$return = $this->owner_model->get_owner('*', array('owner_email' => $post['email']))->result_array();
		if(count($return) > 0)
		{

			$return = $return[0];
			$verify = $auth->password_verify(array(
					'password' => $post['password'],
					'encrypted_password' => $return['owner_password'],
					'key'=> array($return['key_A'], $return['key_B'])
				));
			if($verify)
			{
				if(!isset($get['dblServer']) || $get['dblServer'] == 1 )
				{

					echo json_encode(
					array(
						'status' 	=> 200, 
						'owner_email' => $return['owner_email'], 
						'owner_key'	=> $return['owner_key'], 
						'username' 	=> $return['username'], 
						'prefix' 	=> $return['prefix'], 
						'app_key_A' => $return['key_A'],
						'owner_id' 	=> $return['owner_id']
						)
					);
				}else
				{
					$auth = $this->owner_model->set_credential($return);
					echo json_encode($auth);
				}
			}else
			{
				echo json_encode(array('status'=> 500,'message' => 'Wrong password!') );
			}
		}else
		{
			echo json_encode(array('status'=> 404,'message' => 'Owner not recognized!', 'post' => $_POST) );
		}
	}

	public function get_credential($data)
	{
		/*$data = array(
				'auth' => 'u8oEM8Rk6pBcwKvDkXB9jq1Rn9ryU9oBy0utFToqt4cJqJP7pJrbh/0OPf/SD9hWg/kTFQUJ2MI9/RAnOEUf3D80rrGCAQ/ZvtZF1IJfSPLlxVM=',
				'app_key' => 'EPk89DHISsGsoiya0PXCFAZBOm+f9hCivCw0FsWl10tCnNm+vZxKBlm/XsO+1pgZzXIVZ09KAv6DlGs9o/TNB8K1mSXe5/1szM4st+/mEfO8sRblvAzkizNMTipPgeU8Lxb+5W+29eknrje9YqVEZ9EdcmLgn3Gfdbq+8pTIRyI= ',
				'owner_key' => '58f3425f81af11'
			);*/
		$cr = $this->owner_model->get_credential($data);
		print_r($cr);
	}

}