#!/usr/local/bin/php -q
<?php
error_reporting(E_ALL);
set_time_limit(0);
ini_set('memory_limit', -1);
declare(ticks = 1);

require_once 'pct.class.php';

$pct = new PCT();
$ppid = getmypid();

//$pct->DaemonMode();

$formatArgs = $pct->parseArgs();
$pct->Log(print_r($formatArgs, true));
for ($i=0;$i<100;$i++) {
	$pct->Log($i."\n");
	sleep(1);
}

/*
$pct->registerSignal(SIGHUP, "sighupCb1");
//$pct->registerSignal(SIGHUP, "sighupCb2");

//$pct->registerSignal(SIGINT, "sighupCb2");

sleep(1);

function sighupCb1($pid) {
	global $ppid, $pct;
	if ($ppid == $pid) {
		echo "parent ";
		$pct->signalProcessGroup("child_process_group1", SIGHUP);
	} else {
		echo "child ";
	}
	echo "sighup1\n";
}
function sighupCb2($pid) {
	global $ppid;
	if ($ppid == $pid) {
		echo "parent ";
	} else {
		echo "child ";
	}
	echo "sighup2 || sigint\n";
}


$group1 = $pct->MultiProcess(3, "child_process_group1");
function child_process_group1() {
	echo "child_process_group1\n";
	// 子进程循环
	while(1) {
		usleep(200000);
	}
}

$group2 = $pct->MultiProcess(3, "child_process_group2");
function child_process_group2() {
	echo "child_process_group2\n";
	// 子进程循环
	while(1) {
		usleep(200000);
	}
}

// 父进程循环
while(1) {
	usleep(100000);
}
*/
?>
