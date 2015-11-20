<?php

class CA_Entry_Default
{
	static $hosts = [
		//'125.88.190.33',
		//'183.136.133.236',
		//'221.204.14.182',
		'27.221.20.236'
		];

	static $thread = 1;
	static $retry  = 20;

	function get($id, $req, $env)
	{
		header("Content-Type: text/html; charset=gbk");

		if($env['display'] == 'js' || $env['display'] == 'css' || $env['display'] == 'gif' ||
				$env['display'] == 'jpg' || $env['display'] == 'bmp')
		{
			//$seconds_to_cache = 9600; 
			$ls = gmdate("D, d M Y H:i:s", time()) . " GMT";
			//$ts = gmdate("D, d M Y H:i:s", time() + $seconds_to_cache) . " GMT"; 
			//header("Expires: $ts");
			header("Last-Modified: $ls");
			//header("Pragma: cache"); 
			//header("Cache-Control: max-age=$seconds_to_cache");	

			if(isset($_SERVER['HTTP_IF_MODIFIED_SINCE']))
			{
				header("HTTP/1.1 304 Not Modified");
				exit;
			}
		}

		//$str = '';
		$str = $this->get_html_loop(self::$retry);
		//$str = trim(str_replace('"text/html; charset=GBK"', '"text/html; charset=gb2312"', $str));
		//$str = @iconv("GBK", "UTF-8", $str);
		if($_SERVER['SCRIPT_NAME'] == '/yyfh.appointment.do' && isset($_REQUEST['m']))
		{
			if($_REQUEST['m'] == 'add')
			{
				//$str = file_get_contents(CY_HOME."/data/add.html");
				//$str = iconv('UTF-8', 'GBK', $str);
				$str = str_replace('name="p_Appointment_server_id" id=""',
						'name="p_Appointment_server_id" id="p_Appointment_server_id"',
						$str);

				$str = str_replace('value="297edff83cf6b763013cf634a2f80006"',
						'value="297edff83cf6b763013cf634a2f80006" selected',
						$str);

			}
			else if($_REQUEST['m'] == 'edit' || $_REQUEST['m'] == 'showAddPage')
			{
$md5 = md5($str);
$fp = fopen(CY_HOME."/data/".$_REQUEST['m'].$md5.".html", "w");
fwrite($fp, $str);
fclose($fp);

				//$str = file_get_contents(CY_HOME."/data/edit.html");
				//$str = iconv('UTF-8', 'GBK', $str);
				$array = [];
				$array['p_Appointment_app_date'  ] = '2015-11-23';
				$array['p_Appointment_app_1'     ] = 'X京房权证朝字第nnnnnnn号';    // 不动产产权证号
				$array['slwq'                    ] = 'C';                 // 网签合同号类型
				$array['wqhth'                   ] = '0000000';           // 网签合同号 
				$array['p_Appointment_app_3'     ] = '00000000';          // 完税凭证号
				$array['p_Appointment_app_5'     ] = '刘aabb';            // 卖方姓名或名称
				$array['p_Appointment_app_7'     ] = '000000000000000000';// 卖方身份证号
				$array['p_Appointment_app_8'     ] = '';                  // 卖方委托代理人姓名
				$array['p_Appointment_app_10'    ] = '';                  // 卖方委托代理人证件号码

				$array['p_Appointment_app_12'    ] = '杨aabb';            // 买方姓名或名称
				$array['p_Appointment_app_14'    ] = '000000000000000000';// 买方身份证号
				$array['p_Appointment_app_15'    ] = '';                  // 买方委托代理人姓名
				$array['p_Appointment_app_17'    ] = '';                  // 买方委托代理人证件号码

				$array['p_Appointment_phonenumber'    ]= '15900000000';   // 预约人手机号
				$array['p_Appointment_testmsgtemp'    ] = '111111';       // 请设定个人密码
				$array['p_Appointment_testmsgtempnext'] = '111111';       // 请设定个人密码

				$keys = [];
				for($i = 1; $i<18; $i++) $keys[] = 'app_'.$i;
				$keys[] = 'phonenumber';
				$keys[] = 'testmsgtemp';
				$keys[] = 'testmsgtempnext';
				foreach($keys as $k)
				{
					$key = 'p_Appointment_'.$k;
					if(!isset($array[$key]))
					{
						continue;
					}

					$value = iconv("UTF-8", "GBK", $array[$key]);
					$str = preg_replace('/<input.*?="'.$key.'"[^>]*>/',
							'<input id="'.$key.'" name="'.$key.'" value="'.$value.'">', $str);
				}

				$str = preg_replace('#<select name="p_Appointment_app_date".*?</select>#ms',
						'<select name="p_Appointment_app_date" id="p_Appointment_app_date"><option value="'.
						$array['p_Appointment_app_date'].'" selected>'.$array['p_Appointment_app_date'].'</option></select>',
						$str);

				$str = preg_replace('/<option value="'.$array['slwq'].'">/',
						'<option value="'.$array['slwq'].'" selected>',
						$str);

				$str = preg_replace('/<input.*?id="wqhth"[^>]*>/',
						'<input type="text" id="wqhth" maxlength="30" value="'.$array['wqhth'].'">',
						$str);

				// 证件类型	
				$str = str_replace('<option value="sfz">', '<option value="sfz" selected>', $str);
				$str = preg_replace('#<select name="p_Appointment_app_9">.*?</select>#ms',
						'<select name="p_Appointment_app_9"></select>', $str);
				$str = preg_replace('#<select name="p_Appointment_app_16">.*?</select>#ms',
						'<select name="p_Appointment_app_16"></select>', $str);

				$str = str_replace('<a href="http://fwjy1.bjchy.gov.cn/yyfh.appointment.do#" onclick="getCheckCode()">',
						'<a href="http://fwjy1.bjchy.gov.cn/yyfh.appointment.do##" onclick="getCheckCode()">',
						$str);

			}
		}
		else if($_SERVER['SCRIPT_NAME'] == '/scripts/yanzheng.js')
		{
			$str = str_replace('checkCode.value = code;',
					'document.getElementById("j_captcha_response").value = code; checkCode.value = code;',
					$str);
		}
		else if($_SERVER['SCRIPT_NAME'] == '/scripts/calendar.js')
		{
			$str = str_replace('frames("meizzCalendarIframe");', 'frames["meizzCalendarIframe"];', $str);
		}
		else if($_SERVER['SCRIPT_NAME'] == '/js/time.js')
		{
			$str = str_replace('function document.onclick()', 'document.onclick = function()', $str);
			$str = str_replace('function document.onkeydown()', 'document.onkeydown = function()', $str);
		}
		else if($_SERVER['SCRIPT_NAME'] == '/scripts/popAlert.js')
		{
			//$str = str_replace('win.document', 'win.contentWindow.document', $str);
			$str = '';
		}

		echo $str;
		exit;
	}

