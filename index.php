<?php 
namespace SS3;

// error_reporting(-1);
// ini_set('display_errors', 'On');

require_once 'vendor/autoload.php';
use Aws\Common\Aws;
use Aws\S3\Transfer;

class PocSThree {

	public function __construct($options = array()){
		$this->options = $options;
		$this->aws = Aws::factory($options);
    	$this->S3Client = $this->aws->get('s3');
	}

	/**
	 * lists the buckets
	 */
	public function listS3Buckets(){
		return $this->S3Client->listBuckets();
	}

	/**
	 * list contents of a bucket
	 	$bucket_name = name of the bucket
	 	$prefix = the folder path of which contents you wanna display
	 */
	public function getBucketObjects($bucket_name, $prefix = ''){
		if($bucket_name == ''){
			return false;
		}

		$objects = array();

		$iterator = $this->S3Client->getIterator('ListObjects',
			array(
				'Bucket' => $bucket_name,
				'Prefix' => $prefix
			)
		);

		foreach ($iterator as $object) {
			$objects[] = $object['Key'];
		}

		return $objects;
	}

	/**
	 * to copy a folder from local to s3
	 	$source = local directory absolute path
	 	$destination = s3 bucket
	 */
	public function pushDirectoryToBucket($source, $destination){
		$manager = new Transfer($this->S3Client, $source, $destination);
		$manager->transfer();
	}

	/**
	 * to copy from one bucket to another
	 	$source = the source bucket name
	 	$skey = name of the file to copy
	 	$destination = the destination bucket name
	 	$dkey = name of the file to be copied (can have a name of your choice)
	 */
	public function copyBucketToBucket($source, $skey, $destination, $dkey){
		if($source == '' || $skey == '' || $destination == '' || $dkey == ''){
			return false;
		}

		return $this->S3Client->copyObject(array(
			'Bucket' => $destination,
			'Key' => $dkey,
			'CopySource' => "{$source}/{$skey}"
		));
	}

	/**
	 * to delete a bucket
	 */
	public function deleteBucket($bucket_name){
		if($bucket_name == ''){
			return false;
		}

		//list the bucket contents
		$bucket_contents = $this->getBucketObjects($bucket_name);

		if(!empty($bucket_contents)){
			foreach ($bucket_contents as $value) {
				$this->deleteObject($bucket_name, $value);
			}
		}

		return $this->S3Client->deleteBucket(array(
			'Bucket' => $bucket_name
		));
	}

	/**
	 * to create a new bucket
	 	$bucket_name = bucket name
	 	$acl = access control list. e.g. 'private|public-read|public-read-write|authenticated-read'
	 */
	public function createBucket($bucket_name, $acl = ''){
		if($bucket_name == ''){
			return false;
		}

		if($acl == ''){
			$acl = 'public-read';
		}

		return $this->S3Client->createBucket(array(
			'ACL' => $acl,
			'Bucket' => $bucket_name
		));
	}

	/**
	 * to delete object from bucket
	 	$bucket_name = bucket name
	 	$key = the name of the object to be deleted
	 */
	public function deleteObject($bucket_name, $key){
		if($bucket_name == '' || $key == ''){
			return false;
		}

		return $this->S3Client->deleteObject(array(
			'Bucket' => $bucket_name,
			'Key' => $key
		));
	}

	/**
	 * to delete multiple objects from bucket
	 	$bucket_name = bucket name
	 	$objects = array containing the name of the objects to be deleted
	 */
	public function deleteObjects($bucket_name, $objects = array()){

		if($bucket_name == '' || empty($objects)){
			return false;
		}

		foreach ($objects as $value) {
			$this->deleteObject($bucket_name, $value);
		}
	}

	/**
	 * to download image from remote url to s3 bucket
	 	$bucket_name = destination bucket name
	 	$source_url = url from which image/file is to be downloaded
	 	$key = destination file name
	 	$folder = folder structure inside which you want the image to be place
	 	$local_path = path of folder inside your application which will be used to store temporary files. Always end the path with a '/'
	 	E.g. $folder = test/testing/  if it is to be placed inside testing folder which is inside test folder
	 	Note: Keep it blank if you need it to store in the bucket itself
	 	also end it with a /
	 */
	public function putInBucketFromUrl($bucket_name, $source_url, $local_path, $key, $folder){
		if($bucket_name == '' || $source_url == '' || $local_path == '' || $key == '' || $folder == ''){
			return false;
		}

		$local_path = $local_path.$key;
		$local_img = file_put_contents($local_path, file_get_contents($source_url));

		if($local_img){
			return $this->S3Client->putObject(array(
				'Bucket' => $bucket_name,
				'SourceFile' => $local_path,
				'Key' => $folder.$key
			));
		}

		unlink($local_path);
	}

	/**
	 * to create folder inside a bucket
	 	$bucket_name = destination bucket name
	 	$folder_name = desired folder name
	 */
	public function createFolder($bucket_name, $folder_name){
		if($bucket_name == '' || $folder_name == ''){
			return false;
		}

		return $this->S3Client->putObject(array(
				'Bucket' => $bucket_name,
				'Body' => "",
				'Key' => $folder_name.'/'
			));
	}

	/**
	 * to upload file to Bucket or folder inside bucket
	 	$bucket_name = destination bucket name
	 	$source_file = absolute path of the file
	 	$key = destination file name

	 	INCASE you want to store the file inside a folder in the bucket,
	 	$key = folder(s)/file_name
	 	E.g. : test/sam.jpg
	 */
	public function uploadFileToBucket($bucket_name, $source_file, $key){
		if($bucket_name == '' || $source_file == '' || $key == ''){
			return false;
		}

		return $this->S3Client->putObject(array(
			'Bucket' => $bucket_name,
			'SourceFile' => $source_file,
			'Key' => $key
		));
	}

	/**
	 * to get s3 object information
	 	$bucket_name = name of the bucket
	 	$key = name/path(after the bucket name) of the file
	 */
	public function getObject($bucket_name, $key){
		if($bucket_name == '' || $key == ''){
			return false;
		}

		return $this->S3Client->getCommand('GetObject',array(
			'Bucket' => $bucket_name,
    		'Key'    => $key
		));
	}

	/**
	 * to get the signed url needed incase of private files
	 	$bucket_name = name of the bucket
	 	$key = name/path(after the bucket name) of the file
	 */
	public function getSignedUrl($bucket_name, $key){
		if($bucket_name == '' || $key == ''){
			return false;
		}

		$cmd = $this->getObject($bucket_name, $key);
		return $cmd->createPresignedUrl('+3 minutes');
	}

}


/**
 * use the following in your application where you need
 */
// $options = array(
// 	'version'     => 'latest',
// 	'region'      => 'us-east-1',
// 	'bucket_name' => '',
// 	'credentials' => array(
// 		'key' => '',
// 		'secret' => ''
// 	)
// );


// $poc = new PocSThree($options);
// echo "<pre>";
// print_r($poc->listS3Buckets());