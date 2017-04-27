<?php
if(function_exists('session_status') && session_status() == PHP_SESSION_NONE)
{
	session_start();
}elseif(!isset($_SESSION))
{
	session_start();
}
/**
* 
*/

require_once('Authority.php');
require_once('Session.php');
class Pengguna extends Authority
{
	private $VERSION_NAME 	= 'alphino';
	private $VERSION_NUMBER	= '1.0.0';
	private $config_login 	= array(
		'REMEMBER_ME' 	=> false,
		'EXPIRATION' 	=> 7200, # in case if you use remember me, it will works.
	);

	
	public function __construct($username = null, $password = null, $level = null)
	{
		parent::__construct();
		$this->config_pengguna();
		if( isset($username) && !empty($username) &&
			isset($password) && !empty($password) &&
			isset($level) && !empty($level))
		{
			return $this->create_account(array('username' => $username, 'password' => $password, 'level'=>$level) );
		}
		$this->Sess = new Session;
	}

	/*
	|---------------------
	| Configuration Default
	|---------------------
	*/
	public function config_pengguna($config = array())
	{
		$_conf = array(
				'HASH_PASSWORD' => 'sha1',
				'ENCODED' => true,
			);
		$this->data_config_pengguna = array_merge($_conf, $config);
	}

	/*
	|----------------------------
	| Function create some encryption for an account
	|---------------------------
	*/
	public function create_account($data, $options)
	{
		$_deff_opt = array(
				'password_hash' => false
			);
		$options = array_merge($_deff_opt, $options);
		$key = $this->handshakeKey(null, $this->data_config_pengguna['ENCODED']);
		foreach ($data as $dkey => $value) {
			$usingHash = explode(',', $options['password_hash']);
			$exception = explode(',', $options['exception']);

			if(in_array($dkey, $exception))
			{
				continue;
			}

			if($options['password_hash'] !== false && in_array($dkey, $usingHash))
			{
				$pass = $this->password_hash(array(
						'password' => $value,
						'key' => array($key['key_A'], $key['key_B']),
						'encode' => $this->data_config_pengguna['ENCODED']
					));
				$data[$dkey] = $pass['encrypted_password'];

			}else
			{
				$data[$dkey] = $this->encrypt($value, $key['key_A'], $key['key_B'], $this->data_config_pengguna['ENCODED']);
			}
		}
		
		
		$data['key_A'] 	= $key['key_A'];
		$data['key_B'] 	= $key['key_B'];
		

		return $data;
	}

	public function hash_content($content, $key_A, $key_B)
	{
		return $this->encrypt($content, $key_A, $key_B, $this->data_config_pengguna['ENCODED']);
	}

	public function user_authentication($content, $content_encrypted, $key_A, $key_B, $is_password = false)
	{
		if($is_password === true)
		{
			$content = $this->set_hashing_password($content);
		}
		$c = $this->decrypt($content_encrypted, $key_A, $key_B, $this->data_config_pengguna['ENCODED']);
		if($c['status_code'] === 404)
		{
			return false;
		}

		return $content == $c['decrypted_text'];
	}

	public function session($data_login)
	{
		$date = uniqid(true);
		$IP = $_SERVER['SERVER_ADDR'];

		$session_PID = $this->encryptCipherText($IP, session_id(), $this->data_config_pengguna['ENCODED']);
		$prep = array(
			'PID' => $session_PID, 
			'USER_AGENT' => $_SERVER['HTTP_USER_AGENT'],
			'IP_ADDRESS' => $IP,
		);
		$this->Sess->set_session('__'.$this->VERSION_NAME, $prep);

		foreach ($data_login as $key => $value) {
			$this->Sess->set_session($key, $value);
		}
	}

	private function remember_me($data_login)
	{

	}

