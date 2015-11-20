<?php
/*
 * Http based multi fetch model.
 *
 * By: jianyu@carext.com
 */

class CY_Util_Curl
{
	protected $mh;
	protected $timeout = 4000;

	protected $handles = array();
	protected $hosts   = array();

	protected $count   = 0;

	function __construct()
	{
		$this->mh = curl_multi_init();
	}

	function __destruct()
	{
		curl_multi_close($this->mh);
	}

	function size()
	{
		return $this->count;
	}

	function mix($ch, $c)
	{
		$d = curl_getinfo($ch);
		$errno = curl_errno($ch);
		$error = curl_error($ch);
		if($errno != CURLE_OK || !empty($error))
		{
			$d['error'] = $error;
			cy_log(CYE_WARNING, '%s errno(%s) %s', $d['url'], $errno, $error);
			//cy_ctl_fail($this->hosts[$key], $_SERVER['REQUEST_TIME']);
			goto end;
		}

		if(isset($d['download_content_length']))
		{
			$dcl = $d['download_content_length'];
			if(     $dcl != -1 &&
				$dcl !=  0 &&
				$dcl != $d['size_download'])
			{
				$d['error'] = 'not finish, maybe timeout is too small.';
				cy_log(CYE_WARNING, '%s not finish, time cost %fs', $d['url'], $d['total_time']);
				goto end;
			}
		}

		$contents = curl_multi_getcontent($ch);
		$options  = $c->options();
		if(isset($options['include']))
		{
			$header   = substr($contents, 0, $d['header_size']);
			$contents = substr($contents,    $d['header_size']);

			$headers  = [];
			$array    = explode("\n", $header);
			foreach($array as $i => $h)
			{
				$h = explode(':', $h, 2);
				if (isset($h[1]))
				{
					$headers[$h[0]] = trim($h[1]);
				}
			}

			if(isset($array[0]) && preg_match('/.*(\d\d\d).*/', $array[0], $m))
			{
				$headers['Response Code'] = $m[1];
			}

			$d['headers'] = $headers;
		}

		$d['data']  = $c->outputs($contents);
		$d['class'] = get_class($c);
		$d['proxy'] = isset($options['proxy']) ? $options['proxy'] : '';

end:
		curl_multi_remove_handle($this->mh, $ch);
		curl_close($ch);
		unset($this->handles[(int)$ch]);
		$this->count--;

		$d['errno'] = $errno;
		return $d;
	}

