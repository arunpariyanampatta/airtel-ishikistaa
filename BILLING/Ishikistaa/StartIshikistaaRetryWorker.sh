#!/bin/bash

val="$(grep -oE '\$retryWorker = .*;' queue-config.php | tail -1 | sed 's/$retryWorker = //g;s/;//g')"

for ((i = 0;i < "$val";i++))
do
php IshikistaaRetryWorker.php &
done

echo All "$val"  Wokers  Started

