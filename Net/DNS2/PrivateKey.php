<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * DNS Library for handling lookups and updates. 
 *
 * PHP Version 5
 *
 * Copyright (c) 2010, Mike Pultz <mike@mikepultz.com>.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *   * Neither the name of Mike Pultz nor the names of his contributors 
 *     may be used to endorse or promote products derived from this 
 *     software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRIC
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @category  Networking
 * @package   Net_DNS2
 * @author    Mike Pultz <mike@mikepultz.com>
 * @copyright 2011 Mike Pultz <mike@mikepultz.com>
 * @license   http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version   SVN: $Id$
 * @link      http://pear.php.net/package/Net_DNS2
 * @since     File available since Release 1.1.0
 *
 */

/**
 * SSL Private Key container class
 *
 * @category Networking
 * @package  Net_DNS2
 * @author   Mike Pultz <mike@mikepultz.com>
 * @license  http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link     http://pear.php.net/package/Net_DNS2
 * 
 */
class Net_DNS2_PrivateKey
{
    /*
     * the filename that was loaded; stored for reference
     */
    public $filename;

    /*
     * the keytag for the signature
     */
    public $keytag;

    /*
     * the sign name for the signature
     */
    public $signname;

    /*
     * the algorithm used for the signature
     */
    public $algorithm;

    /*
     * the key format of the signature
     */
    public $key_format;

    /*
     * the openssl private key id
     */
    public $instance;

    /*
     * RSA: modulus
     */
    private $_modulus;

    /*
     * RSA: public exponent
     */
    private $_public_exponent;

    /*
     * RSA: rivate exponent
     */
    private $_private_exponent;

    /*
     * RSA: prime1
     */
    private $_prime1;

    /*
     * RSA: prime2
     */
    private $_prime2;

    /*
     * RSA: exponent 1
     */
    private $_exponent1;

    /*
     * RSA: exponent 2
     */
    private $_exponent2;

    /*
     * RSA: coefficient
     */
    private $_coefficient;

    /*
     * DSA: prime
     */
    public $prime;
    
    /*
     * DSA: subprime
     */
    public $subprime;

    /*
     * DSA: base
     */
    public $base;

    /*
     * DSA: private value
     */
    public $private_value;

    /*
     * DSA: public value
     */
    public $public_value;

    /**
     * Constructor - base constructor the private key container class
     * 
     * @param string $file path to a private-key file to parse and load
     *
     * @throws Net_DNS2_Exception
     * @access public
     * 
     */
    public function __construct($file = null)
    {
        if (isset($file)) {
            $this->parseFile($file);
        }
    }

