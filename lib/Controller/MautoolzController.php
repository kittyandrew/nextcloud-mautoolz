<?php
namespace OCA\Mautoolz\Controller;

use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use \OCP\IConfig;
use OCP\EventDispatcher\IEventDispatcher;
use \OCP\ILogger;
use OCA\Files_External\MountConfig;


class MautoolzController extends Controller {
    private $config;
	private $userId;
    private $logger;


	public function __construct(IConfig $config, $AppName, IRequest $request, string $UserId, ILogger $logger){
		parent::__construct($AppName, $request);
        $this->config = $config;
        $this->logger = $logger;
		$this->UserId = $UserId;
	}
    
	/**
	 * @NoAdminRequired
	 */
    public function compressFile($filename, $directory, $external, $override = false, $imgQuality = 1, $newFilename = null, $shareOwner = null, $mtime = 0) {
		if (preg_match('/(\/|^)\.\.(\/|$)/', $filename)) {
			$response = ['code' => false, 'desc' => 'Can\'t find file'];
			return json_encode($response);
		}
		if (preg_match('/(\/|^)\.\.(\/|$)/', $directory)) {
			$response = ['code' => false, 'desc' => 'Can\'t open file at directory'];
			return json_encode($response);
		}
        if ($shareOwner != null) {
			$this->UserId = $shareOwner;
		}
        // Check if compression service is deployed
        if (!getenv("MAUTOOLZ_HOST")) {
            return json_encode(['code' => false, 'desc' => 'Variable "MAUTOOLZ_HOST" is not set! Can\'t proceed..']);
        }
        $response = array();
		if (file_exists($this->config->getSystemValue('datadirectory', '').'/'.$this->UserId.'/files'.$directory.'/'.$filename)){
			$result = $this->compressCMD($this->config->getSystemValue('datadirectory', '').'/'.$this->UserId.'/files'.$directory.'/', $filename, $newFilename, $imgQuality);
			$scan = self::scanFolder('/'.$this->UserId.'/files'.$directory.'/'.pathinfo($result)['filename'].'.pdf', $this->UserId);
			if($scan != 1){
				return $scan;
			}
			$response = array_merge($response, array("code" => true));
			return json_encode($response);
		} else {
			$response = array_merge($response, array("code" => false, "desc" => "Can't find file at ".$this->config->getSystemValue('datadirectory', '').'/'.$this->UserId.'/files'.$directory.'/'.$filename));
			return json_encode($response);
		}
	}

    /**
	* @NoAdminRequired
	*/
	public function compressCMD($link, $filename, $newFilename, $imgQuality = 1) {
        if($newFilename == null) {
            $newFilename = $link.pathinfo($filename)['filename']." [Compressed].pdf";
        } else {
            $newFilename = $link.pathinfo($newFilename)['filename'].".pdf";
        }
        $curl_command = 'curl -F "upload=@'.$link.$filename.'" '.
                        '-H "Content-Type: multipart/form-data" '.
                        'http://'.getenv("MAUTOOLZ_HOST").':8080/api/compress/pdf '.
                        '-o '.escapeshellarg($newFilename);
        exec($curl_command, $output, $return);
        // $this->error($curl_command, array('output' => $output, 'code' => $return));
        // Return path of the new file
        return $newFilename;
    }

