<?php

class CY_Srv_Protocol_Http
{
	static $HTTP_HEADERS = array
		(
		 100 => "100 Continue",
		 200 => "200 OK",
		 201 => "201 Created",
		 204 => "204 No Content",
		 206 => "206 Partial Content",
		 300 => "300 Multiple Choices",
		 301 => "301 Moved Permanently",
		 302 => "302 Found",
		 303 => "303 See Other",
		 304 => "304 Not Modified",
		 307 => "307 Temporary Redirect",
		 400 => "400 Bad Request",
		 401 => "401 Unauthorized",
		 403 => "403 Forbidden",
		 404 => "404 Not Found",
		 405 => "405 Method Not Allowed",
		 406 => "406 Not Acceptable",
		 408 => "408 Request Timeout",
		 410 => "410 Gone",
		 413 => "413 Request Entity Too Large",
		 414 => "414 Request URI Too Long",
		 415 => "415 Unsupported Media Type",
		 416 => "416 Requested Range Not Satisfiable",
		 417 => "417 Expectation Failed",
		 500 => "500 Internal Server Error",
		 501 => "501 Method Not Implemented",
		 503 => "503 Service Unavailable",
		 506 => "506 Variant Also Negotiates"
			 );

	protected $errno = 0;
	protected $version;
	protected $method;
	protected $keepalve;
	protected $compress;

	function __construct()
	{

	}

	function run($client)
	{
		if(!is_resource($client))
		{
			cy_log(CYE_ERROR, 'bad requests.');
			return cy_dt(CYE_NET_ERROR, ['code' => 500]);
		}

		$_GET = $_POST = $_COOKIE = array();
		$options = array();

		$display = 'json';
		$dt = $this->read_requests($client);
		if($dt['errno'] === CYE_DATA_EMPTY)
		{
			return $dt;
		}

		if($dt['errno'] !== 0)
		{
			switch($dt['errno'])
			{
				case CYE_ACCESS_DENIED:
					$dt['code'] = 403;
					break;

				case CYE_EXPECT_FAIL:
					$dt['code'] = 417;
					break;

				default:			
					$dt['code'] = 503;
					break;
			}

			goto end;
		}

		$dt = $this->dispatch($_SERVER['REQUEST_URI'], $options);
end:
		empty($dt['code']) && $dt['code'] = 200;
		empty($dt['data']) && $dt['data'] = '';
		return $this->write_responses($client, $dt);
	}

	function read_requests($client, $options = [])
	{
		$options['server'] = stream_socket_get_name($client, true);
		$dt = CY_Util_Stream::http_read($client, $options);
		if($dt['errno'] !== 0)
		{
			return $dt;
		}

		if(empty($dt['data']))
		{
			return array('errno' => CYE_DATA_EMPTY);
		}

		$o = new http\Message($dt['data']);
		$this->version = $o->getHttpVersion();
		$this->method  = $o->getRequestMethod();
		$headers       = $o->getHeaders();
		if(!empty($headers['Content-Length']))
		{
			if(isset($headers['Expect']))
			{
				return array('errno' => CYE_EXPECT_FAIL);
			}

			if(empty($o->getBody()))
			{
				mp_log(CYE_ERROR, "Bad req:".$options['server']." ".str_replace("\r\n", "\\r\\n", $dt['data']));
			}
		}

		$this->keepalive = isset($headers['Connection']     ) && (strcasecmp($headers['Connection'], 'keep-alive') == 0);
		$this->compress  = isset($headers['Accept-Encoding']) && (strpos    ($headers['Accept-Encoding'], 'gzip') !== false);
		if(empty($headers['Host']))
		{
			return array('errno' => CYE_ACCESS_DENIED);
		}

		$parts = parse_url('http://'.$headers['Host'].$o->getRequestUrl());
		$_SERVER['REQUEST_URI' ] = $o->getRequestUrl();
		$_SERVER['QUERY_STRING'] = '';
		if(!empty($parts['query']))
		{
			$_SERVER['QUERY_STRING'] = $query = $parts['query'];
			parse_str($query, $_GET);
		}

		if(!empty($o->getBody()))
		{
			if(isset($headers['Content-Type']) && strpos($headers['Content-Type'], 'multipart/form-data') !== false)
			{
			        // grab multipart boundary from content type header
				preg_match('/boundary=(.*)$/', $headers['Content-Type'], $matches);

				// content type is probably regular form-encoded
				if(count($matches))
				{
					$boundary = $matches[1];
					$_POST = cy_parse_http_multipart($o->getBody(), $boundary);
				}
			}

			if(!isset($boundary))
			{
				parse_str($o->getBody(), $_POST);
			}
		}

		if(isset($headers['Cookie']))
		{
			$c = new http\Cookie($headers['Cookie']);
			$_COOKIE = $c->getCookies(); 
		}

		$_REQUEST = array_merge($_GET, $_POST, $_COOKIE);
		return array('errno' => 0);
	}

