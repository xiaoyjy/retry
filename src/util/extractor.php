<?php
/* html extractor */

class CY_Util_Extractor
{
	protected $lines = [];
	protected $black = [];

	protected $html;
	protected $html_clean;

	function load($html)
	{
		//$t1 = microtime(true);
		//
		$this->html = $html;
		//$cost = (microtime(true) - $t1)*1000000;
		//cy_stat('extractor-init-dom', $cost);
		return true;
	}

	function black($md5)
	{
		$this->black[$md5] = 1;
	}

	function lines()
	{
		if(!empty($this->lines))
		{
			return $this->lines;
		}

		$t1 = microtime(true);
		$content = cy_remove_html_attr($this->html, ['style']);
		$pattern = [];
		//$pattern[] = '/<!--.*?-->/s';
		$pattern[0] = '/<script.*?>.*?<\/script>/si';
		$pattern[1] = '/<style.*?>.*?<\/style>/si';
		$pattern[2] = '/<(\w+)[^>]*?><\/\1>/si';
		$pattern[3] = '/<a.*?>|<\/a>/si';
		$pattern[4] = '/<\s*([^>]+?)\s*>/s';

		$replace   = array_fill(0, 4, '');
		$replace[] = '<$1>';

		$this->html_clean = preg_replace($pattern, $replace, $content);
		$texts            = $this->textlines($this->html_clean);
		//$this->html_clean = preg_replace('/<\s*([^>]*?)\s*>/s', '$1', $content);	
		foreach($texts as $i => $line)
		{
			$this->lines[$i] = $line;
		}

		$cost = (microtime(true) - $t1)*1000000;
		cy_stat('html-lines', $cost);
		return $this->lines;
	}

	function title()
	{
		$string = '';
		$array = array();

		$title = $this->tag_text_title();
		foreach(['h1', 'h2', 'h3', 'strong'] as $tag)
		{
			$array[] = $a = $this->tag_texts($tag);
			foreach($a as $txt)
			{
				$titles  = [];
				$txt_arr = [];
				$txt_arr[] = $txt;
				if(strpos($txt, '<') !== false)
				{
					$txt_arr[] = strip_tags($txt);
					$txt_arr[] = $txt = trim(preg_replace('/<.*>/s', '', $txt));
				}

				if(strpos($txt, '(') !== false)
				{
					$txt_arr[] = trim(preg_replace('/\(.*?\)/', '', $txt));
				}

				if(strpos($txt, '[') !== false)
				{
					$txt_arr[] = trim(preg_replace('/\[.*?\]/', '', $txt));
				}

				if(strpos($txt, '（') !== false)
				{
					$txt_arr[] = trim(preg_replace('/\（.*?\）/', '', $txt));
				}

				if(strpos($txt, '【') !== false)
				{
					$txt_arr[] = trim(preg_replace('/\【.*?\】/', '', $txt));
				}

				foreach($txt_arr as $txt)
				{
					if($txt && strpos($title, $txt) !== false)
					{
						$titles[] = $txt;
					}
				}

				if(!empty($titles))
				{
					$string = $this->longest($titles);
					goto end;
				}
			}
		}

		/* 把串全部解析出来 */
		$r = array();

		$texts = $this->lines();
		$p     = stripos($this->html_clean, '<body');
		foreach($texts as $pos => $txt)
		{
			if(!empty($p) && $p > $pos)
			{
				continue;
			}

			if(!empty($txt) && strpos($title, $txt) !== false)
			{
				$r[] = $txt;
			}
		}

		/* 然后选最长的那个串 */
		if(count($r))
		{
			$string = $this->longest($r);
			goto end;
		}

		/* 如果title与h1不匹配，但H1只有唯一取到一个，相信h1 */
		foreach($array as $h1arr)
		{
			if(count($h1arr) == 1 && !empty($h1arr[0]))
			{
				$string = $h1arr[0];
				goto end;
			}
		}

end:
		return $string;
	}

