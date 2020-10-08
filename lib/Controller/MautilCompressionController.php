<?php
namespace OCA\MautilCompression\Controller;

use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use \OCP\IConfig;
use OCP\EventDispatcher\IEventDispatcher;

include("/pdftron/PDFNetPHP.php");


class MautilCompressionController extends Controller {
    private $config;
	private $userId;

	public function __construct(IConfig $config, $AppName, IRequest $request, string $UserId){
		parent::__construct($AppName, $request);
        $this->config = $config;
		$this->userId = $UserId;
        // PDFTron-specific stuff
        PDFNet::Initialize();
	    PDFNet::GetSystemFontList();
	}
    
    /**
	* @NoAdminRequired
	*/
	public function getExternalMP(){
		$mounts = \OC_Mount_Config::getAbsoluteMountPoints($this->UserId);
		$externalMountPoints = array();
		foreach($mounts as $mount){
			if ($mount["class"] == "local"){
				$externalMountPoints[$mount["mountpoint"]] = $mount["options"]["datadir"];
			}
		}
		return $externalMountPoints;
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
		$response = array();
		if ($external){
			$externalUrl = $this->getExternalMP();
			$desc = "";
			$dircpt = substr($directory, 1);
			while ($dircpt != ""){
				if (array_key_exists($dircpt, $externalUrl)){
					$url = $externalUrl[$dircpt];
					$dircpt = str_replace($dircpt, "", $directory);
					if (file_exists($url.'/'.$dircpt.'/'.$filename)){
						$result = $this->compressPDF($url.'/'.$dircpt.'/', $filename, $newFilename, $imgQuality);
						if ($override){
							unlink($url.'/'.$directory.'/'.$filename);
						}
						$response = array_merge($response, array("code" => true));
						return json_encode($response);
					} else {
						$response = array_merge($response, array("code" => false, "desc" => "Can't find file on external local storage : ".$url.'/'.$dircpt.'/'.$filename));
						return json_encode($response);
					}
				} else {
					$pos = strrpos($dircpt, '/');
					if ($pos == false){
						$dircpt = "/";
					} else {
						$dircpt = substr($dircpt, 0, $pos);
					}
				}
			}
			$response = array_merge($response, array("code" => false, "desc" => "Can't find file on external local storage"));
			return json_encode($response);
		} else {
			if ($shareOwner != null){
				$this->UserId = $shareOwner;
			}
			if (file_exists($this->config->getSystemValue('datadirectory', '').'/'.$this->UserId.'/files'.$directory.'/'.$filename)){
				$result = $this->compressPDF($this->config->getSystemValue('datadirectory', '').'/'.$this->UserId.'/files'.$directory.'/', $filename, $newFilename, $imgQuality);
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
	}

    /**
	* @NoAdminRequired
	*/
	public function compressPDF($link, $filename, $newFilename, $imgQuality = 1) {
        if($newFilename == null) {
            $newFilename = $link.pathinfo($filename)['filename']." [Compressed].pdf";
        } else {
            $newFilename = $link.pathinfo($newFilename)['filename'].".pdf";
        }
        // Load doc
        $doc = new PDFDoc($link.$filename);
	    $doc->InitSecurityHandler();
        // Create configuration for images
        $image_settings = new ImageSettings();
	    // low quality jpeg compression
        // TODO: add option for good quality compression
	    $image_settings->SetCompressionMode(ImageSettings::e_jpeg);
	    $image_settings->SetQuality($imgQuality);
        // TODO: add option for result dpi
	    // Set the output dpi to be standard screen resolution
	    $image_settings->SetImageDPI(144,96);
	    // this option will recompress images not compressed with
	    // jpeg compression and use the result if the new image
	    // is smaller.
        // TODO: add option for this
	    $image_settings->ForceRecompression(true);
        // Create configuration for optimizer
        $opt_settings = new OptimizerSettings();
        // TODO: Make separate option (bool) for each type
        // Add our image configurations for colored images
	    $opt_settings->SetColorImageSettings($image_settings);
        // Add our image configurations for grayscale images
	    $opt_settings->SetGrayScaleImageSettings($image_settings);
	    // Apply optimizations
	    Optimizer::Optimize($doc, $opt_settings);
        // Save result
	    $doc->Save($newFilename, SDFDoc::e_linearized);
	    $doc->Close();
        // Return path of the new file
        return $newFilename
    }

    /**
	* @NoAdminRequired
	*/
	public function scanFolder($path, $user) {
		$response = array();
		/*if($user == null){
			$user = \OC::$server->getUserSession()->getUser()->getUID();
		}*/
		$version = \OC::$server->getConfig()->getSystemValue('version');
		 if((int)substr($version, 0, 2) < 18){
			$scanner = new \OC\Files\Utils\Scanner($user, \OC::$server->getDatabaseConnection(), \OC::$server->getLogger());
		 } else {
			$scanner = new \OC\Files\Utils\Scanner($user, \OC::$server->getDatabaseConnection(), \OC::$server->query(IEventDispatcher::class), \OC::$server->getLogger());
		 }
		try {
            $scanner->scan($path, $recusive = false);
        } catch (ForbiddenException $e) {
			$response = array_merge($response, array("code" => false, "desc" => $e->getTraceAsString()));
			return json_encode($response);
        }catch (NotFoundException $e){
			$response = array_merge($response, array("code" => false, "desc" => $this->l->t("Can't scan file at ").$path));
			return json_encode($response);
		}catch (\Exception $e){
			$response = array_merge($response, array("code" => false, "desc" => $e->getTraceAsString()));
			return json_encode($response);
		}
		return 1;
	}
}
