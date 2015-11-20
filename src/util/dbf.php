<?php

/*
 * dbf文件处理类
 *
 */

class CY_Util_DBF
{
    public $rec_num;
    public $field_num;
    public $field_names = [];
    
    private $_handler;
    private $_row;
    private $_row_size;
    private $_hdrsize;
    private $_memos;

    public function load_dbf($filename, $mode = 0)
    {
        if(!file_exists($filename))
        {
            return cy_dt(CYE_ERROR, 'Not a valid DBF file!');
        }
        
        $tail = substr($filename, -4);
        if(strcasecmp($tail, '.dbf') != 0)
        {
            return cy_dt(CYE_ERROR, 'Not a valid DBF file!');
        }
        
        try
        {
            $this->_handler = dbase_open($filename, $mode);
        }
        catch(Exception $e)
        {
            echo $e->getMessage();
            return cy_dt(CYE_ERROR, 'open DBF file failed!');
        }

        $this->field_num = dbase_numfields($this->_handler);
        $this->rec_num = dbase_numrecords($this->_handler);
        $this->field_names = dbase_get_header_info($this->_handler);

        return cy_dt(0);
    }    

    public function __destruct()
    {
        if($this->_handler)
        {
            dbase_close($this->_handler);
        }
    }
    
    public function get_row($recnum)
    {
        return  dbase_get_record($this->_handler, $recnum);
    }

    public function get_row_assoc($recnum)
    {
        $data = $this->get_row($recnum);

        $rt = [];
        foreach($data as $k => $v)
        {
            if(isset($this->field_names[$k]) && $this->field_names[$k]['name'])
            {
                $rt[strtolower($this->field_names[$k]['name'])] = $v;
            }
        }

        return $rt;
    }

    public function create_dbf($filename, $def)
    {
        if(!dbase_create($filename, $def))
        {
            return cy_dt(CYE_ERROR, 'create DBF file failed!');
        }

        return cy_dt(0);
    }


}