    /**
     * parses a private key file generated by dnssec-keygen
     * 
     * @param string $file path to a private-key file to parse and load
     *
     * @return boolean
     * @throws Net_DNS2_Exception
     * @access public
     * 
     */
    public function parseFile($file)
    {
        //
        // check for OpenSSL
        //
        if (extension_loaded('openssl') === false) {

            throw new Net_DNS2_Exception(
                'the OpenSSL extension is required to use parse private key.',
                Net_DNS2_Lookups::E_OPENSSL_UNAVAIL
            );
        }

        //
        // check to make sure the file exists
        //
        if (is_readable($file) == false) {

            throw new Net_DNS2_Exception(
                'invalid private key file: ' . $file,
                Net_DNS2_Lookups::E_OPENSSL_INV_PKEY
            );
        }

        //
        // get the base filename, and parse it for the local value
        //
        $keyname = basename($file);
        if (strlen($keyname) == 0) {

            throw new Net_DNS2_Exception(
                'failed to get basename() for: ' . $file,
                Net_DNS2_Lookups::E_OPENSSL_INV_PKEY
            );
        }

        //
        // parse the keyname
        //
        if (preg_match("/K(.*)\.\+(\d{3})\+(\d*)\.private/", $keyname, $matches)) {
            
            $this->signname    = $matches[1];
            $this->algorithm   = intval($matches[2]);
            $this->keytag      = intval($matches[3]);

        } else {

            throw new Net_DNS2_Exception(
                'file ' . $keyname . ' does not look like a private key file!',
                Net_DNS2_Lookups::E_OPENSSL_INV_PKEY
            );
        }

        //
        // read all the data from the
        //
        $data = file($file, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
        if (count($data) == 0) {
            
            throw new Net_DNS2_Exception(
                'file ' . $keyname . ' is empty!',
                Net_DNS2_Lookups::E_OPENSSL_INV_PKEY
            );
        }

        foreach ($data as $line) {

            list($key, $value) = explode(':', $line);

            $key    = trim($key);
            $value  = trim($value);

            switch(strtolower($key)) {

            case 'private-key-format':
                $this->key_format = $value;
                break;

            case 'algorithm':
                if ($this->algorithm != $value) {
                    throw new Net_DNS2_Exception(
                        'Algorithm mis-match! filename is ' . $this->algorithm . 
                        ', contents say ' . $value,
                        Net_DNS2_Lookups::E_OPENSSL_INV_ALGO
                    );
                }
                break;

            //
            // RSA
            //
            case 'modulus':
                $this->_modulus = $value;
                break;

            case 'publicexponent':
                $this->_public_exponent = $value;
                break;

            case 'privateexponent':
                $this->_private_exponent = $value;
                break;
        
            case 'prime1':
                $this->_prime1 = $value;
                break;

            case 'prime2':
                $this->_prime2 = $value;
                break;

            case 'exponent1':
                $this->_exponent1 = $value;
                break;

            case 'exponent2':
                $this->_exponent2 = $value;
                break;

            case 'coefficient':
                $this->_coefficient = $value;
                break;

            //
            // DSA - this won't work in PHP until the OpenSSL extension is better
            //
            case 'prime(p)':
                $this->prime = $value;
                break;

            case 'subprime(q)':
                $this->subprime = $value;
                break;

            case 'base(g)':
                $this->base = $value;
                break;

            case 'private_value(x)':
                $this->private_value = $value;
                break;

            case 'public_value(y)':
                $this->public_value = $value;
                break;

            default:
                throw new Net_DNS2_Exception(
                    'unknown private key data: ' . $key . ': ' . $value,
                    Net_DNS2_Lookups::E_OPENSSL_INV_PKEY
                );
            }
        }

        //
        // generate the private key
        //
        $args = array();

        switch($this->algorithm) {
        
        //
        // RSA
        //
        case Net_DNS2_Lookups::DNSSEC_ALGORITHM_RSAMD5:
        case Net_DNS2_Lookups::DNSSEC_ALGORITHM_RSASHA1:
        case Net_DNS2_Lookups::DNSSEC_ALGORITHM_RSASHA256:
        case Net_DNS2_Lookups::DNSSEC_ALGORITHM_RSASHA512:

            $args = array(

                'rsa' => array(

                    'n'                 => base64_decode($this->_modulus),
                    'e'                 => base64_decode($this->_public_exponent),
                    'd'                 => base64_decode($this->_private_exponent),
                    'p'                 => base64_decode($this->_prime1),
                    'q'                 => base64_decode($this->_prime2),
                    'dmp1'              => base64_decode($this->_exponent1),
                    'dmq1'              => base64_decode($this->_exponent2),
                    'iqmp'              => base64_decode($this->_coefficient)
                )
            );

            break;

        //
        // DSA - this won't work in PHP until the OpenSSL extension is better
        //
        case Net_DNS2_Lookups::DNSSEC_ALGORITHM_DSA:

            $args = array(

                'dsa' => array(

                    'p'                 => base64_decode($this->prime),
                    'q'                 => base64_decode($this->subprime),
                    'g'                 => base64_decode($this->base),
                    'priv_key'          => base64_decode($this->private_value),
                    'pub_key'           => base64_decode($this->public_value)
                )
            );

            break;

        default:
            throw new Net_DNS2_Exception(
                'we only currently support RSAMD5 and RSASHA1 encryption.',
                Net_DNS2_Lookups::E_OPENSSL_INV_PKEY
            );
        }

        //
        // generate and store the key
        //
        $this->instance = openssl_pkey_new($args);
        if ($this->instance === false) {
            throw new Net_DNS2_Exception(
                openssl_error_string(),
                Net_DNS2_Lookups::E_OPENSSL_ERROR
            );
        }

        //
        // store the filename incase we need it for something
        //
        $this->filename = $file;

        return true;
    }
}

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * c-hanging-comment-ender-p: nil
 * End:
 */
?>
