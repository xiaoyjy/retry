<?php

/**
 * 正则匹配的一个封装
 *
 */
function cy_regex_extract($string, $regex, $which = 0)
{
	if(preg_match($regex, $string, $m))
	{
		return $m[$which];
	}

	return '';
}

/**
 * 去掉html tag里的某个属性
 */
function cy_remove_html_attr($html, $attrs = [])
{
	$pattern = [];
	foreach($attrs as $attr)
	{
		$pattern[] = '/'.$attr.'\s*=\s*(["\']).*?\1\s*/';
	}

	return preg_replace($pattern, '', $html);
}

/**
 * 判断一个字段是否为非ascii字符
 * 
 */
function cy_is_w($char)
{
	$n = ord($char);
	return (64 < $n && $n < 91) || (96 < $n && $n < 123) || (47 < $n && $n < 58);
}

/**
 * NOTICE:
 *  tag space shoud be remove before invoke this function.
 *  eg: </ a > shoud be replace to </a>
 *
 * @author:
 *	 Jianyu<xiaoyjy@gmail.com>
 *
 * Example:
 *
 * $html: 
 *
 * 
 */
function cy_split_by_tag1($html, $tags = [])
{
	$length = strlen($html);

	/* 防止出Warings，又减少计算量的track做法 */
	for($i = 0; $i < $length; $i++)
	{
begin:
		/* NOTICE: $i just at '|' */
		/**
		 *  Find out:
		 *	   xxxx|<foo>
		 *	   xxxx|<foo ...>
		 *     or  xxxx|</foo>
		 *
		 *  but ignore:
		 *         xxxx|<html> end of xxxx will be ignored.
		 */
		$p = strpos($html, '<', $i);

		/***
		 * Last html tag
		 *
		 * may be:
		 *     </html>|\r\n
		 * 
		 */
		if($p === false)
		{
			/* Skip tag will make non empty $text. */
			if(!empty($text))
			{
				yield $i => trim($text);
			}

			break;
		}

		/* xxxx|<foo> or xxxx|</foo>, restore 'xxxx' as new line. */
		if(!empty($text))
		{
			/**
			 * Right now $text may be the first part of:
			 *  <foo .... />| or
			 *  <foo...>..</foo>| or
			 *  <foo xxxxxxx>|....</foo>
			 *  </foo>|
			 */

			/* Skip tag will make non empty $text. */
			$text .= "\n";
			$text .= trim(substr($html, $i, $p - $i));

			yield $i => trim($text);
			$text  = '';
		}
		else
		{
			yield $i => trim(substr($html, $i, $p - $i));
		}

		$i = strpos($html, '>', $p);
		if(!$i)
		{
			cy_log(CYE_WARNING, 'invalid html content "<" without ">" at the end.');
			break;
		}

		$w = ($html[$p+1] !== '/') ? 1 : 2;

		/**
		 * Then:
		 *  $i <foo|>xxxx or </foo|>xxxx or <foo ...|> or </html|>
		 *  $p |<foo>xxxx or |</foo>xxxx or |<foo ...> or |</html>
		 */

		/**
		 * Get next html tag name
		 */
		$y = $p;
		while(cy_is_w($html[++$y]));

		/**
		 * Right now
		 *  $i: <foo|>xxxx or </foo|>xxxx or <foo ...|> or </html|>
		 *  $p: |<foo>xxxx or |</foo>xxxx or <foo ...|> or |</html>
		 *  $y: <foo|>xxxx or </foo|>xxxx or <foo| ...> or </html|>
		 *  $p + $w:
		 *      <|foo>xxxx or </|foo>xxxx or <|foo ...> or </|html>
		 *
		 *  so $t == 'foo'
		 */
		$t = strtolower(substr($html, $p + $w, $y - $p - $w));

		/* if need skip */
		if(!empty($t) && in_array($t, $tags))
		{
			/**
			 * <p> is a very speical tag
			 * most of web editors support <p>, contents in <p> should not be separated.
			 */
			if($t === 'p')
			{
				if($w === 2)
				{
					cy_log(CYE_WARNING, "found </p> without <p>, skip it.");

					$i = $p + 4 /* strlen('</p>') */;
					goto begin; 
				}

				$y = $k = $p;
				/* $y, $k, $p:
				 *   |<p>...</p>                   or
				 *   |<p ...>...</p>               or
				 *   |<p> .. <p>.. </p> </p>       or 
				 *
				 * Invalid:
				 *   |<p> ..<p> </p> not enongh '</p>'
				 */
				do
				{
					/* Invalid <p> tag, just skip it. */
					if(($x = stripos($html, '</p>', $k)) === false)
					{
						cy_log(CYE_WARNING, "content at $p found unclosed <p> tag");

						$i = $p + 3 /* 3 = sizeof('<p>') */;

						/* Right new $i at <p>| .. <p> .. </p> */
						goto begin;
					}

					$k = $x + 4/*strlen('</p>')*/;
					/**
					 * $x: <p> .. <p>.. |</p> </p>
					 $ $k: <p> .. <p>.. </p>| </p>
					 * $p: |<p> .. <p>.. </p> </p>
					 */

					$z = substr($html, $y, $k - $y);

					/* Make sure $z have't <p...> or <p> */
					do
					{
						$j = stripos($z, '<p' , 2/* strlen(<p>)==3' */);
					}
					/**
					 * But not <pxxx>, such as <pre>, <param>, etc. 
					 * if match that, just pass through
					 *
					 * $j:
					 *  ....|<param>.. 
					 *
					 * if <param> ...
					 *   z = substr('.....<param>...<p>...</p>', );
					 *   z = aram>...<p>...</p>
					 */
					while(cy_is_w($z[$j+2]) && ($z = substr($z, $j + 2/* strlen(<p)==2 */)));

					/**
					 * so, $j = position of <p..> | <p> or false.
					 */
					$y += (int)$j; 
				}
				while($j);

				/**
				 * finally:
				 * 
				 * $p: |<p> .. <p>.. </p> </p>
				 * $k: <p> .. <p>.. </p> </p>|
				 */
			}

			else
			{
				/*
				 * $p: |<foo...> or |</foo> 
				 * $k: <foo...>| or </foo>|
				 */ 
				$k = strpos($html, '>', $p) + 1;
			}

			/* save the skipped html into $text */
			$text = substr($html, $p, $k - $p);
			$i    = $k - 1;

		} /* skip tags end . */
	}
}

