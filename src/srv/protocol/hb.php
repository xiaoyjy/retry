<?php

class CY_Srv_Protocol_HB
{
	protected $config_main;

	function __construct()
	{
		$this->config = array
			(
			 [CY_TYPE_UINT16, 'raw_len_head'],
			 [CY_TYPE_STRING, 'raw_header'   , 'raw_len_head'],
			 [CY_TYPE_UINT16, 'raw_len_body'],
			 [CY_TYPE_STRING, 'raw_body'     , 'raw_len_body'] 
			);

		$this->config_head = array
			(
			 [CY_TYPE_UINT8 , 'module_len'],
			 [CY_TYPE_STRING, 'module'     , 'module_len'],
			 [CY_TYPE_UINT8 , 'method_len'],
			 [CY_TYPE_STRING, 'method'     , 'method_len'],
			 [CY_TYPE_UINT8 , 'id_len']    ,
			 [CY_TYPE_STRING, 'id'         , 'id_len'],
			 [CY_TYPE_UINT8 , 'fmt_len']   ,
			 [CY_TYPE_STRING, 'fmt'        , 'fmt_len'],
			 [CY_TYPE_UINT16, 'version']   ,
			);
	}

	function run($client, $request = "")
	{
		$errno   = 0;
		$error   = '';
		$display = 'json';
		$b = cy_unpack($request, $this->config);
		if(!$b || empty($b['raw_header']) || empty($b['raw_len_head']) || $b['raw_len_head'] < 4)
		{
			$errno = CYE_PARAM_ERROR;
			$error = "invalid request.";
			goto error;
		}

		$r = cy_unpack($b['raw_header'], $this->config_head);
		$r['length'] = $b['raw_len_body'];

		$_ENV['module'] = $module = $r['module'];
		$_ENV['id']     = $id     = $r['id']; 
		$_ENV['method'] = $method = isset($r['method'] ) ? $r['method']  : 'get'; 
		$_ENV['display']= $display= $r['fmt'];
		$_ENV['version']= $version= isset($r['version']) ? $r['version'] : 1;

		$classname = 'CA_Entry_'.$module;                                                                                        
		if(!method_exists($classname, $method) && !method_exists($classname, '__call'))
		{
			$classname .= '_'.$method;
			$run        = isset($_GET['a']) ? $_GET['a'] : 'run';
			if(!method_exists($classname, $run))
			{
				$errno = CYE_PARAM_ERROR;
				$error = "method is not exists $classname:$method";
				goto error;
			}
		}

		switch($display)
		{
			case 'raw':
				$req = $b['raw_body'];
				break;

			case 'json':
				$req = json_decode($b['raw_body'], true);
				if(empty($req))
				{
					$errno = json_last_error    ();
					$error = json_last_error_msg();
					goto error;
				}

				break;

			case 'mgp':
				$req = msgpack_unpack($b['raw_body']);
				$req  = ['errno' => 0, 'data' => $data];
				break;

			default:
				$errno = -1;
				$error = 'unkown formant';
				break;
		}

		$eny = new $classname;
		$dt  = $eny->get($id, $req, $_ENV);
		unset($eny);

response:
		switch($display)
		{
			case 'raw':
				$body = $dt;
				break;
			case 'json':
				$body = json_encode($dt);
				break;

			case 'mpg':
				$body = msgpack_pack($dt);
				break;

			default:
				$body = 'unknown format '.$display;
				break;
		}

		if(empty($body) || !is_string($body))
		{
			return ['errno' => 0, 'error' => 'error response body.'];
		}

		$hd = ['raw_len_head' => 0, 'raw_header' => ''];
		if(isset($b['raw_len_head'])) $hd['raw_len_head'] = $b['raw_len_head'];
		if(isset($b['raw_header'  ])) $hd['raw_header'  ] = $b['raw_header'  ];

		$inputs = array
			(
			 [CY_TYPE_UINT16, $hd['raw_len_head']],
			 [CY_TYPE_STRING, $hd['raw_header'  ]],
			 [CY_TYPE_UINT16, strlen($body)      ],
			 [CY_TYPE_STRING, $body              ]
			);

		return ['errno' => $errno, 'error' => $error, 'data' => cy_pack($inputs)];

error:
		$dt = array('errno' => $errno, 'error' => $error);
		cy_log(CYE_WARNING, "$client ".$error);
		goto response;
	}
}

/* vim: set ts=4 sw=4 sts=4 tw=100 noet: */
?>