    /**
	 * @NoAdminRequired
	 */
    public function convertToPDF($filename, $directory, $external, $override = false, $newFilename = null, $worker = null, $shareOwner = null, $mtime = 0) {
		if (preg_match('/(\/|^)\.\.(\/|$)/', $filename)) {
			$response = ['code' => false, 'desc' => 'Can\'t find file'];
			return json_encode($response);
		}
		if (preg_match('/(\/|^)\.\.(\/|$)/', $directory)) {
			$response = ['code' => false, 'desc' => 'Can\'t open file at directory'];
			return json_encode($response);
		}
        // If file is already PDF, don't do anything
        if (pathinfo($this->config->getSystemValue('datadirectory', '').'/'.$this->UserId.'/files'.$directory.'/'.$filename)["extension"] == "pdf") {
            $response = ['code' => false, 'desc' => 'File '.$filename.' is already in PDF format!'];
            return json_encode($response);
        }
        // We use different workers for different mime types
        if ($worker == null) {
            $response = ['code' => false, 'desc' => 'Worker is not defined!'];
            return json_encode($response);
        } elseif ($worker == "mautoolz-converter" and !getenv("MAUTOOLZ_CONVERT_HOST")) {
            return json_encode(['code' => false, 'desc' => 'Variable "MAUTOOLZ_CONVERT_HOST" is not set! Can\'t proceed..']);
        } elseif ($worker == "mautoolz" and !getenv("MAUTOOLZ_HOST")) {
            return json_encode(['code' => false, 'desc' => 'Variable "MAUTOOLZ_HOST" is not set! Can\'t proceed..']);
        }
        if ($shareOwner != null) {
			$this->UserId = $shareOwner;
		}
        $response = array();
		if (file_exists($this->config->getSystemValue('datadirectory', '').'/'.$this->UserId.'/files'.$directory.'/'.$filename)){
			$result = $this->convertCMD($this->config->getSystemValue('datadirectory', '').'/'.$this->UserId.'/files'.$directory.'/', $filename, $newFilename, $worker);
			$scan = self::scanFolder('/'.$this->UserId.'/files'.$directory.'/'.pathinfo($result)['filename'].'.pdf', $this->UserId);
			if($scan != 1){
				return $scan;
			}
			$response = array_merge($response, array("code" => true));
			return json_encode($response);
		} else {
			$response = array_merge($response, array("code" => false, "desc" => "Can't find file at ".$this->config->getSystemValue('datadirectory', '').'/'.$this->UserId.'/files'.$directory.'/'.$filename));
			return json_encode($response);
		}
	}

    /**
	* @NoAdminRequired
	*/
	public function convertCMD($link, $filename, $newFilename, $worker) {
        if ($newFilename == null) {
            $newFilename = $link.pathinfo($filename)['filename'].".pdf";
        } else {
            $newFilename = $link.pathinfo($newFilename)['filename'].".pdf";
        }
        // different workers, different urls
        if ($worker == "mautoolz-converter") {
            $curl_command = 'curl -s -F format=pdf '.
                            '-F "file=@'.$link.$filename.'" '.
                            'http://'.getenv("MAUTOOLZ_CONVERT_HOST").':3000/convert '.
                            '-o '.escapeshellarg($newFilename);
            exec($curl_command, $output, $return);
            // $this->error($curl_command, array('output' => $output, 'code' => $return));
            // Return path of the new file
            return $newFilename;
        } elseif ($worker == "mautoolz") {
            $curl_command = 'curl -s -F "file=@'.$link.$filename.'" '.
                            '-H "Content-Type: multipart/form-data" '.
                            'http://'.getenv("MAUTOOLZ_HOST").':8080/api/convert/pdf '.
                            '-o '.escapeshellarg($newFilename);
            exec($curl_command, $output, $return);
            $this->error($curl_command.'code='.$return, array('output' => $output, 'code' => $return));
            // Return path of the new file
            return $newFilename;
        } else {
            $this->error('Impossible worker: '.$worker, array('filename' => $filename));
            return null;
        }
    }

    /**
	* @NoAdminRequired
	*/
	public function scanFolder($path, $user) {
		$response = array();
		$scanner = new \OC\Files\Utils\Scanner($user, \OC::$server->getDatabaseConnection(), \OC::$server->query(IEventDispatcher::class), \OC::$server->getLogger());
		try {
            $scanner->scan($path, $recusive = false);
        } catch (ForbiddenException $e) {
			$response = array_merge($response, array("code" => false, "desc" => $e->getTraceAsString()));
			return json_encode($response);
        } catch (NotFoundException $e) {
			$response = array_merge($response, array("code" => false, "desc" => $this->l->t("Can't scan file at ").$path));
			return json_encode($response);
		} catch (\Exception $e) {
			$response = array_merge($response, array("code" => false, "desc" => $e->getTraceAsString()));
			return json_encode($response);
		}
		return 1;
	}

    public function error($command, $data=null) {
        if ($data == null) {
            $data = array();
        }
        $this->logger->error($command, $data);
    }
}
