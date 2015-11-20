<?php

include CY_LIB_PATH.'/3rd/smarty/libs/Smarty.class.php';

class CY_Util_Smarty extends Smarty
{
	function __construct()
	{
		parent::__construct();

		$this->template_dir = CY_HOME.'/app/html';
		$this->compile_dir  = CY_HOME.'/data/c';
		$this->cache_dir    = CY_HOME.'/data/cache';
		$this->caching      = 0;
		$this->left_delimiter = "<{";
		$this->right_delimiter = "}>";
	}
}

?>
