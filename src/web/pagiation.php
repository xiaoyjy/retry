<?php

class CY_Web_Pagiation
{
	function split($current, $total, $size = 10, $win_size = 5)
	{
		$re = array();
		$total_page = ceil($total/$size);


		$current = (int)$current;
		if(1 > $current || $current > $total_page)
		{
			$current = 1;
		}

		$pages = array();
		$step  = (int)($win_size/2);
		$min   = $current - $step; 
		$max   = $current + $step;
		if($min < 1)
		{
			$max += 1 - $min; 
			$min  = 1;
		}

		if($max > $total_page)
		{
			$min -= $max - $total_page;
			$min  = max(1, $min);
			$max  = $total_page;
		}

		$pages = range($min, $max);
		$re['prev']    = $current-1>0 ? $current-1 : 1;
		$re['next']    = $current+1<$total_page?$current+1:$total_page;
		$re['pages']   = $pages;
		$re['current'] = $current;
		return $re;
	}

	function html()
	{

	}
}

?>
