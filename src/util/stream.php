<?php

class CY_Util_Stream
{
	static function default_write($stream, $body, $options)
	{
		$length = strlen($body);
		do
		{
			if(($rv = fwrite($stream, $body, $length)) == false)
			{
				$id = (int)$stream;
				$sv = isset($options['server']) ? $options['server'] : 'unknown hosts.';
				cy_log(CYE_ERROR, $sv.' default_write error');
				return array('errno' => CYE_NET_ERROR);
			}

			$length -= $rv;
			if($length)
			{
				$body = substr($body, $rv);
			}

		}while($stream && $length);
		return array('errno' => 0);
	}

	static function default_read($stream, $options)
	{
		static $length = 4096;
		$data = '';

		$size = isset($options['size']) ? $options['size'] : 0;
		$left = $size ? $size : $length;
		stream_set_blocking($stream, 1);
		do
		{
			if(($buf = fread($stream, $left)) === false)
			{
				$id = (int)$stream;
				cy_log(CYE_ERROR, $options['server'].' default_read error');
				return array('errno' => CYE_NET_ERROR);
			}

			$data .= $buf;
			if($size)
			{
				$left -= strlen($buf);
				if($left < 1)
					break;
			}
			else
			{
				if(strlen($buf) !== $length)
					break;
			}
		}
		while($left && $buf !== NULL && !feof($stream));

		return array('errno' => 0, 'data' => $data);
	}

	static function http_read($stream, $options)
	{
		static $length = 4096;
		$array = [];

		stream_set_blocking($stream, 1);
		$n_blank_line = 0;
		$size         = 0;
		$buf = fgets($stream, $length);
		if(!$buf)
		{
			return array('errno' => -1);
		}

		$array[] = $buf;
		$header  = [];
		do
		{
			if(($buf = fgets($stream, $length)) === false)	
			{
				$id = (int)$stream;
				cy_log(CYE_ERROR, $options['server'].' http_read error');
				return array('errno' => CYE_NET_ERROR);	
			}

			$pair = explode(":", $buf, 2);
			if(isset($pair[1]))
			{
				$header[strtolower($pair[0])] = trim($pair[1]);
			}

			$array[] = $buf;
		}
		while($buf !== "\r\n" && $buf !== NULL && !feof($stream));

		if(isset($header['content-length']))
		{
			$size = (int)$header['content-length'];
		}

		$data = implode("", $array);
		if($buf !== NULL && $size)
		{
			$options['size'] = $size;
			$dt = self::default_read($stream, $options);
			if($dt['errno'] === 0)
			{
				$data .= $dt['data'];
			}
		}

		return array('errno' => 0, 'data' => $data);
	}

}

/* vim: set ts=4 sw=4 sts=4 tw=100 noet: */
?>
