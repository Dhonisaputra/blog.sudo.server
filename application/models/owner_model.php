<?php
/**
* 
*/
class Owner_model extends CI_Model
{
	
	public function __construct()
	{
		parent::__construct();
		require_once(APPPATH.'libraries/profiling/Pengguna.php');
		$this->auth = new Pengguna;
	}
	public function create_owner_database($data)
	{
		$name = $data['prefix'].$data['owner_id'];
		$this->db->query('CREATE DATABASE '.$name );
		$this->create_table_blog_to_db($name);
	}
	public function config_db($dbname)
	{
		$db['hostname'] = 'localhost';
		$db['username'] = 'root';
		$db['password'] = 'toor';
		$db['database'] = $dbname;
		$db['dbdriver'] = 'mysqli';
		$db['dbprefix'] = '';
		$db['pconnect'] = TRUE;
		$db['db_debug'] = TRUE;
		$db['cache_on'] = FALSE;
		$db['cachedir'] = '';
		$db['char_set'] = 'utf8';
		$db['dbcollat'] = 'utf8_general_ci';
		$db['swap_pre'] = '';
		$db['autoinit'] = TRUE;
		$db['stricton'] = FALSE;
		return $db;
	}
	private function create_table_blog_to_db($db)
	{
		$newDBConfig = $this->config_db($db);
		$connection = $this->load->database($newDBConfig, true);
		$connection->trans_start();
		$connection->query("
				CREATE TABLE posts ( id_post int(11) NOT NULL AUTO_INCREMENT, id_user int(11) DEFAULT NULL, title varchar(255) DEFAULT NULL, content longtext, posted_time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, post_status enum('draft','publish') DEFAULT 'draft', avatar_post text, post_tag mediumtext, post_categories text,
				  counter_post int(11) DEFAULT '0', PRIMARY KEY (id_post) ); ");

		$connection->query(" CREATE TABLE post_categories ( id_post int(11) NOT NULL, id_category int(11) NOT NULL, PRIMARY KEY (id_post,id_category) ); ");
		$connection->query("
				CREATE TABLE post_files (
				  id_post_files int(11) NOT NULL AUTO_INCREMENT,
				  id_post int(11) DEFAULT NULL,
				  id_files int(11) DEFAULT NULL,
				  uploaded_timestamp timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				  uploaded_by int(11) DEFAULT NULL,
				  PRIMARY KEY (id_post_files)
				); ");
		$connection->query("
				CREATE TABLE master_files (
				  id_files int(11) NOT NULL AUTO_INCREMENT,
				  file_name text,
				  file_type varchar(10) DEFAULT NULL,
				  file_path varchar(200) DEFAULT NULL,
				  raw_name text,
				  original_name text,
				  client_name text,
				  file_ext varchar(10) DEFAULT NULL,
				  file_size int(200) DEFAULT NULL,
				  PRIMARY KEY (id_files)
				);
				");
		$connection->query("

				CREATE TABLE categories (
				  id_category int(11) NOT NULL AUTO_INCREMENT,
				  name varchar(200) NOT NULL,
				  description text,
				  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				  created_by int(11) NOT NULL,
				  PRIMARY KEY (id_category)
				);
			");
		$connection->trans_complete();

		// echo $connection->last_query();

	}
	public function new_owner($data)
	{
		$this->db->insert('owner', $data);
		$data_result = $this->db;
		$data['owner_id'] = $data_result->insert_id();
		$data['owner_key'] = uniqid().$data['owner_id'];
		$this->update_owner(
			array('owner_key' => $data['owner_key'] ),
			array('owner_id' => $data['owner_id'] )
		);
		// $this->create_owner_database($data);
		return $data_result;
	}

	public function get_owner($select='*',$where = array())
	{
		$this->db->select($select);
		$this->db->from('owner');
		if(isset($where) && (is_array($where) || is_string($where)) )
		{
			$this->db->where($where);
		}
		return $this->db->get();
	}
	public function check_owner_credential($password, $owner)
	{
		return $this->auth->password_verify(array(
				'password' => $password,
				'encrypted_password' => $owner['owner_password'],
				'key'=> array($owner['key_A'], $owner['key_B'])
			));
	}
	public function update_owner($update, $where){

		$this->db->where($where);
		$this->db->update('owner', $update); 
	}


	public function extract_owner_key($owner_key)
	{
		$auth = substr($owner_key, -1);
		return array('owner_id' => $auth, 'owner_key' => $owner_key);
	}

	public function decrypt_app_key($extracted_owner_key, $app_key_encrypted)
	{

		$dec = $this->auth->decrypt($app_key_encrypted, $extracted_owner_key['owner_id'], $extracted_owner_key['owner_key'], true);
		if($dec['status_code'] !== 200)
		{
			return false;
		}
		return $dec['decrypted_text'];
	}

	public function decrypt_auth($app_key_A, $owner_key, $auth_encrypted)
	{
		$dec = $this->auth->decrypt($auth_encrypted, $app_key_A, $owner_key, true);
		if($dec['status_code'] !== 200)
		{
			return false;
		}
		$dec = explode('*', $dec['decrypted_text']);
		return array(
				'prefix' => $dec[0],
				'source' => $dec[1],
				'email' => $dec[2],
			);
	}

	public function set_credential($body, $source='panel')
	{
		$auth_raw 	= $body['prefix'].'*'.$source.'*'.$body['owner_email'];
		$encAuth 	= $this->auth->encrypt($auth_raw, $body['key_A'], $body['owner_key'], true);
		$encAppKey 	= $this->auth->encrypt($body['key_A'], $body['owner_id'], $body['owner_key'], true);
		$returndata = array(
			'code' => 200,
			'auth' => $encAuth,
			'app_key' => $encAppKey,
			'auth_unique' => $body['owner_key'],
			'owner_key' => $body['owner_key'],
		);
		return $returndata;
	}

	public function get_credential($body, $source="panel")
	{
		$owner_data = $this->extract_owner_key($body['owner_key']);
		$decAppKey = isset($body['app_key'])? $this->decrypt_app_key($owner_data, $body['app_key']) : null;
		$decAuth = isset($body['auth'])? $this->decrypt_auth($decAppKey, $body['owner_key'], $body['auth']) : null;

		$returndata['prefix'] = isset($decAuth)? $decAuth['prefix'] : 'blog_';
		$returndata['owner_id'] = $owner_data['owner_id'];
		$returndata['owner_key'] = $owner_data['owner_key'];
		$returndata['source'] = isset($decAuth)? $decAuth['source'] : '';
		$returndata['email'] = isset($decAuth)? $decAuth['email'] : '';
		$returndata['app_key'] = $decAppKey? $decAppKey : '';
		$returndata['is_auth'] = $decAuth ? true : false;
		$returndata['need_auth'] = $source == 'panel' ? true : false;
		return $returndata;
	}
}