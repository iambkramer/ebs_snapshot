<?php

/* * ************************************************************************
  |
  |	Script to Automate EBS Backups
  |	Run this script with CRON or whatever every X period of time to take
  |	automatic snapshots of your EBS Volumes.  Script will delete old
  |	snapshot after Y period of time
  |
  |	Version 1.01 updated 2012-08-02
  |	Version 1.02 updated 2013-10-09 by Brian Kramer
  |
  |	Copyright 2012 Caleb Lloyd
  |	http://www.caleblloyd.com/
  |
  |	I offer no warrant or guarentee on this code - use at your own risk
  |	You are free to modify and redistribute this code as you please
  |
  |	Requires AWS PHP SDK be configured for your AWS Account:
  |		http://aws.amazon.com/sdkforphp/
  |
  |	Optional PHPMailer Support to email results to yourself
  |		http://phpmailer.worxware.com/
  |
  |	Stores snapshot information in "./snapshot_information.json"
  |		Make sure PHP can write this file
  |
  /************************************************************************* */
/* * ************************************************************************
  |	Include Files
 * ************************************************************************ */
require_once 'common/AWSSDKforPHP/sdk.class.php';
//Your path to PHP Mailer (if you don't want to eamil yourself the results, you can get rid of this)
//require_once('phpmailer/class.phpmailer.php');
//require_once('phpmailer/smtp.inc.php');
/* * ************************************************************************
  |	Begin Configuration
 * ************************************************************************ */

//Do not take a snapshot more than every X hours/minutes/days, etc. (uses strtotime)
//This prevents the script from running out of control and producing tons of snapshots
$snapshot_limit = '1 hour';

//Keep snapshots for this amount of time (also uses strtotime)
$keep_snapshots = '5 days';

//Your path to the Amazon AWS PHP SDK
//require_once 'path_to_aws_php_sdk/sdk.class.php';
//EC2 Region, view path_to_aws_php_sdk/services/ec2.class.php for definitions
$region = 'ec2.us-east-1.amazonaws.com';

//Enable Emails upon completion
$enablemail = false;
//Provide your email server settings

$smtphost = '';
$smtpport = '';
$smtpauth = false;
$smtpuser = '';
$smtppass = '';
$emailaddr = '';
$emailname = '';

// by creating a tag on the volume labeled below with a value of 'Yes' it will be included in the backup
$BackupTagName = 'HourlyDriveBackup';

// name of json file to store the backup information
$filename = 'hourly_drive_snapshot_information.json';

// Data to be added to the snapshot description
$PrependDescription = 'Hourly-Volume  ';



/* * ************************************************************************
  | get Volumes based upon  the $BackupTagName tag
 * ************************************************************************ */
$volvar = '';
$ec2 = new AmazonEC2();
$response = $ec2->describe_volumes();
$volumes = array();
foreach ($response->body->volumeSet->item as $item) {

    foreach ($item->tagSet->item as $tags) {
        if ($tags->key == $BackupTagName && $tags->value == "Yes") {
            $volvar .= '\'' . $item->volumeId . '\'=>array(),';
            $volumes["$item->volumeId"] = array();
        }
    }
}

/* * ************************************************************************
  |	End Configuration
 * ************************************************************************ */

function snapshot_info($s) {
    $info = '<p>';
    $info.='Volume: ' . $s['volume'] . '<br />';
    $info.=(!empty($s['volume_name']) ? 'Volume Name: ' . $s['volume_name'] . '<br />' : '');
    $info.=(!empty($s['snapshot']) ? 'Snapshot: ' . $s['snapshot'] . '<br />' : '');
    $info.=(!empty($s['instance']) ? 'EC2 Instance: ' . $s['instance'] . '<br />' : '');
    $info.=(!empty($s['device']) ? 'Device: ' . $s['device'] . '<br />' : '');
    $info.=(!empty($s['error']) ? 'Error: ' . $s['error'] . '<br />' : '');
    $info.=(!empty($s['datetime']) ? 'Date/Time: ' . $s['datetime'] . '<br />' : '');
    $info.='</p>';
    return $info;
}

$success = array();
$failure = array();
$preserve = array();
$success_delte = array();
$failure_delete = array();

$ec2 = new AmazonEC2();
$ec2 = $ec2->set_region($region);

$latest_snapshot = array();

if (file_exists($filename))
    $json = file_get_contents($filename);
else
    $json = '[]';

$snapshots = json_decode($json, TRUE);

foreach ($snapshots as $s) {
    if (!empty($lastest_snapshot[$s['volume']])) {
        if ($s['timestamp'] > $lastest_snapshot[$s['volume']]['timestamp']) {
            $lastest_snapshot[$s['volume']] = $s;
        }
    } else {
        $lastest_snapshot[$s['volume']] = $s;
    }
}