	/**
	 * add
	 *
	 * @param $key  string  unique key for each request.
	 * @param $c    array   config object.
	 *
	 * @return true | false.
	 */
	function add($key, $c)
	{
		$inputs = $c->inputs([]);
		if(empty($inputs['url']))
		{
			return false;
		}

		$url   = $inputs['url'];
		$parts = parse_url($url);
		$host  = $parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
		$this->hosts[$key] = $host;
		/*
		   if(!cy_ctl_check($host, $_SERVER['REQUEST_TIME']))
		   {
		   return false;
		   }
		 */

		/* get options */
		$options = $c->options();
		$timeout = isset($options['timeout']) ? $options['timeout'] : $this->timeout;
		$method  = isset($inputs['method' ] ) ? $inputs ['method' ] : 'get';
		if(isset($inputs['data']))
		{
			if(strncasecmp($method, 'get', 3) === 0)
			{
				$query = http_build_query($data);
				if(strpos($url, '?') === false)
				{
					$url .= '?'.$query;
				}
				else if($url[strlen($url)-1] == '&')
				{
					$url .= $query;
				}
				else
				{
					$url .= '&'.$query;
				}
			}
		}

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_HEADER        , !empty($options['include']));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_MAXREDIRS     , 5);
		curl_setopt($ch, CURLOPT_ENCODING      , 'gzip');
		curl_setopt($ch, CURLOPT_NOSIGNAL      , true);
		if(defined('CURLOPT_TIMEOUT_MS'))
		{
			curl_setopt($ch, CURLOPT_TIMEOUT_MS, $timeout);
		}
		else
		{
			curl_setopt($ch, CURLOPT_TIMEOUT, ceil($timeout/1000));
		}

		if(strncasecmp($method, 'post', 4) === 0)
		{
			if(isset($inputs['data']))
			{
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $inputs['data']);
			}

			/* Disable Expect: header  */
			isset($inputs['header']) || $inputs['header'] = [];
			$inputs['header'][] = 'Expect: ';
		}

		if(isset($inputs['header']))
		{
			curl_setopt($ch, CURLOPT_HTTPHEADER, $inputs['header']);
		}

		if(isset($options['proxy']))
		{
			curl_setopt($ch, CURLOPT_PROXY, $options['proxy']);
		}
		elseif(isset($_ENV['config']['http_proxy']))
		{
			$which = array_rand($_ENV['config']['http_proxy']);
			$proxy = $_ENV['config']['http_proxy'][$which];
			curl_setopt($ch, CURLOPT_PROXY, $proxy[0].':'.$proxy[1]);
		}

		curl_multi_add_handle($this->mh, $ch);

		$this->handles[(int)$ch] = array($ch, $c, $key);

		$this->count++;
		return true;
	}

	function recv($callback = NULL, $timeout = 0.1)
	{
		$active = 0; /* A reference to a flag to tell whether the cURLs are still running  */

		$t1 = $t2 = microtime(true);
		if($this->count < 1)
		{
			return cy_dt(1, 'empty handle.');
		}

		$errno = curl_multi_exec($this->mh, $active);
		if($errno != CURLM_OK)
		{
			return cy_dt($errno, curl_strerror($errno));
		}

		$select_func = function_exists('cy_curl_multi_select') ? 'cy_curl_multi_select' : 'curl_multi_select';
		while($this->count === $active && ($t2 - $t1) < $timeout)
		{
			//$ready = $select_func($this->mh, $timeout, 0x01|0x4);
			$ready = curl_multi_select($this->mh, $timeout);
			if($ready < 0)
			{
				return cy_dt(-1, 'curl_multi_select error');
			}

			$errno = curl_multi_exec($this->mh, $active);
			if($errno != CURLM_OK)
			{
				return cy_dt($errno, curl_strerror($errno));
			}

			$t2 = microtime(true);
		}

		$data = [];
		while(($r = curl_multi_info_read($this->mh, $msgs_in_queue)))
		{
			{
				$ch = $r['handle'];
				list(, $c, $key) = $this->handles[(int)$ch];
				$data[$key]=$mix = $this->mix($ch, $c);
				if($callback)
				{
					call_user_func($callback, $key, $mix);
				}
			}
		}

		return cy_dt(OK, $data);
	}

	function get()
	{
		$active = null;
		$t1 = microtime(true);

		do
		{
			$mrc = curl_multi_exec($this->mh, $active);
		}
		while($mrc == CURLM_CALL_MULTI_PERFORM);

		$select_func = function_exists('cy_curl_multi_select') ? 'cy_curl_multi_select' : 'curl_multi_select';
		while($active && $mrc == CURLM_OK)
		{
			PHP_VERSION_ID < 50214 ? usleep(5000) : $select_func($this->mh);
			do
			{
				$mrc = curl_multi_exec($this->mh, $active);
			}
			while($mrc == CURLM_CALL_MULTI_PERFORM);
		}

		$stat = array();
		$data = array();
		while(($r = curl_multi_info_read($this->mh, $msgs_in_queue)))
		{
			if($r['msg'] == CURLMSG_DONE)
			{
				$ch = $r['handle'];
				list(, $c, $key) = $this->handles[(int)$ch];
				$data[$key] = $this->mix($ch, $c);
			}
		}

		/*
		foreach($this->handles as $id => list($ch, $c, $key))
		{
			$data[$key] = $this->mix($ch, $c);
		}
		*/
		$cost = (microtime(true) - $t1)*1000000;
		cy_stat('Curl-get', $cost, array('c' => implode(',', $stat)));
		return array('errno' => 0, 'data' => $data);
	}

	function fetch($url, $method = 'GET', $headers = array(), $options = array())
	{
		$getinfo = !empty($options['getinfo']);
		unset($options['getinfo']);

		$c = new CY_Driver_Http_Default($url, $method, $headers, $options);
		$this->add(0, $c);
		$dt = $this->get();
		if($dt['errno'] !== 0 || $dt['data'][0]['errno'] !== 0)
		{
			return NULL;
		}

		return $getinfo ? $dt['data'][0] : $dt['data'][0]['data'];
	}
}

/* vim: set ts=4 sw=4 sts=4 tw=100 noet: */
?>
