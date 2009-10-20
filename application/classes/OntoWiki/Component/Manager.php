<?php

/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @category   OntoWiki
 * @package    OntoWiki_Component
 * @copyright Copyright (c) 2008, {@link http://aksw.org AKSW}
 * @license   http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 * @version   $Id: Manager.php 4095 2009-08-19 23:00:19Z christian.wuerker $
 */

/**
 * OntoWiki component manager
 *
 * Scans a component directory for suitable components, loads their 
 * configuration settings and registers them for the dispatcher and the navigation.
 *
 * Components usually provide a controller to seve requests and are thus often 
 * visible as tabs in OntoWiki. It is however possible for a component to not be
 * visible in the user interface at all, e.g. a controller serving Ajax requests.
 *
 * A component must meet the following requirements:
 * - A folder with a unique name under the OntoWiki components folder
 * - A component.ini configuration file with at least the key 'active'#
 * - An first letter uppercased component controller .php file named like the folder
 *   with the extension 'Controller' that provides a class of the same name derived
 *   from OntoWiki_Component_Controller
 * - Optionally, a component helper derived from OntoWiki_Component_Helper that is named
 *   like the component with the suffix 'Helper'
 *
 * @category   OntoWiki
 * @package    OntoWiki_Component
 * @copyright Copyright (c) 2008, {@link http://aksw.org AKSW}
 * @license   http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 * @author    Norman Heino <norman.heino@gmail.com>
 */
class OntoWiki_Component_Manager
{
    /**
     * The component config file
     */
    const COMPONENT_CONFIG_FILE   = 'component.ini';
    
    /**
     * Component class name suffix
     */
    const COMPONENT_HELPER_SUFFIX = 'Helper';
    
    /** 
     * Array where component information is kept
     * @var array 
     */
    protected $_componentRegistry = array();
    
    /** 
     * The path scanned for components
     * @var string 
     */
    protected $_componentPath = null;
    
    /** 
     * Prefix to distinguish component controller directories
     * from other controller directories.
     * @var string 
     */
    private $_componentPrefix = '_component_';
    
    /** 
     * Keys in the component configuration file storing path names that 
     * should be normalized.
     * @var array 
     */
    private $_pathKeys = array(
        'templates', 
        'languages'
    );
    
    /** 
     * Name of the private section in the component config file
     * @var string 
     */
    private $_privateSection = 'private';
    
    /**
     * Constructor
     */ 
    public function __construct($componentPath)
    {
        $this->_componentPath = $componentPath;
        
        // scan for components
        $this->_scanComponentPath();
    }
    
    /**
     * Returns registered components.
     *
     * @return array
     */
    public function getComponents()
    {
        return $this->_componentRegistry;
    }
    
    /**
     * Returns the path the component manager used to search for components.
     *
     * @return string
     */
    public function getComponentPath()
    {
        return $this->_componentPath;
    }
    
    public function getComponentUrl($componentName)
    {
        if (!$this->isComponentRegistered($componentName)) {
            throw new OntoWiki_Component_Exception("Component with key '$componentName' not registered");
        }
        
        $config = OntoWiki_Application::getInstance()->config;
        
        return $config->staticUrlBase . $config->extensions->components . $componentName . '/';    
    }
    
    /**
     * Checks whether a specific component is registered.
     * 
     * A registered component has been found valid and is activated
     * in its configuration file.
     *
     * @param  string $componentName
     * @return boolean
     */
    public function isComponentRegistered($componentName)
    {
        if (array_key_exists($componentName, $this->_componentRegistry)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Returns a prefix that can be used to distinguish components from
     * other extensions, i.e. modules or plugins.
     *
     * @return string
     */
    public function getComponentPrefix()
    {
        return $this->_componentPrefix;
    }
    
    /**
     * Returns the template path for a given component.
     *
     * @param  string $componentName
     * @return string
     */
    public function getComponentTemplatePath($componentName)
    {
        if (!$this->isComponentRegistered($componentName)) {
            throw new OntoWiki_Component_Exception("Component with key '$componentName' not registered");
        }
        
        if (array_key_exists('templates', $this->_componentRegistry[$componentName])) {
            $path = $this->_componentPath 
                  . $componentName 
                  . DIRECTORY_SEPARATOR 
                  . $this->_componentRegistry[$componentName]['templates'];
            
            return $path;
        }
    }
    
    /**
     * Returns the component's private configuration section
     *
     * @param  string $componentName
     * @return array|null
     */
    public function getComponentPrivateConfig($componentName)
    {
        if (!$this->isComponentRegistered($componentName)) {
            throw new OntoWiki_Component_Exception("Component with key '$componentName' not registered");
        }
        
        if (array_key_exists($this->_privateSection, $this->_componentRegistry[$componentName])
            and ($this->_componentRegistry[$componentName][$this->_privateSection] instanceof Zend_Config_Ini)) {
            
            $privateConfig = $this->_componentRegistry[$componentName][$this->_privateSection];
            
            return $privateConfig;
        }
    }
    
    /**
     * Scans the component path for conforming components and 
     * announces their paths to appropriate components.
     */
    private function _scanComponentPath()
    {
        $dir = new DirectoryIterator($this->_componentPath);
        foreach ($dir as $file) {
            if (!$file->isDot() && $file->isDir()) {
                $fileName = $file->getFileName();
                $innerComponentPath = $this->_componentPath . $fileName . DIRECTORY_SEPARATOR;
                
                // scan for component files
                if (is_readable($innerComponentPath . self::COMPONENT_CONFIG_FILE)) {
                    $this->_addComponent($fileName, $innerComponentPath);
                }
            }
        }
    }
    
    /**
     * Reads a components onfiguration and adds it to the internal registry.
     *
     * @param string $componentName the component's (folder) name
     * @param string $componentPath the path to the component folder
     */
    private function _addComponent($componentName, $componentPath)
    {
        $tempArray = parse_ini_file($componentPath . self::COMPONENT_CONFIG_FILE);
        
        // break if component is not enabled
        if (!array_key_exists('active', $tempArray) or !((boolean)$tempArray['active'])) {
            return;
        }
        
        // load private config as Zend_Config
        try {
            $tempArray[$this->_privateSection] = new Zend_Config_Ini(
                $componentPath . self::COMPONENT_CONFIG_FILE, 
                $this->_privateSection, 
                true);
        } catch (Zend_Config_Exception $e) {
            // config error
        }
        
        // normalize paths
        foreach ($this->_pathKeys as $pathKey) {
            if (array_key_exists($pathKey, $tempArray)) {
                $tempArray[$pathKey] = rtrim($tempArray[$pathKey], '/\\') . '/';
            }   
        }
        
        // save component's path
        $tempArray['path'] = $this->_componentPath 
                           . $componentName 
                           . DIRECTORY_SEPARATOR;
        
        // add component
        $this->_componentRegistry[$componentName] = $tempArray;
        
        // load helper
        $helperClassName = ucfirst($componentName) . self::COMPONENT_HELPER_SUFFIX;
        $helperPathName  = $componentPath . $helperClassName . '.php';
        
        if (is_readable($helperPathName)) {
            // execute component helper
            include_once $helperPathName;
            
            if (class_exists($helperClassName)) {
                $hlp = new $helperClassName($this);
            }
        }
        
        $action = null;
        if (array_key_exists('action', $tempArray)) {
            $action = $tempArray['action'];
        }
        
        $position = null;
        if (array_key_exists('position', $tempArray)) {
            $position = $tempArray['position'];
        }
        
        if (array_key_exists('navigation', $tempArray) && (boolean)$tempArray['navigation']) {
            // register with navigation
            OntoWiki_Navigation::register($componentName, array(
                'controller' => $componentName, 
                'action'     => $action, 
                'name'       => $tempArray['name'], 
                'priority'   => $position, 
                'active'     => false
            ));
        }
        
        $translate = OntoWiki_Application::getInstance()->translate;
        
        if (array_key_exists('languages', $tempArray) && is_readable($componentPath . $tempArray['languages'])) {
            $translate->addTranslation(
                $componentPath . $tempArray['languages'], 
                null, 
                array('scan' => Zend_Translate::LOCALE_FILENAME));
        }
        
        // reset locale
        $translate->setLocale(OntoWiki_Application::getInstance()->config->languages->locale);
    }
}

