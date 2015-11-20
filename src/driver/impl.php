<?php

interface CY_Driver_Impl
{
	function options();

	function inputs($opt);
	
	function outputs($opt);

	function callback($data);
}

/* vim: set ts=4 sw=4 sts=4 tw=100 noet: */
?>
