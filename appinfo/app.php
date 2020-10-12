<?php
/**
 * Load Javascrip
 */
use OCP\Util;
$eventDispatcher = \OC::$server->getEventDispatcher();
$eventDispatcher->addListener('OCA\Files::loadAdditionalScripts', function(){
    Util::addScript('mautoolz', 'compress');
    Util::addScript('mautoolz', 'convert');
    Util::addStyle('mautoolz', 'style' );
});
