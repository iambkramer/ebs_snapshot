Automatically Take EBS Snapshots and Delete Old Ones with PHP Script
============

I am a big fan of Amazon’s RDS product, which takes automated nightly snapshots of your RDS Storage and deletes old snapshots after a specified amount of time. After reading that Amazon’s EBS drives sustain a 0.1% – 0.5% Annual Failure Rate, I also wanted to take automated nightly snapshots of my EBS drives. Deleting snapshots after a certain number of days was also of interest, because this way I am not over-paying for snapshot storage at Amazon or removing snapshots manually. I also wanted to email the results to myself after backups were taken.

I did some searching on Google and found a nice script by Chris at Applied Trust Engineering, Inc.(http://www.appliedtrust.com/resources/opensource/php-script-to-create-ebs-snapshots)
that runs PHP from the command line to create an automatic snapshot. I used this script as an example to setup my script, so thanks, Chris. 

My script has support for:
* Backup Multiple EBS Volumes
* Protect against script running again and creating another snapshot too soon
* Delete Snapshots after a Specified Period of Time
* Script outputs Detailed Snapshot Information for these 5 categories:
* Snapshots that succeeded
* Snapshots that failed and had errors
* Old Snapshots that were removed
* Old Snapshots that had errors while trying to remove
* Snapshots that were preserved
* Includes PHPMailer code to email results of script to yourself

Script Setup
You will need: (all links open in new window)
* My ebs_backup.php Code (download is a zip) (or look below)
* Stores snapshot information in “./snapshot_information.json” – Make sure PHP can write this file
* Configure the lines of code within the configuration comment blocks
* Run script periodically to your needs with CRON or whatever
* Requires AWS PHP SDK be configured for your AWS Account: http://aws.amazon.com/sdkforphp/
* Optional PHPMailer Support to email results to yourself: http://phpmailer.worxware.com/ (configure PHPMailer at the very bottom of the script)
