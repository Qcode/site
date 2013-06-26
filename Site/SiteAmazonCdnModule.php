<?php

require_once 'AWSSDKforPHP/sdk.class.php';
require_once 'Site/SiteCdnModule.php';
require_once 'Site/exceptions/SiteCdnException.php';

/**
 * Application module that provides access to an Amazon S3 bucket.
 *
 * @package   Site
 * @copyright 2010-2013 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class SiteAmazonCdnModule extends SiteCdnModule
{
	// {{{ public properties

	/**
	 * The name of the S3 bucket to use
	 *
	 * @var string
	 */
	public $bucket;

	/**
	 * The key to use for accessing the bucket
	 *
	 * @var string
	 */
	public $access_key_id;

	/**
	 * The secret to use for accessing the bucket
	 *
	 * @var string
	 */
	public $access_key_secret;

	/**
	 * Whether or not cloudfront should be used for CDN resources.
	 *
	 * @var boolean
	 */
	public $cloudfront_enabled;

	/**
	 * CloudFront streaming distribution
	 *
	 * @var string
	 */
	public $streaming_distribution;

	/**
	 * CloudFront distribution key-pair id
	 *
	 * @var string
	 */
	public $distribution_key_pair_id;

	/**
	 * Filename of the file containing the CloudFront distribution private key
	 *
	 * @var string
	 */
	public $distribution_private_key_file;

	// }}}
	// {{{ protected properties

	/**
	 * The Amazon S3 accessor
	 *
	 * @var AmazonS3
	 */
	protected $s3;

	/**
	 * The Amazon CloudFront accessor
	 *
	 * @var AmazonCloudFront
	 */
	protected $cf;

	/**
	 * Storage class to use for storing the object.
	 *
	 * Must be one of STANDARD (99.999999999%, two facilities) or
	 * REDUCED_REDUNDANCY (99.99%, one facility).
	 *
	 * @var string
	 */
	protected $storage_class = 'STANDARD';

	/**
	 * CloudFront distribution private key
	 *
	 * @var string
	 */
	protected $distribution_private_key;

	// }}}
	// {{{ public function init()

	/**
	 * Initializes this module
	 */
	public function init()
	{
		if ($this->access_key_id === null ||
			$this->access_key_secret === null) {

			throw new SiteCdnException(
				'Access keys are required for the Amazon CDN module'
			);
		}

		$this->s3 = new AmazonS3(
			array(
				'key'    => $this->access_key_id,
				'secret' => $this->access_key_secret,
			)
		);

		if ($this->cloudfront_enabled) {
			$this->cf = new AmazonCloudFront(
				array(
					'key'    => $this->access_key_id,
					'secret' => $this->access_key_secret,
				)
			);

			if ($this->distribution_key_pair_id !== null &&
				$this->distribution_private_key !== null) {
				$this->cf->set_keypair_id($this->distribution_key_pair_id);
				$this->cf->set_private_key($this->distribution_private_key);
			}
		}
	}

	// }}}
	// {{{ public function setDistributionPrivateKey()

	public function setDistributionPrivateKey($distribution_private_key_file,
		$distribution_key_pair_id = null)
	{
		if (file_exists($distribution_private_key_file)) {
			$this->distribution_private_key = file_get_contents(
				$distribution_private_key_file
			);
		} else {
			throw new SiteCdnException(
				sprintf(
					'Distribution Private Key ‘%s’ missing.',
					$file
				)
			);
		}

		if ($distribution_key_pair_id !== null) {
			$this->distribution_key_pair_id = $distribution_key_pair_id;
		}

		// If this is called after init() and the cloudfront object already
		// exits, set the private key.
		if ($this->cf instanceof AmazonCloudFront) {
			$this->cf->set_keypair_id($this->distribution_key_pair_id);
			$this->cf->set_private_key($this->distribution_private_key);
		}
	}

	// }}}
	// {{{ public function setStandardRedundancy()

	public function setStandardRedundancy()
	{
		$this->storage_class = 'STANDARD';
	}

	// }}}
	// {{{ public function setReducedRedundancy()

	public function setReducedRedundancy()
	{
		$this->storage_class = 'REDUCED_REDUNDANCY';
	}

	// }}}
	// {{{ public function copyFile()

	/**
	 * Copies a file to the Amazon S3 bucket
	 *
	 * @param string $filename the name of the file to create/replace.
	 * @param string $source the source file to copy to S3.
	 * @param array $headers an array of HTTP headers associated with the file.
	 * @param string $access_type the access type, public/private, of the file.
	 *
	 * @throws SiteCdnException if the CDN encounters any problems
	 */
	public function copyFile($filename, $source, $headers,
		$access_type = 'private')
	{
		if (!is_file($source)) {
			throw new SiteCdnException(
				sprintf(
					'“%s” is not a regular file.',
					$source
				)
			);
		}

		if (!is_readable($source)) {
			throw new SiteCdnException(
				sprintf(
					'Unable to read “%s.”',
					$source
				)
			);
		}

		$headers['x-amz-meta-md5'] = md5_file($source);
		$headers['x-amz-storage-class'] = $this->storage_class;

		$acl = AmazonS3::ACL_PRIVATE;

		if (strcasecmp($access_type, 'public') === 0) {
			$acl = AmazonS3::ACL_PUBLIC;
		}

		$metadata = $this->s3->get_object_metadata($this->bucket, $filename);

		$new_md5 = $headers['x-amz-meta-md5'];
		$old_md5 = isset($metadata['Headers']['x-amz-meta-md5']) ?
			$metadata['Headers']['x-amz-meta-md5'] :
			'';

		if (($old_md5 != '') && ($new_md5 === $old_md5)) {
			$this->handleResponse(
				$this->s3->update_object(
					$this->bucket,
					$filename,
					array(
						'acl' => $acl,
						'headers' => $headers,
					)
				)
			);
		} else {
			$this->handleResponse(
				$this->s3->create_object(
					$this->bucket,
					$filename,
					array(
						'acl' => $acl,
						'headers' => $headers,
						'fileUpload' => $source,
					)
				)
			);
		}
	}

	// }}}
	// {{{ public function copyFile()

	/**
	 * Moves a file around in the S3 bucket.
	 *
	 * @param string $filename the current name of the file to move.
	 * @param string $new_filename the new name of the file to move.
	 * @param string $access_type the access type, public/private, of the file.
	 *
	 * @throws SiteCdnException if the CDN encounters any problems
	 */
	public function moveFile($old_filename, $new_filename,
		$access_type = 'private')
	{
		// Since we can't use the old ACL, at least support passing in a new ACL
		$acl = AmazonS3::ACL_PRIVATE;
		if (strcasecmp($access_type, 'public') === 0) {
			$acl = AmazonS3::ACL_PUBLIC;
		}

		// TODO: Need a simplier way to look up the current ACL as the constant
		// as this returns a bigger set of information.
		//$acl = $this->s3->getObjectAcl($this->bucket, $old_filename);

		$this->handleResponse(
			$this->s3->copy_object(
				array(
					'bucket'   => $this->bucket,
					'filename' => $old_filename,
				),
				array(
					'bucket'   => $this->bucket,
					'filename' => $new_filename,
				),
				array(
					'acl' => $acl,
					'storage' => $this->storage_class,
				)
			)
		);

		// S3 has no concept of move, so remove the old version once it has
		// been copied.
		$this->removeFile($old_filename);
	}

	// }}}
	// {{{ public function removeFile()

	/**
	 * Removes a file from the Amazon S3 bucket
	 *
	 * @param string $filename the name of the file to remove.
	 *
	 * @throws SiteCdnException if the CDN encounters any problems
	 */
	public function removeFile($filename)
	{
		$this->handleResponse(
			$this->s3->delete_object(
				$this->bucket,
				$filename
			)
		);
	}

	// }}}
	// {{{ public function getUri()

	/**
	 * Gets a URI for a file on the CDN
	 *
	 * @param string $filename the name of the file.
	 * @param string $expires expiration time expressed either as a number
	 *                        of seconds since UNIX Epoch, or any string
	 *                        that strtotime() can understand
	 * @param boolean $secure whether or not to use HTTPS. If not set, the
	 *                        value will fall back to
	 *                        SiteWebApplication::isSecure().
	 */
	public function getUri($filename, $expires = null, $secure = null)
	{
		$uri = null;

		if ($secure === null) {
			$secure = $this->app->isSecure();
		}

		if ($this->app->config->amazon->cloudfront_enabled &&
			$this->cf instanceof AmazonCloudFront) {
			$uri = $this->getCloudFrontUri($filename, $expires, false, $secure);
		} else {
			$options = array(
				'https' => $secure,
			);

			$uri = $this->s3->get_object_url(
				$this->bucket,
				$filename,
				$expires,
				$options
			);
		}

		return $uri;
	}

	// }}}
	// {{{ public function getStreamingUri()

	/**
	 * Gets a streaming URI for a file on the CDN
	 *
	 * @param string $filename the name of the file.
	 * @param string $expires expiration time expressed either as a number
	 *                        of seconds since UNIX Epoch, or any string
	 *                        that strtotime() can understand
	 * @param boolean $secure whether or not to use HTTPS. If not set, the
	 *                        value will fall back to
	 *                        SiteWebApplication::isSecure().
	 */
	public function getStreamingUri($filename, $expires = null, $secure = null)
	{
		return $this->getCloudFrontUri($filename, $expires, true, $secure);
	}

	// }}}
	// {{{ public function getMetadata()

	public function getMetadata($filename)
	{
		return $this->s3->get_object_metadata($this->bucket, $filename);
	}

	// }}}
	// {{{ public function hasStreamingDistribution()

	public function hasStreamingDistribution()
	{
		return (
			$this->cf instanceof AmazonCloudFront &&
			$this->streaming_distribution !== null
		);
	}

	// }}}
	// {{{ protected function getCloudFrontUri()

	protected function getCloudFrontUri($filename, $expires = null,
		$streaming = null, $secure = null)
	{
		$config = $this->app->config->amazon;

		if (!$config->cloudfront_enabled ||
			!$this->cf instanceof AmazonCloudFront) {
			throw new SwatException('CloudFront must be enabled '.
				'streaming URIs in the Amazon CDN module');
		}

		if ($streaming) {
			if (!$this->hasStreamingDistribution()) {
				throw new SwatException('Distribution keys are required for '.
					'streaming URIs in the Amazon CDN module');
			}

			$distribution = ($expires === null) ?
				'streaming_distribution' :
				'private_streaming_distribution';

		} else {
			$distribution = ($expires === null) ?
				'distribution' :
				'private_distribution';
		}

		if ($config->$distribution === null) {
			throw new SwatException(
				sprintf(
					'amazon.%s config setting must be set.',
					$distribution
				)
			);
		}

		if ($secure === null) {
			 $secure = $this->app->isSecure();
		}

		if ($expires === null) {
			$uri = sprintf(
				'%s://%s/%s',
				$secure ?
					'https' :
					'http',
				$config->$distribution,
				$filename
			);
		} else {
			$options = array(
				'Secure' => $secure,
			);

			$uri = $this->cf->get_private_object_url(
				$config->$distribution,
				$filename,
				$expires,
				$options
			);
		}

		return $uri;
	}

	// }}}
	// {{{ protected function handleResponse()

	/**
	 * Handles a response from a CDN operation
	 *
	 * @param CFResponse $response the response to the CDN operation.
	 *
	 * @throws SiteCdnException if the response indicates an error
	 */
	protected function handleResponse(CFResponse $response)
	{
		if (!$response->isOK()) {
			if ($response->body instanceof SimpleXMLElement) {
				$message = $response->body->asXML();
			} else {
				$message = 'No error response body provided.';
			}

			throw new SiteCdnException($message, $response->status);
		}
	}

	// }}}
}

?>
