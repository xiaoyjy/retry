<?php

class CY_Util_Loader
{
	protected $route;
	protected $namespace_map = [];

	public function __construct($auto_register = true)
	{
		if($auto_register)
		{
			spl_autoload_register(array($this, 'loader'));
		}

		$this->route = [
			'cy' => CY_LIB_PATH,
			'ch' => CY_HOME,
			'ca' => CY_HOME.'/app'
				];
	}

	public function loader($name)
	{
		$lower = strtolower($name);	
		$head  = substr($lower, 0, 2);
		if(empty($this->route[$head]))
		{
			if(strpos($name, '\\') !== false)
			{
				$this->load_namespace($name);
			}

			return;
		}

		$path = $this->route[$head];
		$filename = $path.'/'.strtr(substr($lower, 3), '_', '/').'.php';
		if(is_file($filename))
		{
			include $filename;
			return;
		}
	}

	public function load_namespace($name)
	{
		list($key) = explode('\\', $name);
		if(isset($this->namespace_map[$key]))
		{
			$filename = $this->namespace_map[$key].strtr(substr($name, strlen($key)), '\\', '/').'.php';
		}
		else
		{
			$filename = CY_HOME.'/app/'.strtr($name, '\\', '/').'.php';
		}

		if(is_file($filename))
		{
			include $filename;
		}
	}

	public function set_namespace_path($key, $path)
	{
		$this->namespace_map[$key] = $path;
	}

}

/* vim: set ts=4 sw=4 sts=4 tw=100 noet: */
?>
