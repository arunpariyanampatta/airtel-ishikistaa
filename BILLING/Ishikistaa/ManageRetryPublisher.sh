echo "Checking Service"
cnt=`ps -eaflc --sort stime | grep  IshikistaaRetryPublisher.php |grep -v grep | wc -l`
echo $cnt
if(test $cnt -le 1) ;
then
    echo "Services for Retry Publisher are already running..."
else
  echo "Starting Services for..."
  echo `nohup sh  StartIshikistaaRetryPublisher.sh`
fi