/**
 * 修正html中的语法错误 
 *
 */
function cy_html_repair($html, $encoding = 'UTF8')
{
	$config = array
		(
		 'clean'         => true,
		 'output-xml'    => true,
		 'output-xhtml'  => true,
		 'wrap'          => 200
		);


	$t = new tidy();
	$t->parseString($html, $config, $encoding);
	$t->cleanRepair();

	// fix html
	return $t->html();
}

/**
 * Spider中用的，提取页面编码
 *
 * @ignore
 */
function cy_html_charset($string)
{
	if(preg_match('/charset(\s*?)=(\s*?)["\']?([\w-]+)["\']?[ >]/i', $string, $m))
	{
		$encode = strtoupper($m[3]);
		if($encode == 'UTF8')
		{
			$encode = 'UTF-8';
		}

		return $encode;
	}

	$orders = ["ISO-8859-1", 'UTF-8', 'GB18030', 'BIG-5'];
	return mb_detect_encoding($string, $orders);
}


/**
 * 将中文描述的时间转成数据描述的时间
 *
 */
function cy_normalize_date($str)
{
	$cn=array('二十','三十',
			'十一','十二','十三','十四','十五','十六','十七','十八','十九',
			'一','二','三','四','五','六','七','八','九','十',
			'零', '○','年', '月', '日','/','–', '时', '分', '秒');

	$num=array('2','3',
			'11','12','13','14','15','16','17','18','19',
			'1','2','3','4','5','6','7','8','9','10',
			'0', '0','-', '-', '', '-','-', ':', ':', '');

	return rtrim(str_replace($cn, $num, $str), ':');
}


/**
 * 对整个数据执行iconv
 *
 * $param $ar 要执行iconv的数据
 * $param $from 当前编码
 * $param $to 目标编码
 */
function cy_iconv_recursive($ar, $from = 'GB18030', $to = 'UTF-8//IGNORE')
{
	if(empty($ar))
	{
		return $ar;
	}

	if(is_string($ar))
	{
		return iconv($from, $to, $ar);
	}

	if(!is_array($ar))
	{
		return $ar;
	}

	$new_ar = array();
	foreach($ar as $k => $v)
	{
		$new_ar[$k] = cy_iconv_recursive($v);
	}

	return $new_ar;
}

/**
 * 用于spider中解析页面中的title
 *
 * @ignore
 */
function cy_split_html_title($title)
{
	$combine = function($nodes, $c)
	{
		$array = [];
		$count = count($nodes);
		for($i = 0; $i < $count; $i++)
		{
			$end = $count - $i;
			$len = $i + 1;
			for($j = 0; $j < $end; $j++)
			{
				$tmp = [];
				for($k = 0; $k < $len; $k++)
				{
					$tmp[] = $nodes[$j + $k];
				}

				$array[] = implode($c, $tmp);
			}
		}

		return $array;
	};

	$array = [];
	foreach(['|', '_', '-', '/',' '] as $c)
	{
		$array[] = $combine(array_map('trim', explode($c, $title)), $c); 
	}

	/* 去除括号 */
	$array[][] = trim(preg_replace('/\(.*/', '', $title));
	return $array;
}


/**
 * Parse raw HTTP request data
 *
 * Pass in $a_data as an array. This is done by reference to avoid copying
 * the data around too much.
 *
 * Any files found in the request will be added by their field name to the
 * $data['files'] array.
 *
 * @param   $input
 * @param   $boundary 
 * @return  array  Associative array of request data
 */
