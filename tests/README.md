# http-client测试
##测试开始前mock server
cd mockServer

./go.sh start
##一、运行所有测试用例
cd tests/

phpunit

##二、运行单个测试用例
cd tests/

phpunit xxTest.php

##测试开始完成后关闭mock server
cd mockServer

./go.sh stop
