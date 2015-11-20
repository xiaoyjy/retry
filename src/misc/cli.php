<?php

function getopt_merge($o)
{
	$opts = [];
	foreach($o as $k => $v)
	{
		switch($k)
		{
			case 'f':
			case 'file':
				$opts['file'] = $v;
				break;

			case 't':
			case 'title':
				$opts['title'] = $v;
				break;

			case 'n':
			case 'name':
				$opts['name'] = $v;
				break;

			case 'h':
			case 'host':
				$opts['host'] = $v;
				break;

			case 'c':
			case 'cmd':
				$opts['cmd'] = $v;
				break;

			case 'r':
			case 'rule':
				$opts['rule'] = $v;
				break;

			case 'u':
			case 'url':
				$opts['url'] = $v;
				break;

			case 'm':
			case 'message':
				$opts['message'] = $v;
				break;
		}
	}

	return $opts;
}


function read_line($o)
{
	static $fp = NULL;
	if($fp === NULL)
	{
		$fp = fopen('php://stdin', 'r');
	}

	$i  = 0;

	if(feof($fp))
	{
		if(isset($o['d']))
		{
			echo "Sleeping ...\n";
			sleep(2);
			continue;
		}
		else
		{
			return '';
		}
	}

	return trim(fgets($fp));
}

?>
