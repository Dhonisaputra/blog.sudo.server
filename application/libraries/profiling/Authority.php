<?php
/**
* 
*/
class Authority 
{
    
    private $config = array(
        'HASH_ALGO' => 'sha384',
        'METHOD' => 'AES-256-OFB',
    );
	public function __construct($config = array())
	{
		$this->configuration($config);
	}

	
	public function configuration($config = array())
	{
		$this->config = array_merge($this->config, $config);
	}

    private function basic_hashing($text = '#', $options)
    {
        $def_options = [
            'cost' => 2,
            'salt' => mcrypt_create_iv(2, MCRYPT_DEV_URANDOM),
        ];
        $options    = array_merge( $def_opt, $options );

        $hash = password_hash($text, PASSWORD_BCRYPT, $options)."\n";
        $explode_password = explode('$', $hash);
        $tail = array_pop($explode_password);
        return array('raw' => $hash, 'tail' => $tail);
    }

    /*
    | $options = [key => array[key_A, key_B], encode => bool, password => string, algo => ALGO, hash_options => array[see http://devdocs.io/php/function.password-hash] ]
    EXAMPLE
        $hash = $this->authority->password_hash(
            array(  
                    'password'  => $randPass,
                    'key'       => array($key['key_A'], $key['key_B'])
                )
            );
    */
    public function password_hash($options)
    {
        $def_opt = array(
            'algo'          => PASSWORD_BCRYPT,
            'password'      => 'rasmuslerdorf',
            'encode'        => true,
            'hash_options'  => array(
                'cost' => 6,
                'salt' => mcrypt_create_iv(22, MCRYPT_DEV_URANDOM),
            )
        ); 
        $options    = array_merge( $def_opt, $options );
        if(!isset($options['key']))
        {
            $key = $this->handshakeKey('',true);
            $options['key'] = array($key['key_A'], $key['key_B']);
        }
        $hashing                    = password_hash($options['password'], $options['algo'], $options['hash_options']);
        $explode_password           = explode('$', $hashing);

        $data['raw_password']       = array_pop($explode_password);
        $data['encrypted_password'] = $this->encrypt($data['raw_password'], $options['key'][0], $options['key'][1], $options['encode']);
        $data['key']                = $options['key'];
        $data['head_raw_password']  = implode('$', $explode_password);
        return $data;
    }

    /*
    | $options = [key => array[key_A, key_B], encode => bool, password => string, encrypted_password => string, algo => ALGO, hash_options => array[see http://devdocs.io/php/function.password-hash] ]
    EXAMPLE

        $decrypt = $this->authority->password_verify(
            array(  
                'encrypted_password'=> $hash['encrypted_password'],
                'password'          => $randPass,
                'key'               => array($key['key_A'], $key['key_B'])
            )
        );
    */
    /*
        return bool
    */
    public function password_verify($options)
    {
        if(!isset($options['encrypted_password'])) return false;
        $def_opt = array(
            'algo'          => PASSWORD_BCRYPT,
            'password'      => 'rasmuslerdorf',
            'encode'        => true,
            'hash_options'  => array(
                'cost' => 6,
                'salt' => mcrypt_create_iv(22, MCRYPT_DEV_URANDOM),
            )
        ); 

        $options    = array_merge( $def_opt, $options );
        $create_example_password_cost = password_hash( $options['password'], $options['algo'], $options['hash_options']);
        $cost_code  = explode('$', $create_example_password_cost);
        array_pop( $cost_code );
        
        $cost_code  = implode( '$' ,$cost_code);
        $decrypt    = $this->decrypt($options['encrypted_password'], $options['key'][0], $options['key'][1], $options['encode']);
        if((int)$decrypt['status_code'] == 404) return false;
        $decrypt    = $cost_code.'$'.$decrypt['decrypted_text'];

        return password_verify($options['password'], $decrypt);
    }
    
	public function encrypt($message, $key_A, $key_B, $encode = false)
    {
        /*$split = $this->handshakeKey($key);
        list($encKey, $authKey) = $split;*/

        // Pass to UnsafeCrypto::encrypt
        $ciphertext = $this->encryptCipherText($message, $key_A);

        // Calculate a MAC of the IV and ciphertext
        $mac = hash_hmac($this->config['HASH_ALGO'], $ciphertext, $key_B, true);

        if ($encode) {
            return base64_encode($mac.$ciphertext);
        }
        // Prepend MAC to the ciphertext and return to caller
        return $mac.$ciphertext;

    }

