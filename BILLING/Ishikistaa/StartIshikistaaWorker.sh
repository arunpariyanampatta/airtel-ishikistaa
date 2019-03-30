#!/bin/bash

val="$(grep -oE '\$worker = .*;' queue-config.php | tail -1 | sed 's/$worker = //g;s/;//g')"

for ((i = 0;i < "$val";i++))
do
php IshikistaaWorker.php &
done

echo All "$val"  Wokers  Started