foreach ($volumes as $volume => $v) {
    $v['volume'] = $volume;
    $v['instance'] = 'Not Attached to an Instance';

    $volume_information = $ec2->describe_volumes(array('VolumeId' => $volume));
    $v['volume_name'] = '(volume has no tags)';
    foreach ($volume_information->body->volumeSet->item->tagSet->item as $tags) {
        if ($tags->key == "Name") {
            $v['volume_name'] = (string) $tags->value;
        }
    }

    $description = $PrependDescription . $volume . (empty($v['volume_name']) ? '' : ' (' . $v['volume_name'] . ')');

    if (!empty($volume_information->body->volumeSet->item->attachmentSet->item->status)) {
        if ($volume_information->body->volumeSet->item->attachmentSet->item->status == "attached") {
            $v['device'] = (string) $volume_information->body->volumeSet->item->attachmentSet->item->device;
            $v['instance'] = (string) $volume_information->body->volumeSet->item->attachmentSet->item->instanceId;
            $description.=' attached to ' . $v['instance'] . ' as ' . $v['device'];
        }
    } else {
        $description.= ' (' . $v['instance'] . ')';
    }

    if ((!empty($lastest_snapshot[$volume])) && ($lastest_snapshot[$volume]['timestamp'] > strtotime('-' . $snapshot_limit))) {
        $error = TRUE;
        $v['datetime'] = date('Y-m-d H:i:s');
        $v['timestamp'] = time();
        $v['error'] = 'An Automatic Snapshot Already Exists for that volume in the past ' . $snapshot_limit;
        $failure[] = $v;
    } else {
        $response = $ec2->create_snapshot($volume, array('Description' => $description));
        if ($response->isOK()) {
            $v['datetime'] = date('Y-m-d H:i:s');
            $v['timestamp'] = time();
            $v['snapshot'] = (string) $response->body->snapshotId;
            $success[$v['snapshot']] = $v;
        } else {
            $error = TRUE;
            $v['datetime'] = date('Y-m-d H:i:s');
            $v['timestamp'] = time();
            $v['error'] = (string) $response->body->Errors->Error->Message;
            $failure[] = $v;
        }
    }
}
if (!empty($snapshots)) {
    foreach ($snapshots as $snapshot => $s) {
        $s['snapshot'] = $snapshot;
        if ($s['timestamp'] < strtotime('-' . $keep_snapshots)) {
            $response = $ec2->delete_snapshot($snapshot);
            if ($response->isOK()) {
                $success_delete[$snapshot] = $s;
            } else {
                $error = TRUE;
                $s['error'] = (string) $response->body->Errors->Error->Message;
                $failure_delete[$snapshot] = $s;
            }
        } else {
            $preserve[$snapshot] = $s;
        }
    }
    $snapshots_json = json_encode(array_merge($success, $preserve));
} else {
    $snapshots_json = json_encode($success);
}
file_put_contents($filename, $snapshots_json);

$message = '';

if (!empty($success)) {
    $message.='<p><strong>The following Snapshots Succeeded:</strong></p>';
    foreach ($success as $v) {
        $message.=snapshot_info($v);
    }
}

if (!empty($failure)) {
    $message.='<p><strong>The following Snapshots Failed and had Errors:</strong></p>';
    foreach ($failure as $v) {
        $message.=snapshot_info($v);
    }
}

if (!empty($success_delete)) {
    $message.='<p><strong>The following old Snapshots were removed:</strong></p>';
    foreach ($success_delete as $v) {
        $message.=snapshot_info($v);
    }
}

if (!empty($failure_delete)) {
    $message.='<p><strong>The following old Snapshots had errors while trying to remove:</strong></p>';
    foreach ($failure_delete as $v) {
        $message.=snapshot_info($v);
    }
}

if (!empty($preserve)) {
    $message.='<p><strong>The following Snapshots were preserved:</strong></p>';
    foreach ($preserve as $v) {
        $message.=snapshot_info($v);
    }
}

echo $message;

/* * ************************************************************************
  |	Begin PHPMailer Script
  |	Remove Below This Line if you don't want to EMail results to yourself
  |	This is the SMTP Example
  |	For other examples in PHPMailer, go to path_to_PHPMailer/examples
 * ************************************************************************ */
if ($enablemail) {
    $mail = new PHPMailer(true); // the true param means it will throw exceptions on errors, which we need to catch

    $mail->IsSMTP(); // telling the class to use SMTP

    try {
        $mail->Host = $smtphost; // sets the SMTP server
        $mail->SMTPDebug = 2;                     // enables SMTP debug information (for testing)
        $mail->SMTPAuth = $smtpauth;                  // enable SMTP authentication

        $mail->Port = $smtpport;                    // set the SMTP port for the GMAIL server
        $mail->Username = $smtpuser; // SMTP account username
        $mail->Password = $smtppass;        // SMTP account password
        $mail->AddReplyTo($emailaddr, $emailname);

        $mail->Subject = 'EBS Snapshot Backup Information for ' . date('Y-m-d') . ' - ' . ($error ? 'ERRORS' : 'Success');
        $mail->MsgHTML($message);

        $mail->Send();
        echo "Message Sent OK</p>\n";
    } catch (phpmailerException $e) {
        echo $e->errorMessage(); //Pretty error messages from PHPMailer
    } catch (Exception $e) {
        echo $e->getMessage(); //Boring error messages from anything else!
    }
}
?>