	function write_responses($client, $response, $options = [])
	{
		$body  = "HTTP/".$this->version.' '.self::$HTTP_HEADERS[$response['code']]."\r\n";
		$body .= "Date: ".gmdate("D, d M Y H:i:s T", $_SERVER['REQUEST_TIME'])."\r\n";
		$body .= "Server: BPS ".CY_BPS_VERSION."\r\n";
		$body .= "Connection: ".($this->keepalive ? 'keep-alive' : 'close')."\r\n";
		$body .= "Content-Type: text/html\r\n";
		if($this->compress && strlen($response['data']) > 4096)
		{
			$body .= "Content-Encoding: gzip\r\n";
			//$response['data']  = "\x1f\x8b\x08\x00\x00\x00\x00\x00";
			//$response['data'] .= substr(gzcompress($response['data'], 6), 0, -4);
			$response['data'] = gzencode($response['data'], 9);
		}

		$body .= "Content-Length: ".strlen($response['data'])."\r\n";
		$body .= "\r\n";
		$body .= $response['data'];
		return CY_Util_Stream::default_write($client, $body, $options);
	}

	function dispatch($request_uri, $options)
	{
		/* parse request uri start
		 * -----------------------------------
		 */
		$p           = strpos($request_uri, '?');
		$request_uri = $p !== false ? substr($request_uri, 0, $p) : $request_uri;

		/* security request uri filter. */
		if(preg_match('/(\.\.|\"|\'|<|>)/', $request_uri))
		{
			return array('errno' => 403, 'data' => "permission denied.");
		}

		/* get display format. */
		if(($p = strrpos($request_uri, '.')) !== false)
		{
			$tail = substr($request_uri, $p + 1);

			if(preg_match('/^[a-zA-Z0-9]+$/', $tail))
			{
				$display  = $tail; //'json'
				$request_uri = substr($request_uri, 0, $p);
			}
		}

		/* get version, module, id, method. */
		$requests = array_pad(explode('/', $request_uri, 5), 5, NULL);
		list(, $module, $id, $method) = $requests;

		/* default format: json */
		empty($display) && $display = 'json';
		empty($method ) && $method  = 'get' ;

		if(isset($_GET['id']))
		{
			$id = $_GET['id'];
		}

		$_ENV['module'] = $module;
		$_ENV['id']     = $id;
		$_ENV['method'] = $method;
		$_ENV['display']= $display;
		//$_ENV['version']= $version;
		/*-------------------------------------
		 * parse request uri end
		 */

		if(empty($module))
		{
			$dt = array('errno' => CYE_PARAM_ERROR, 'code' => 404, 'data' => 'Not found.');
			goto end;
		}

		if($module == 'static')
		{
			$file = CY_HOME.'/app'.$request_uri.'.'.$display;
			if(is_file($file))
			{
				$content = file_get_contents($file);
				return ['errno' => 0, 'code' => 200, 'data' => $content];
			}
		}

		$classname = 'CA_Entry_'.$module;                                                                                        
		if(!method_exists($classname, $method) && !method_exists($classname, '__call'))
		{
			$classname .= '_'.$method;
			$run        = isset($_GET['a']) ? $_GET['a'] : 'run';
			if(!method_exists($classname, $run))
			{
				$errno = CYE_PARAM_ERROR;
				$error = "method is not exists $classname:$method";
				$dt = array('errno' => $errno, 'code' => 404, 'data' => 'unkown request.', 'error' => $error);
				goto end;
			}

			$eny = new $classname;
			$dt  = $eny->$method($id, $_REQUEST, $_ENV);
		}
		else
		{
			$eny = new $classname;
			$dt  = $eny->$method($id, $_REQUEST, $_ENV);
		}

		unset($eny);

		if($dt['errno'] !== 0)
		{
			cy_log(CYE_ERROR, json_encode($dt));
			if($dt['errno'] != CYE_PARAM_ERROR)
			{
				$dt['code'] = 500;
			}
			else
			{
				$dt['code'] = 200;
			}
			if(empty($dt['error']))
			{
				$dt['data'] = 'Internal error.';
			}
			else
			{
				$dt['data'] = $dt['error'];
			}
		}
		else
		{
			$dt['code'] = 200;
		}

end:

		if($display === 'html' || $display === 'php')
		{
			if($dt['errno'] == 0)
			{
				$files = array();
				$files[] = CY_HOME.'/app/html/'.$module.'/'.$method.'.'.$display;
				$files[] = CY_HOME.'/app/html/'.$module.'.'.$display;
				$files[] = CY_HOME.'/app/html/default.'.$display;
				foreach($files as $i => $file)
				{
					if(is_file($file))
					{
						break;
					}

					if($i === 1 && $display === 'html')
					{
						$_ENV['display'] = $display = 'php';
						goto end;
					}
				}
			}
			else
			{
				$file = CY_HOME.'/app/html/error.'.$display;
			}
		}
		else
		{
			$file = NULL;
		}

		$t = new CY_Util_Output();
		$t->assign(['errno' => $dt['errno'], 'data' => $dt['data']]);
		$data = $t->get($file);
		return ['errno' => 0, 'code' => $dt['code'], 'data' => $data];
	}
}

/* vim: set ts=4 sw=4 sts=4 tw=100 noet: */
?>