	/*
	|----------------------
	| Function has level
	|---------------------
	|
	| use: $u->has_level([11,10])
	| use: $u->has_level([11])
	| use: $u->has_level(10)
	| use: $u->has_level('pengawas')
	| use: $u->has_level(['pengawas','guru'])
	*/
	public function has_level($level, $levelEnc = false)
	{
		if(!isset($level) || $level == '' || (is_array($level) && count($level) < 1 ) )
		{	
            // throw new Exception('Level granted failure:level granted function executed but didnt specified. all users can pass it!');
            trigger_error("LEVEL GRANTED FAILURE::: function has_level has been executed. but no specified.  level is undefined!", E_USER_ERROR);
            die();

		}
		// jika level bukan array, jadikan array
		if(!is_array($level))
		{
			$d0[] = $level;
		}else
		{
			// jika sudah array, biarkan
			$d0 = $level;
		}

		if(!isset($_SESSION['level']) )
		{
			header('HTTP/1.0 404 You Must Login First.');
			return false;
		}
		$isexs = array();
		
		$lvl=$_SESSION['level'];

		// check session level is exist in array level from parameter
		return in_array($lvl, $d0)? true : false;

	}
	



	public function config_login($config = array())
	{
		$this->config_login = array_merge( $this->config_login, $config);
	}
	public function get_config_login($what)
	{
		return $this->config_login[$what];
	}
	/**
	*----------------------------
	* Function login:: untuk menyimpan data pengguna kedalam session.
	*----------------------------
	*
	* @param $data_login array [encrypted_password, encrypted_username, key_A, key_B]
	*
	*/
	public function login($data_login)
	{
		if( $this->get_config_login('REMEMBER_ME') )
		{
			$this->remember_me($data_login);
		}else
		{
			$this->session($data_login);
		}
		
	}
	public function is_login()
	{
		// ambil PID;
		$pid = @$_SESSION['__'.$this->VERSION_NAME]['PID'];
		$ip  = @$_SESSION['__'.$this->VERSION_NAME]['IP_ADDRESS'];
		// check kesesuaian data encrypt IP dengan IP address. jika sama berarti login.
		$decrypt = $this->decryptCipherText($pid, session_id(), $this->data_config_pengguna['ENCODED']);

		return ($decrypt === $ip)? true : false;
	}

	public function logout()
	{
		$pid = @$_SESSION['__'.$this->VERSION_NAME]['PID'];
		if(isset($_SESSION[$pid])){ unset($_SESSION[$pid]); }
		if(isset($_SESSION['__'.$this->VERSION_NAME])){ unset($_SESSION['__'.$this->VERSION_NAME]); }
		session_destroy();
	}

	public function must_login()
	{

	}

	private function set_hashing_password($password)
	{
		return hash($this->data_config_pengguna['HASH_PASSWORD'], $password);
	} 


	public function check_data_pengguna($username, $password, $key_A, $key_B)
	{

		$u = $this->check_equality($this->data['encrypted_password'], $key_A, $key_B, $this->data_config_pengguna['ENCODED']);
		$p = $this->check_equality($this->data['encrypted_username'], $key_A, $key_B, $this->data_config_pengguna['ENCODED']);
		$as[] = ($u === false)? 'false' : 'true';
		$as[] = ($p === false)? 'false' : 'true';
		
	}

	private function set_check_hash($username_enc, $key_A, $key_B, $encoded = false)
	{
		return $this->decrypt($username_enc, $key_A, $key_B, $encoded);

	}

	public static function permission($permission)
	{
		self::convert_permission($permission);
		$this->read = $a['read'];
		$this->update = $a['update'];
		$this->delete = $a['delete'];
		$this->create = $a['create'];
	}

	private static function convert_permission($permissions)
	{
		$explode = str_split($permissions);
		list($read, $update, $delete, $create) = $explode;
		$a = array('read'=> (int) $read, 'update' => (int) $update, 'delete' => (int) $delete, 'create' => (int) $create );
		return $a;
	}

	public function canRead()
	{
		return $this->read === 0? false : true;
	}

	public function canUpdate()
	{
		return $this->update === 0? false : true;
	}

	public function canDelete()
	{
		return $this->delete === 0? false : true;
	}

	public function canCreate()
	{
		return $this->create === 0? false : true;
	}

}

