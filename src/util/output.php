<?php

class CY_Util_Output
{
	protected $options = array('display' => 'html');
	protected $data    = array();

	function __construct($data = NULL, $options = NULL)
	{
		$options && $this->options = $options;
		$data    && $this->data    = $data;

		isset($_ENV['display']) && $this->options['display'] = $_ENV['display'];
	}

	function assign()
	{
		switch(func_num_args())
		{
			case 1:
				$this->data += func_get_arg(0);
				break;
			case 2:
				$this->data[func_get_arg(0)] = func_get_arg(1);
				break;
		}
	}

	function render($file = NULL)
	{
		echo $this->get($file); 
	}

	function get($file = NULL)
	{
		/*
		if(isset($this->data['backtrace']))
		{
			unset($this->data['backtrace']);
		}
		*/
		switch($this->options['display'])
		{
			case 'php':
				extract($this->data);
				include $file;
				break;

			case 'html':
				try
				{
					$smarty = class_exists('CA_Util_Smarty') ? 'CA_Util_Smarty' : 'CY_Util_Smarty';
					$tpl    = new $smarty();
					/*
					$tpl->assign("cy_home"      , CY_HOME);
					$tpl->assign("cy_url_base"  , $_ENV['url_base']);
					$tpl->assign("cy_url_path"  , $_ENV['url_path']);
					$tpl->assign("cy_id"        , $_ENV['id']      );

					// cy_user_id 放这里不合适，但先就这样了吧
					$tpl->assign("cy_user_id"   , $_ENV['session']->user_id());
					*/
					$tpl->assign($this->data);
					return $tpl->fetch($file);
				}
				catch(Exception $e)
				{
					if($_ENV['debug'])
					{
						if(empty($this->data['exitting']))
						{
							cy_exit(CYE_TEMPLATE_ERROR, "Smarty template exception:".
								$e->getMessage()." On ".$file);
						}
						else // in case error template have exception.
						{
							echo "Smarty template exception:".$e->getMessage()." On ".$file;
							exit;
						}
					}
				}

			case 'jsonh':
				// TODO: use smart here.
				break;

			case 'json':
				if(PHP_SAPI !== 'cli') header('Content-Type: application/json');
				return json_encode($this->data);

			case 'jsonp':
				//header('Content-Type: application/jsonp');
				$method = isset($_GET['method']) ? trim($_GET['method']) : 'callback';
				if(!preg_match('/^[0-9a-zA-Z_]+$/', $method))
				{
					$method = 'callback';
				}

				return $method."(".json_encode($this->data).");";

			case 'raw':
				if(empty($this->data['data']))
				{
					return '';
				}

				return $this->data['data'];

			case 'xml':
				default:
					break;

		}

		return '';
	}
}

/* vim: set ts=4 sw=4 sts=4 tw=100 noet: */
?>