    public function decrypt($message, $key_A, $key_B, $encoded = false)
    {
        $status = array();
        $isEqual = $this->check_equality($message, $key_A, $key_B, $encoded);
        if (!$isEqual) {
            $status['resultText'] = 'Retrieving Data Failed: Key and Text not matched!';
            $status['status_code'] = 404;
            $status['status_type'] = 'error';
            return $status;
        
            // throw new Exception('Encryption failure');
        }

        $plaintext = $this->decryptCipherText($isEqual, $key_A);
        $status['decrypted_text'] = $plaintext;
        $status['status_code'] = 200;
        $status['resultText'] = 'Retrieving Data success: Key and Text matched!';
        $status['status_type'] = 'success';

        return $status;
    }

    protected function check_equality($message, $key_A, $key_B, $encoded = false)
    {
        if ($encoded) {
            $message = base64_decode($message, true);
            if ($message === false) {
                throw new Exception('Encryption failure');
            }
        }

        // Hash Size -- in case HASH_ALGO is changed
        $hs = mb_strlen(hash($this->config['HASH_ALGO'], '', true), '8bit');

        $mac = mb_substr($message, 0, $hs, '8bit');
        $ciphertext = mb_substr($message, $hs, null, '8bit');

        

        $calculated = hash_hmac(
            $this->config['HASH_ALGO'],
            $ciphertext,
            $key_B,
            true
        );

        if (!$this->hashEquals($mac, $calculated)) {
            return false;
        }

        return $ciphertext;
    }

    protected function hashEquals($a, $b)
    {
        if (function_exists('hash_equals')) {
            return hash_equals($a, $b);
        }
        $nonce = openssl_random_pseudo_bytes(32);
        
        return hash_hmac($this->config['HASH_ALGO'], $a, $nonce) === hash_hmac($this->config['HASH_ALGO'], $b, $nonce);
    }

    public function handshakeKey($masterKey = null, $encoded = true)
    {

        // create random text
        # random text for message / content hash
        $bytes = openssl_random_pseudo_bytes(32);

        # random text from masterkey
        $masterKey = empty($masterKey)? openssl_random_pseudo_bytes(64) : $masterKey;

        // buat pbckey Raw dari bytes dan masterKey
        $pbKey = $this->encryptCipherText($bytes, $masterKey, $encoded);

        // buat private key dari hasil pbkey
        $pvtKey     = $this->encryptCipherText($bytes, $pbKey, $encoded);

        return [
            'key_A' => $pbKey,//hash_hmac($this->config['HASH_ALGO'], 'ENCRYPTION', $masterKey, $encode),
            'key_B' => $pvtKey,//hash_hmac($this->config['HASH_ALGO'], 'AUTHENTICATION', $masterKey, $encode)
        ];
    }

    public  function encryptCipherText($message, $key, $encode = false)
    {
    	$method = $this->config['METHOD'];
        $nonceSize = openssl_cipher_iv_length($method);
        $nonce = openssl_random_pseudo_bytes($nonceSize);

        $ciphertext = openssl_encrypt(
            $message,
            $this->config['METHOD'],
            $key,
            OPENSSL_RAW_DATA,
            $nonce
        );

        // Now let's pack the IV and the ciphertext together
        // Naively, we can just concatenate
        if ($encode) {
            return base64_encode($nonce.$ciphertext);
        }
        return $nonce.$ciphertext;
    }

    public function decryptCipherText($message, $key, $encoded = false)
    {
        if ($encoded) {
            $message = base64_decode($message, true);
            if ($message === false) {
                throw new Exception('Encryption failure');
            }
        }

        $nonceSize = openssl_cipher_iv_length($this->config['METHOD']);
        $nonce = mb_substr($message, 0, $nonceSize, '8bit');
        $ciphertext = mb_substr($message, $nonceSize, null, '8bit');

        $plaintext = openssl_decrypt(
            $ciphertext,
            $this->config['METHOD'],
            $key,
            OPENSSL_RAW_DATA,
            $nonce
        );

        return $plaintext;
    }

    

}