	function get_html_loop($retry)
	{
		if(isset($_REQUEST['m']) && (($_REQUEST['m'] == 'edit' || $_REQUEST['m'] == 'getCheckCode' || $_REQUEST['m'] == 'saveAdd')))
		{
			$th_num = 1;
		}
		else
		{
			$th_num = self::$thread; 
		}

		for($i = 0; $i < $retry; $i++)
		{
			if(($str = $this->get_html($th_num)) !== '')
			{
				return $str;
			}
		}

		return '';
	}

	function get_html($th_num)
	{
		$headers = $this->get_header();
		if($_SERVER['REQUEST_METHOD'] == 'POST')
		{
			$data = file_get_contents('php://input');
			$headers['data'] = $data;
		}

		$options = ['timeout' => 20000, 'include' => true];
		$host = self::$hosts[array_rand(self::$hosts)];
		$url  = 'http://'.$host.$_SERVER['REQUEST_URI'];

		$handlers = [];
		if(isset($_REQUEST['m']) && $_REQUEST['m'] == 'saveAdd')
		{
			//$dates = ['2015-11-23', '2015-11-24', '2015-11-25', '2015-11-26', '2015-11-26'];
			$dates = ['2015-11-25'];
			foreach($dates as $date)
			{
				$hds = $headers;
				$hds['data'] = str_replace('2015-11-23', $date, $hds['data']);
				for($i = 0; $i < $th_num; $i++)
				{
					$handlers[] = new CY_Driver_Http_Default($url, $_SERVER['REQUEST_METHOD'], $hds, $options);
				}
			}
		}
		else
		{
			for($i = 0; $i < $th_num; $i++)
			{
				$handlers[] = new CY_Driver_Http_Default($url, $_SERVER['REQUEST_METHOD'], $headers, $options);
			}
		}

		return $this->get_remote_html($handlers);
	}


	function get_remote_html($handlers)
	{
		$curl = new CY_Util_Curl();
		foreach($handlers as $i => $c)
		{
			$curl->add($i, $c);
		}		

		$ok   = false;
		$code = 0;
		do
		{
			$dt = $curl->recv();
			if(isset($dt['data'])) foreach($dt['data'] as $r)
			{
				if($r['http_code'] == 200)
				{
					if(isset($r['headers']['Set-Cookie']))
					{
						header('Set-Cookie: '.$r['headers']['Set-Cookie']);

					}

					return $r['data'];
				}

				$code = $r['http_code'];
			}
		}
		while($curl->size() > 0);

		return $code ? false : '';
	}

	function get_add_page()
	{
		print_R($this->get_header());
		return $this->get_default();		
	}

	function get_header()
	{
		$headers = [];
		foreach($_SERVER as $key => $value) {
			if(substr($key, 0, 5) === 'HTTP_') {
				$key = substr($key, 5);
				$key = str_replace('_', ' ', $key);
				$key = ucwords(strtolower($key));
				$key = str_replace(' ', '-', $key);

				$headers[] = $key.": ".$value; 
			}
		}

		return $headers;
	}

	/** 
	 * 获取HTTP请求原文 
	 * @return string 
	 */
	function get_http_raw()
	{ 
		$raw = ''; 

		// (1) 请求行 
		$raw .= $_SERVER['REQUEST_METHOD'].' '.$_SERVER['REQUEST_URI'].' '.$_SERVER['SERVER_PROTOCOL']."\r\n"; 

		// (2) 请求Headers 
		foreach($_SERVER as $key => $value) { 
			if(substr($key, 0, 5) === 'HTTP_') { 
				$key = substr($key, 5); 
				$key = str_replace('_', '-', $key); 

				$raw .= $key.': '.$value."\r\n"; 
			} 
		} 

		// (3) 空行 
		$raw .= "\r\n"; 

		// (4) 请求Body 
		$raw .= file_get_contents('php://input');
		return $raw; 
	}

}

?>
