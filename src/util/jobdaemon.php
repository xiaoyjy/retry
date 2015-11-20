<?php 
declare(ticks=1); 

class CY_Util_JobDaemon
{ 
    public $max_processes = 25;
    
    protected $jobs_started = 0; 
    protected $current_jobs = array(); 
    protected $signal_queue = array();   
    protected $parentPID; 
   
    public function __construct()
    { 
        $this->parentPID = getmypid(); 
        pcntl_signal(SIGCHLD, array($this, "child_signal_handler")); 
    } 
   
    /** 
    * Run the Daemon 
    */ 
    public function run($task, $callback, $opt = []){ 
        for($i=0; $i< $this->max_processes; $i++)
        {
            $sub_task = $task[$i]; 
            $launched = $this->launch_job($i, $sub_task, $callback); 
        } 
       
        //Wait for child processes to finish before exiting here 
        while(count($this->current_jobs))
        { 
            sleep(1); 
        } 
    } 
   
    /** 
    * Launch a job from the job queue 
    */ 
    protected function launch_job($job_id, $task, $callback)
    { 
        $pid = pcntl_fork(); 
        if($pid == -1)
        { 
            cy_log(CY_ERROR, 'Could not launch new job, exiting'); 
            return false; 
        } 
        else if ($pid)
        { 
            // Parent process 
            // Sometimes you can receive a signal to the childSignalHandler function before this code executes if 
            // the child script executes quickly enough! 
            $this->current_jobs[$pid] = $job_id; 
           
            // In the event that a signal for this pid was caught before we get here, it will be in our signalQueue array 
            // So let's go ahead and process it now as if we'd just received the signal 
            if(isset($this->signal_queue[$pid]))
            { 
                $this->child_signal_handler(SIGCHLD, $pid, $this->signal_queue[$pid]); 
                unset($this->signal_queue[$pid]); 
            } 
        } 
        else
        { 
            $exit_status = 0;
            call_user_func($callback, $task); 
            exit($exit_status); 
        } 
        return true; 
    } 
   
    public function child_signal_handler($signo, $pid=null, $status=null)
    { 
        //If no pid is provided, that means we're getting the signal from the system.  Let's figure out 
        //which child process ended 
        if(!$pid)
        { 
            $pid = pcntl_waitpid(-1, $status, WNOHANG); 
        } 
       
        //Make sure we get all of the exited children 
        while($pid > 0)
        { 
            if($pid && isset($this->current_jobs[$pid]))
            { 
                $exit_code = pcntl_wexitstatus($status); 
                if($exit_code != 0)
                { 
                    echo "$pid exited with status ".$exit_code."\n"; 
                } 
                unset($this->current_jobs[$pid]); 
            } 
            else if($pid)
            { 
                //Oh no, our job has finished before this parent process could even note that it had been launched! 
                echo "..... Adding $pid to the signal queue ..... \n"; 
                $this->signal_queue[$pid] = $status; 
            } 
            $pid = pcntl_waitpid(-1, $status, WNOHANG); 
        } 
        return true; 
    } 
}

