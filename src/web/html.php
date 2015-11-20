<?php

class CY_Web_HTML
{
	static function link($text, $url = '#', $options = [])
	{
		$attrs = ["href='".$url."'"];
		foreach($options as $k => $v)
		{
			$attrs[] = $k."='".$v."'";
		}

		return '<a '.implode(' ', $attrs).'>'.$text.'</a>';
	}

}

//$a = new CY_Web_HTML();
//echo $a->link('你好', '#', ['class' => 'n']);

?>