function cy_parse_http_multipart($input, $boundary)
{
	// read incoming data
	$a_data = [];

	// split content by boundary and get rid of last -- element
	$a_blocks = preg_split("/-+$boundary/", $input);
	array_pop($a_blocks);

	// loop data blocks
	foreach ($a_blocks as $id => $block)
	{
		if (empty($block))
			continue;

		// you'll have to var_dump $block to understand this and maybe replace \n or \r with a visibile char
		// parse uploaded files
		if (strpos($block, 'application/octet-stream') !== FALSE)
		{
			// match "name", then everything after "stream" (optional) except for prepending newlines
			preg_match("/name=\"([^\"]*)\".*stream[\n|\r]+([^\n\r].*)?$/s", $block, $matches);
			$a_data['files'][$matches[1]] = $matches[2];
		}
		// parse all other fields
		else
		{
			// match "name" and optional value in between newline sequences
			preg_match('/name=\"([^\"]*)\"[\n|\r]+([^\n\r].*)?\r$/s', $block, $matches);
			$a_data[$matches[1]] = $matches[2];
		}
	}

	return $a_data;
}



/**
 * 把整型时间戳转成对人类友好的表达，如一天前、刚刚、一年前等。
 *
 * @param $timestamp 整形时间戳
 */
function cy_time_friendly($timestamp)
{
	$curTime = time();
	$space = $curTime - $timestamp;

	//1分钟
	if($space < 60)
	{
		$string = "刚刚";
		return $string;
	}
	elseif($space < 3600) //一小时前
	{
		$string = floor($space / 60) . "分钟前";
		return $string;
	}
	// 365*24*3600 = 31536000
	elseif($space > 31536000)
	{
		$string = (int)($space/31536000)."年前";
		return $string;
	}
	// 30*24*3600 = 2592000
	elseif($space > 2592000)
	{
		$string = "约".(int)($space/2592000)."月前";
		return $string;
	}
	// 7*24*3600 = 604800
	elseif($space > 604800)
	{
		$string = (int)($space/604800)."周前";
		return $string;
	}
	
	$curtimeArray = getdate($curTime);
	$timeArray    = getdate($timestamp);
	if($curtimeArray['year'] == $timeArray['year'])
	{
		if($curtimeArray['yday'] == $timeArray['yday'])
		{
			$format = "%H:%M";
			$string = strftime($format, $timestamp);
			return "今天 {$string}";
		}
		elseif(($curtimeArray['yday'] - 1) == $timeArray['yday'])
		{
			$format = "%H:%M";
			$string = strftime($format, $timestamp);
			return "昨天 {$string}";
		}
		else
		{
			$string = sprintf("%d月%d日", $timeArray['mon'], $timeArray['mday']);
			return $string;
		}
	}


	$string = sprintf("%d年%d月%d日 %02d点", $timeArray['year'], $timeArray['mon'], $timeArray['mday'], 
			$timeArray['hours']);
	return $string;
}

/**
 * 把时间格式转成对人类友好的表达，如一天前、刚刚、一年前等。
 *
 * @param $datetime 格式如：2010-10-10，等strtotime函数可以解析的时间格式
 */
function cy_datetime_friendly($datetime)
{
	$n = strtotime($datetime);
	if((int)$n < 0)
	{
		$n = 0;
	}

	return cy_time_friendly($n);
}

function cy_id_decode($id)
{
	if(empty($id))
	{
		return [];
	}

	if(is_numeric($id))
	{
		return ['id' => $id];
	}

	$array = explode('-', $id);
	if(is_numeric($array[0]))
	{
		return $array;
	}

	$count = count($array);
	if(($count&1) === 1)
	{
		// Error
		return [];
	}

	$data = [];
	for($i = 0; $i < $count; $i+=2)
	{
		$data[$array[$i]] = urldecode($array[$i+1]);
	}
	return $data;
}

function cy_id_encode($id)
{
	if(is_numeric($id))
	{
		return $id;
	}

	$array = [];
	foreach($id as $k => $v)
	{
		if(!is_numeric($k))
		{
			$array[] = $k;		
		}

		$array[] = $v;	
	}

	return implode('-', $array);
}

function cy_url_normalize($uri)
{
	$parts = parse_url($uri);
	if(empty($parts['scheme']) || empty($parts['host']))
	{
		//return cy_dt(CYE_PARAM_ERROR, 'full url needed');
		return '';
	}

	$account = '';
	if(!empty($parts['user']))
	{
		$account = $parts['user'];
		if(!empty($parts['pass']))
		{
			$account .= ':'.$parts['pass'];
		}

		$account .= '@';
	}

	$path = '';
	if(!empty($parts['path']))
	{
		$path = rtrim($parts['path'], '/');
	}

	$query = '';
	if(!empty($parts['query']))
	{
		parse_str($parts['query'], $queries);
		if(!empty($queries))
		{
			$query = '?'.http_build_query($queries);
		}
	}

	return implode('', [$parts['scheme'], '://', $account, $parts['host'], $path, $query]);
}

function cy_mk_link($text, $url = '#', $options = [])
{
	$attrs = ["href='".$url."'"];
	foreach($options as $k => $v)
	{
		$attrs[] = $k."='".$v."'";
	}

	return '<a '.implode(' ', $attrs).'>'.$text.'</a>';
}

?>
