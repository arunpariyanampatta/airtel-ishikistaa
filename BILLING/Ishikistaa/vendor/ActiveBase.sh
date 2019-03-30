\#!/bin/bash
source /var/local/Shell_Script/broadcast_base_auto.conf
backup_time=$(date +%Y%m%d_%H_%M_%S);
table_name="vas_tbl_qn_subscribers_${backup_time}"
backup_name="vas_tbl_qn_subscribers_${backup_time}.sql.gz"
echo "Backup Process started at $(date +%Y%m%d_%H_%M_%S)";
cd $BACKUP_PATH
mysqldump -h $HOST -u $USER -p"$PASSWORD" $DB $TABLE --extended-insert --max_allowed_packet=512M  --single-transaction --where "ACTIVITY_STATUS  = 'ACTIVE' AND DND_STATUS = '0'" |\
  sed -e "s/tbl_qn_subscribers/${table_name}/"| gzip -c >$backup_name
echo "Backup Process finished at $(date +%Y%m%d_%H_%M_%S)";

echo "Restore and validation check process started at $(date +%Y%m%d_%H_%M_%S)";
gunzip < $backup_name | mysql $DEST_DB
echo "Restore and validation check process finished at $(date +%Y%m%d_%H_%M_%S)";
