PHP Cli Tools
=============

##PCT是什么？##
PCT是PHP Cli Tools的简称，顾名思义，她的作用就是提供一些工具来简化或增强在命令行下执行的PHP脚本。

##PCT有哪些特性？##
> 格式化命令行参数  
> Daemon方式运行  
> 信号监听并定义回调函数  
> 多进程支持  
> 日志

**Change Log:** 

2012-11-21:  
> 加指定运行时用户(-u)

2012-11-21:  
> 使用getopt解析参数  
> 加帮助参数(-h)  
> 加log及pid路径参数(-l/-p)  
> 优化程序结束清理  
> 优化Daemon逻辑  
> 移除Singleton，并入到Daemon