	function content($options = [])
	{
		$texts = $this->lines();

		$title = isset($options['title']) ? $options['title'] : '';
		if(!empty($title))
		{
			/* skip <title> tag. */
			$p = stripos($this->html_clean, '<body');
			$p = strpos ($this->html_clean, $title, $p);
		}

		$blocks= [];
		$block = '';

		$null  = 0;
		foreach($texts as $pos => $line)
		{
			if(!empty($p) && $p > $pos)
			{
				continue;
			}

			empty($line) ? $null++ : $null = 0;

			if($null > 0)
			{
				if(!empty($block))
				{
					$blocks[] = $block;
					$block    = '';
				}
			}
			else
			{
				$block .= $line;
				if($line[strlen($line)-1] == '>')
				{
					$block .= "\n";
				}
			}
		}

		$str = $this->longest($blocks);

		// end process.
		return $str;
	}

	function time()
	{
		if(preg_match('/\d\d\d\d-\d\d-\d\d\s+(\d+){0,1}(\:\d+){0,2}/', $this->html, $m))
		{
			return $m[0];
		}

		if(preg_match('/\d+年\d+月\d+日\s*(\d+){0,1}(\:\d+){0,2}/', $this->html, $m))
		{
			return $m[0];
		}

		return '';
	}

	function tag_text_title()
	{
		if(preg_match('/<title>(.+)<\/title>/si', $this->html, $m))
		{
			return $m[1];
		}

		return '';
	}

	function tag_attrs($tag, $attr)
	{
		if(preg_match_all('/<'.$tag.'[^>]+'.$attr.'\s*=\s*["\']?([^ >]+?)["\'"]/si', $this->html, $m))
		{
			return $m[1];
		}

		return [];
	}

	function tag_texts($tag)
	{
		$data = [];
		if(preg_match_all('/<'.$tag.'.*?>(.+?)<\/'.$tag.'>/si', $this->html, $m))
		{
			$arr = $m[1];
		}
		else
		{
			return $data;
		}

		foreach($arr as $h1)
		{
			foreach($this->textlines(trim($h1), []) as  $h)
			{
				if(!empty($h))
				{
					$data[] = $h;
				}
			}
		}

		return $data;
	}

	function textlines($txt, $tags = NULL)
	{
		if($tags === NULL)
		{
			$tags = ['p', 'img', 'br', 'object', 'embed', 'param'];
		}

		return cy_split_by_tag($txt, $tags);
	}

	function longest($array)
	{
		$max = 0;
		$idx = 0;
		foreach($array as $i => $t)
		{
			if(!empty($t) && $max < strlen($t) && empty($this->black[md5($t)]))
			{
				$str = $t;
				$max = strlen($t);
			}
		}

		if(isset($str))
		{
			return $str;
		}

		return '';
	}

	function links($sitename, $dir)
	{
		$links = array();
		foreach($this->tag_attrs('a', 'href') as $path)
		{
			if(!$this->isLink($path))
			{
				continue;
			}

			if($path[0] === '/')
			{
				$links[] = 'http://'.$sitename.$path;
			}
			else if(strncasecmp('http://', $path, 7) === 0)
			{
				$links[] = $path;
			}
			else if(strncasecmp('https://', $path, 8) === 0)
			{
				$links[] = $path;
			}
			else if(strncasecmp('http%3a%2f%2f', $path, 13) === 0)
			{
				$links[] = urldecode($path);
			}
			else if(strncmp($path, '../', 3) === 0)
			{
				do
				{
					$tmp_dir = $dir;
					$path    = substr($path, 3);
					$tmp_dir = dirname($tmp_dir);
				}
				while(strncmp($path, '../', 3) === 0);

				$links[] = 'http://'.$sitename.$tmp_dir.'/'.$path;
			}
			else
			{
				$links[] = 'http://'.$sitename.$dir.'/'.$path;
			}
		}

		return $links;
	}

	function isLink($link)
	{
		if($link[0] === '#')
		{
			return false;
		}

		if(preg_match('/[\(\)\;]/', $link))
		{
			return false;
		}

		if(stripos($link, ':') !== false)
		{
			if(!preg_match('#http[s]?://#i', $link))
			{
				return false;
			}
		}

		return true;
		/*
		   if(stripos($link, 'javascript') !== false)
		{
			return false;
		}

		if(stripos($link, 'mailto:') !== false)
		{
			return false;
		}

		if(stripos($link, 'tel:') !== false)
		{
			return false;
		}

		if(stripos($link, 'file:') !== false)
		{
			return false;
		}

		return true;
*/
	}

}

?>
