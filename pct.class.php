<?php
// PHP Cli Tools
class PCT {
	private $_origin_argv = array();
	private $_parsed_args = array();
	private $_handled_cmd = array();
	private $_signal_handler = array();
	private $_child_process = array();
	private $_pid_file = NULL;
	private $_log_file = NULL;
	
	public function __construct($configs=array()) {
		global $argv;
		
		if (strtolower(PHP_OS) != 'linux' && strtolower(PHP_OS) != 'freebsd') {
			throw new PCTException("PCT only works in linux or freebsd!\n");
			exit();
		}
		
		if (!extension_loaded('pcntl')) {
			throw new PCTException("PCT needs support of pcntl extension!\n");
			exit();
		}
		
		if (php_sapi_name() != 'cli') {
			throw new PCTException("PCT only works in Cli sapi!\n");
			exit();
		}
		
		$scriptname = basename($argv[0]);
		
		if (isset($configs['pidfile']) && !empty($configs['pidfile'])) {
			$this->_pid_file = realpath($configs['pidfile']);
		} else {
			$this->_pid_file = $scriptname.".pid";
		}
		
		if (isset($configs['logpath']) && !empty($configs['logpath'])) {
			$this->_log_file = realpath($configs['logpath']);
		}
		
		// 切换到脚本所在目录
		chdir(dirname($argv[0]));
		
		pcntl_signal(SIGINT, array($this, '_exit'));
		register_shutdown_function(array($this, '_shutdown'));

		$this->_parse_args();
		$this->_handle_cmd();
	}
	
	private function _parse_args() {
		// getopt(): No support for long options when php version < 5.3
		// set user/set pid/set logpath/daemon/help
		$this->_parsed_args = getopt('u:p:l:dh');
	}
	
	// 处理命令
	private function _handle_cmd() {
		if (!empty($this->_parsed_args)) {
			foreach ($this->_parsed_args as $cmd => $val) {
				$this->_prepair_cmd($cmd, $val);
			}
			foreach ($this->_parsed_args as $cmd => $val) {
				$this->_start_cmd($cmd, $val);
			}
		}
	}
	
	// 设置
	private function _prepair_cmd($cmd, $args=null) {
		switch ($cmd) {
			case 'h':
				$this->_cmd_help();
				exit();
			case 'l':
				$this->_cmd_setlogpath($args);
				break;
			case 'p':
				$this->_cmd_setpidfile($args);
				break;
			default:
				return;
		}
		
		unset($this->_parsed_args[$cmd]);
		$this->_handled_cmd[] = $cmd;
	}
	
	// 执行
	private function _start_cmd($cmd, $args=null) {
		switch ($cmd) {
			case 'd':
				$this->_cmd_daemon();
				break;
			case 'u':
				$this->_cmd_setuser($args);
				break;
			default:
				return;
		}
		
		unset($this->_parsed_args[$cmd]);
		$this->_handled_cmd[] = $cmd;
	}
	
	// 帮助
	private function _cmd_help() {
		echo <<<HELP
help:
	-h: show help
	-d: Daemonize
	-u: run as username
	-l: log file full path
	-p: pid file full path\n
HELP;
	}
	
	// Daemon
	private function _cmd_daemon() {
		// 先检测进程是否已存在
		if (file_exists($this->_pid_file)) {
			$pid = file_get_contents($this->_pid_file);
			if (file_exists("/proc/{$pid}")) {
				throw new PCTException("another instance exist!\n");
				exit();
			}
		}
		
		$pid = pcntl_fork();
		if ($pid == -1) {
			throw new PCTException("Could not fork!\n");
			exit();
		} else if ($pid) {
			$this->Log("Grandparent Process Exit!\n");
			exit();
		} else {	//First child process
			chdir('/');
			posix_setsid();
			umask(0);
			$pid2 = pcntl_fork();
			if ($pid2 == -1) {
				throw new PCTException("Could not fork!\n");
				exit();
			} else if ($pid2) {
				$this->Log("Parent Process Exit!\n");
				exit();
			} else {	//Set first child process as the session leader.
				flush();
				fclose(STDIN);
				fclose(STDOUT);
				fclose(STDERR);
				$this->Log("All fd closed!\n");
				
				$this->Log("Daemon Process Launched!\n");
				// 写daemon pid
				file_put_contents($this->_pid_file, posix_getpid());
			}
		}
	}
	
	private function _cmd_setuser($username) {
		if (($pw = posix_getpwnam($username)) === false) {
            throw new PCTException("can't find the user {$username} to switch to\n");
			exit();
        }
        if (posix_setgid($pw['gid']) === false || posix_setuid($pw['uid']) === false) {
			throw new PCTException("failed to assume identity of user {$username}\n");
			exit();
        }
	}
	
	// 设置log文件路径
	private function _cmd_setlogpath($path) {
		$this->_log_file = ltrim($path, '=');
	}
	
	// 设置pid文件路径
	private function _cmd_setpidfile($path) {
		$this->_pid_file = ltrim($path, '=');
	}
	
	// 命令清理
	public function _shutdown() {
		if (!empty($this->_handled_cmd)) {
			foreach ($this->_handled_cmd as $cmd) {
				switch ($cmd) {
					case 'd':
						if (file_exists($this->_pid_file)) {
							@unlink($this->_pid_file);
						}
						break;
				}
			}
		}
	}
	
	public function _exit() {
		$this->_shutdown();
		exit();
	}
	
	// Daemon Mode
	public function DaemonMode() {
		$this->_start_cmd('d');
	}
	
	// 解析命令行参数
	public function parseArgs() {
		return $this->_parsed_args;
	}
	
	// 注册信号回调
	public function registerSignal($signal, $callback) {
		$this->_signal_handler[$signal][] = $callback;
		pcntl_signal($signal, array(&$this,"handleSignals"));
	}
	
	// 执行信号回调
	public function handleSignals($signal) {
		if (isset($this->_signal_handler[$signal]) && !empty($this->_signal_handler[$signal])) {
			foreach ($this->_signal_handler[$signal] as $func) {
				$func(posix_getpid());
			}
		}
	}
	
	// 多进程
	public function MultiProcess($num, $cpfunc, $wait=false) {
		$child_process = array();
		for ($i=0;$i<$num;$i++) {
			$pid = pcntl_fork();
			if ($pid == -1) {
				throw new PCTException("Could not fork!\n");
				exit();
			} else if ($pid) {
				$this->Log("Child Process:{$pid} Launched!\n");
				$child_process[] = $pid;
			} else {
				$cpfunc();
				$this->Log("Child Process:".posix_getpid()." Exited!\n");
			}
		}
		
		if (!isset($this->_child_process[$cpfunc])) $this->_child_process[$cpfunc] = array();
		
		if (!empty($child_process)) {
			foreach ($child_process as $pid) {
				if ($wait && $pid > 0) pcntl_waitpid($pid, $status);
				if (!in_array($pid, $this->_child_process[$cpfunc])) $this->_child_process[$cpfunc][] = $pid;
			}
		}
		
		return $child_process;
	}
	
	// 向指定进程组发送信号
	public function signalProcessGroup($group, $signal) {
		if (isset($this->_child_process[$group]) && !empty($this->_child_process[$group])) {
			foreach ($this->_child_process[$group] as $pid) {
				posix_kill($pid, $signal);
			}
		}
	}
	
	// 日志函数
	public function Log($msg) {
		if (!is_null($this->_log_file)) {
			return error_log($msg, 3, $this->_log_file);
		} else {
			echo $msg;
		}
	}
	
	public function __destruct() {}
}

class PCTException extends Exception {}
?>