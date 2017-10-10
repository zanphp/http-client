#!/usr/bin/env bash
path=`pwd`
echo $path
case "$1" in
    start)
        echo "start mock http server"
        php HttpEchoServer.php &
    ;;
    stop)
        echo "stop http server"
        httpEchoServer=$(ps -ef|grep 'php HttpEchoServer.php'|grep -v "grep"|awk -F " " '{print $2}'|sort)
        kill -9 $httpEchoServer
    ;;
    *)
    echo "invalid options"
    ;;
esac