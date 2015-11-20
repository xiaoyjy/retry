<?php
/**
 *
 */

class CY_Model_Default
{
	protected $db; 
	protected $table;

	function __construct($table, $config = NULL)
	{
		$this->table = $table;
		$this->db    = new CY_Util_MySQL($config);
	}

	/**
	 * Example1:
	 * ===============================================
	 * kv => id=1 ----> 'SELECT * FEOM `$this->table` WHERE id=1'
	 * 
	 * Example2:
	 * ===============================================
	 * kv => [id => 1]  ----> 'SELECT * FEOM `$this->table` WHERE id=1'
	 *
	 * Example3:
	 * ===============================================
	 * kv => [id => [2, 3]]  ----> 'SELECT * FEOM `$this->table` WHERE id IN (2, 3)'
	 *
	 *
	 */
	function mGet($kv, $options = [])
	{
		if(empty($kv))
		{
			return cy_dt(CYE_PARAM_ERROR);
		}

		$table = isset($options['prefix']) ? $options['prefix'].'_'.$this->table : $this->table;

		$i = 0;
		if(is_string($kv))
		{
			$r = ['where' => $kv];
			$c = new CY_Driver_DB_Default($table, $r, $options);
			$this->db->add($i++, $c);
			return cy_r_m($this->db->mGet());
		}
		else
		{
			if(!isset($options['key']) && count($kv) === 1)
			{
				list($options['key'], $v) = each($kv);
			}
		}

		$chunk_size = isset($options['chunk_size']) ? $options['chunk_size'] : 50;
		unset($options['chunk_size']);

		foreach($kv as $k => $v)
		{
			if(is_scalar($v))
			{
				/* key = value */
				$vv = [$v];
			}
			else
			{
				/* key IN (values) */
				$vv = array_chunk($v, $chunk_size);
			}

			foreach($vv as $cv)
			{
				$r = ['where' => [$k => $cv]];
				$c = new CY_Driver_DB_Default($table, $r, $options); 
				$this->db->add($i++, $c);
			}
		}

		return cy_r_m($this->db->mGet());
	}

	function mSet($data, $options = [])
	{
		$chunk_size = isset($options['chunk_size']) ? $options['chunk_size'] : 50;
		$chunk_data = array_chunk($data, $chunk_size);
		$update     = isset($options['update']) ? $options['update'] : false;

		$table = isset($options['prefix']) ? $options['prefix'].'_'.$this->table : $this->table;

		$i = 0;
		foreach($chunk_data as $chunk)
		{
			$r = ['data' => $chunk];
			$c = new CY_Driver_DB_Default($table, $r, ['method' => 'insert', 'update' => $update]);
			$this->db->add($i++, $c);
		}

		$dt = $this->db->mGet();
		if($dt['errno'] !== 0)
		{
			return $dt;
		}

		$insert_id  = PHP_INT_MAX;
		$affected_rows = 0;
		foreach($dt['data'] as $i => $dt_sub)
		{
			if($dt_sub['errno'] !== 0)
			{
				cy_log(CYE_WARNING, 'MySQL::mSet part failed.');
				continue;
			}

			$v = $dt_sub['data'];
			if($v['insert_id'] === 0 || $v['affected_rows'] === 0)
			{
				cy_log(CYE_DEBUG, 'MySQL::mSet insert but no affect, maybe exists');
				continue;
			}

			$insert_id > $v['insert_id'] && $insert_id = $v['insert_id'];
			$affected_rows += $v['affected_rows'];
		}

		$insert_id === PHP_INT_MAX && $insert_id = 0;
		return cy_dt(0, ['insert_id' => $insert_id, 'affected_rows' => $affected_rows]);
	}

	function update($kv, $data, $options = [])
	{
		$table = isset($options['prefix']) ? $options['prefix'].'_'.$this->table : $this->table;

		$r = ['where' => $kv, 'data' => $data];
		$c = new CY_Driver_DB_Default($table, $r, ['method' => 'update']); 
		$this->db->add(0, $c);

		return cy_r_m($this->db->mGet());
	}

	function delete($kv, $options = [])
	{
		$chunk_size = isset($options['chunk_size']) ? $options['chunk_size'] : 50;
		$table = isset($options['prefix']) ? $options['prefix'].'_'.$this->table : $this->table;

		$i = 0;
		foreach($kv as $k => $v)
		{
			$vv = array_chunk($v, $chunk_size);
			foreach($vv as $cv)
			{
				$r = ['where' => [$k => $cv]];
				$c = new CY_Driver_DB_Default($table, $r, ['method' => 'delete']); 
				$this->db->add($i++, $c);
			}
		}

		return cy_r_m($this->db->mGet());
	}

	function max($field)
	{
		$table = isset($options['prefix']) ? $options['prefix'].'_'.$this->table : $this->table;
		return $this->db->query('SELECT max('.$field.') AS '.$field.' FROM `'.$table.'`');
	}

}

?>
